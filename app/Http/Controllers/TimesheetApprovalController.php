<?php

namespace App\Http\Controllers;

use App\Models\TimesheetApproval;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimesheetApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can access timesheet approvals
        if (!$workspace->isOwner($user) && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can access timesheet approvals.');
        }
        
        // Validate status filter
        $validStatuses = ['pending', 'approved', 'rejected', 'all'];
        $statusFilter = $request->status && in_array($request->status, $validStatuses) ? $request->status : 'all';
        
        $query = TimesheetApproval::with(['timesheet.user', 'timesheet.entries', 'approver'])
            ->whereHas('timesheet', function($q) use ($user) {
                $q->where('workspace_id', $user->current_workspace_id);
            });

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($request->search) {
            $searchTerm = trim($request->search);
            if (!empty($searchTerm)) {
                $query->whereHas('timesheet.user', function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                });
            }
        }

        if ($request->from_date) {
            $fromDate = $request->validate(['from_date' => 'date'])['from_date'];
            $query->whereHas('timesheet', function($q) use ($fromDate) {
                $q->where('start_date', '>=', $fromDate);
            });
        }

        if ($request->to_date) {
            $toDate = $request->validate(['to_date' => 'date'])['to_date'];
            $query->whereHas('timesheet', function($q) use ($toDate) {
                $q->where('end_date', '<=', $toDate);
            });
        }

        // Managers only see approvals from their assigned projects
        if ($userWorkspaceRole === 'manager') {
            $query->whereHas('timesheet.entries', function($q) use ($user) {
                $q->whereHas('project', function($projectQuery) use ($user) {
                    $projectQuery->where(function($pq) use ($user) {
                        $pq->whereHas('members', function($memberQuery) use ($user) {
                            $memberQuery->where('user_id', $user->id);
                        })
                        ->orWhere('created_by', $user->id);
                    });
                });
            });
        }

        $perPage = in_array($request->per_page, [12, 24, 36, 48]) ? $request->per_page : 12;
        $approvals = $query->latest()->paginate($perPage);

        return Inertia::render('timesheets/Approvals', [
            'approvals' => $approvals,
            'filters' => $request->only(['status', 'search', 'per_page', 'view', 'from_date', 'to_date']),
            'userWorkspaceRole' => $workspace->isOwner($user) ? 'owner' : $userWorkspaceRole
        ]);
    }

    public function approve(Request $request, TimesheetApproval $approval)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can approve timesheets
        if (!$workspace->isOwner($user) && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can approve timesheets.');
        }
        
        // Check if approval is still pending
        if ($approval->status !== 'pending') {
            return back()->with('error', __('This approval has already been processed.'));
        }
        
        $validated = $request->validate([
            'comments' => 'nullable|string'
        ]);

        try {
            $approval->update([
                'status' => 'approved',
                'comments' => $validated['comments'],
                'approved_at' => now()
            ]);

            // Update timesheet status if all approvals are complete
            $this->updateTimesheetStatus($approval->timesheet);

            return back()->with('success', __('Timesheet approved successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to approve timesheet.'));
        }
    }

    public function reject(Request $request, TimesheetApproval $approval)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can reject timesheets
        if (!$workspace->isOwner($user) && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can reject timesheets.');
        }
        
        // Check if approval is still pending
        if ($approval->status !== 'pending') {
            return back()->with('error', __('This approval has already been processed.'));
        }
        
        $validated = $request->validate([
            'comments' => 'required|string'
        ]);

        try {
            $approval->update([
                'status' => 'rejected',
                'comments' => $validated['comments'],
                'approved_at' => now()
            ]);

            $approval->timesheet->update(['status' => 'rejected']);

            return back()->with('success', __('Timesheet rejected successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to reject timesheet.'));
        }
    }

    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can approve timesheets
        if (!$workspace->isOwner($user) && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can approve timesheets.');
        }
        
        $validated = $request->validate([
            'approval_ids' => 'required|array',
            'approval_ids.*' => 'exists:timesheet_approvals,id',
            'comments' => 'nullable|string'
        ]);

        $approvals = TimesheetApproval::whereIn('id', $validated['approval_ids'])
            ->where('status', 'pending')
            ->get();

        if ($approvals->isEmpty()) {
            return back()->with('error', __('No pending approvals found.'));
        }

        try {
            foreach ($approvals as $approval) {
                $approval->update([
                    'status' => 'approved',
                    'comments' => $validated['comments'],
                    'approved_at' => now()
                ]);

                $this->updateTimesheetStatus($approval->timesheet);
            }

            return back()->with('success', __('Timesheets approved successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to approve timesheets.'));
        }
    }

    public function bulkReject(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Only owners and managers can reject timesheets
        if (!$workspace->isOwner($user) && $userWorkspaceRole !== 'manager') {
            abort(403, 'Access denied. Only owners and managers can reject timesheets.');
        }
        
        $validated = $request->validate([
            'approval_ids' => 'required|array',
            'approval_ids.*' => 'exists:timesheet_approvals,id',
            'comments' => 'required|string'
        ]);

        $approvals = TimesheetApproval::whereIn('id', $validated['approval_ids'])
            ->where('status', 'pending')
            ->get();

        if ($approvals->isEmpty()) {
            return back()->with('error', __('No pending approvals found.'));
        }

        try {
            foreach ($approvals as $approval) {
                $approval->update([
                    'status' => 'rejected',
                    'comments' => $validated['comments'],
                    'approved_at' => now()
                ]);

                $approval->timesheet->update(['status' => 'rejected']);
            }

            return back()->with('success', __('Timesheets rejected successfully!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to reject timesheets.'));
        }
    }

    private function updateTimesheetStatus(Timesheet $timesheet)
    {
        try {
            $pendingApprovals = $timesheet->approvals()->where('status', 'pending')->count();
            
            if ($pendingApprovals === 0) {
                $rejectedApprovals = $timesheet->approvals()->where('status', 'rejected')->count();
                
                if ($rejectedApprovals > 0) {
                    $timesheet->update(['status' => 'rejected']);
                } else {
                    $timesheet->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update timesheet status: ' . $e->getMessage());
        }
    }
}