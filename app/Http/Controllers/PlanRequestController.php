<?php

namespace App\Http\Controllers;

use App\Models\PlanRequest;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlanRequestController extends BaseController
{
    use HasPermissionChecks;
    public function index(Request $request)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $query = PlanRequest::with(['user', 'plan', 'approver', 'rejector']);

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('plan', function ($planQuery) use ($search) {
                    $planQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $planRequests = $query->latest()->paginate($perPage);

        return Inertia::render('plans/plan-request', [
            'planRequests' => $planRequests,
            'filters' => $request->only(['search', 'status', 'per_page'])
        ]);
    }

    public function approve(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $planRequest->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        // Create plan order
        \App\Models\PlanOrder::create([
            'user_id' => $planRequest->user_id,
            'plan_id' => $planRequest->plan_id,
            'billing_cycle' => $planRequest->duration,
            'original_price' => $planRequest->duration === 'yearly' ? $planRequest->plan->yearly_price : $planRequest->plan->price,
            'final_price' => $planRequest->duration === 'yearly' ? $planRequest->plan->yearly_price : $planRequest->plan->price,
            'status' => 'approved'
        ]);

        // Assign the plan to the user
        \DB::table('users')
            ->where('id', $planRequest->user_id)
            ->update([
                'plan_id' => $planRequest->plan_id,
                'plan_is_active' => 1,
                'plan_expire_date' => $planRequest->duration === 'yearly' ? now()->addYear() : now()->addMonth()
            ]);

        return redirect()->route('plan-requests.index')->with('success', __('Plan request approved successfully!'));
    }

    public function reject(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        $planRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        return redirect()->route('plan-requests.index')->with('success', __('Plan request rejected successfully!'));
    }

    public function myRequests(Request $request)
    {
        $query = PlanRequest::with(['user', 'plan', 'approver', 'rejector'])
            ->where('user_id', Auth::id());

        // Apply search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('plan', function ($planQuery) use ($search) {
                $planQuery->where('name', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $planRequests = $query->latest()->paginate($perPage);

        return Inertia::render('plans/plan-request', [
            'planRequests' => $planRequests,
            'filters' => $request->only(['search', 'status', 'per_page']),
            'isMyRequests' => true
        ]);
    }

    public function cancelMyRequest(PlanRequest $planRequest)
    {
        // Ensure user can only cancel their own pending requests
        if ($planRequest->user_id !== Auth::id()) {
            abort(403, __('Unauthorized'));
        }

        if ($planRequest->status !== 'pending') {
            return back()->withErrors(['error' => __('Only pending requests can be cancelled')]);
        }

        $planRequest->update([
            'status' => 'cancelled',
            'rejected_at' => now(),
            'rejected_by' => Auth::id()
        ]);

        return back()->with('success', __('Plan request cancelled successfully'));
    }

    public function destroy(PlanRequest $planRequest)
    {
        $this->authorizePermission('plan_manage_requests');
        
        // Only allow deleting cancelled requests
        if ($planRequest->status !== 'cancelled') {
            return back()->withErrors(['error' => __('Only cancelled requests can be deleted')]);
        }

        $planRequest->delete();

        return back()->with('success', __('Plan request deleted successfully'));
    }
}
