<?php

namespace App\Http\Controllers;

use App\Models\ExpenseApproval;
use App\Models\ProjectExpense;
use App\Services\BudgetService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ExpenseApprovalController extends Controller
{
    use HasPermissionChecks;
    
    protected BudgetService $budgetService;
    
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }
    public function approve(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_approve');
        
        $validated = $request->validate([
            'notes' => 'nullable|string'
        ]);

        DB::transaction(function () use ($expense, $validated) {
            // Create or update approval record
            ExpenseApproval::updateOrCreate(
                [
                    'project_expense_id' => $expense->id,
                    'approver_id' => auth()->id()
                ],
                [
                    'status' => 'approved',
                    'notes' => $validated['notes'],
                    'approved_at' => now(),
                    'approval_level' => 1
                ]
            );

            // Update expense status
            $expense->update(['status' => 'approved']);

            // Update budget after approval
            $this->budgetService->updateBudgetAfterApproval($expense);
        });

        return back()->with('success', __('Expense approved and budget updated successfully!'));
    }

    public function reject(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_reject');
        
        try {
            
            $validated = $request->validate([
                'notes' => 'nullable|string'
            ]);

            DB::transaction(function () use ($expense, $validated) {
                // Create or update approval record
                ExpenseApproval::updateOrCreate(
                    [
                        'project_expense_id' => $expense->id,
                        'approver_id' => auth()->id()
                    ],
                    [
                        'status' => 'rejected',
                        'notes' => $validated['notes'] ?? 'Expense rejected',
                        'approved_at' => now(),
                        'approval_level' => 1
                    ]
                );

                // Update expense status
                $expense->update(['status' => 'rejected']);
            });

            return back()->with('success', __('Expense rejected successfully!'));
        } catch (\Exception $e) {
            \Log::error('Failed to reject expense: ' . $e->getMessage(), [
                'expense_id' => $expense->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return back()->with('error', __('Failed to reject expense. Please try again.'));
        }
    }

    public function requestInfo(Request $request, ProjectExpense $expense)
    {
        $this->authorizePermission('expense_request_info');
        
        $validated = $request->validate([
            'notes' => 'required|string'
        ]);

        // Create or update approval record
        $approval = ExpenseApproval::updateOrCreate(
            [
                'project_expense_id' => $expense->id,
                'approver_id' => auth()->id()
            ],
            [
                'status' => 'requires_info',
                'notes' => $validated['notes'],
                'approved_at' => now(),
                'approval_level' => 1
            ]
        );

        // Update expense status
        $expense->update(['status' => 'requires_info']);

        return back()->with('success', __('Additional information requested successfully!'));
    }

    public function bulkApprove(Request $request)
    {
        $this->authorizePermission('expense_approve');
        
        $validated = $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:project_expenses,id',
            'notes' => 'nullable|string'
        ]);

        $expenses = ProjectExpense::whereIn('id', $validated['expense_ids'])
            ->where('status', 'pending')
            ->get();

        DB::transaction(function () use ($expenses, $validated) {
            foreach ($expenses as $expense) {
                // Create approval record
                ExpenseApproval::create([
                    'project_expense_id' => $expense->id,
                    'approver_id' => auth()->id(),
                    'status' => 'approved',
                    'notes' => $validated['notes'],
                    'approved_at' => now(),
                    'approval_level' => 1
                ]);

                // Update expense status
                $expense->update(['status' => 'approved']);

                // Update budget after approval
                $this->budgetService->updateBudgetAfterApproval($expense);
            }
        });

        return back()->with('success', __(count($expenses) . ' expenses approved and budgets updated successfully!'));
    }

    public function index(Request $request)
    {
        $this->authorizePermission('expense_view_approvals');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = ProjectExpense::with(['project:id,title', 'budgetCategory:id,name,color', 'submitter:id,name,avatar'])
            ->whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->whereIn('status', ['pending', 'requires_info']);
            
        // Apply filters
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->project_id && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('vendor', 'like', '%' . $request->search . '%')
                  ->orWhereHas('submitter', function($subQ) use ($request) {
                      $subQ->where('name', 'like', '%' . $request->search . '%');
                  })
                  ->orWhereHas('project', function($projQ) use ($request) {
                      $projQ->where('title', 'like', '%' . $request->search . '%');
                  });
            });
        }
        
        $perPage = $request->get('per_page', 20);
        $pendingExpenses = $query->latest('created_at')->paginate($perPage);
        
        // Get filter options
        $projects = \App\Models\Project::forWorkspace($workspace->id)
            ->select('id', 'title')
            ->get();
        
        // Get overview stats
        $stats = [
            'pending_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'pending')->count(),
            
            'requires_info_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'requires_info')->count(),
            
            'approved_today' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'approved')->whereDate('updated_at', today())->count(),
            
            'pending_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'pending')->sum('amount')
        ];

        return Inertia::render('expenses/Approvals', [
            'expenses' => $pendingExpenses,
            'stats' => $stats,
            'projects' => $projects,
            'filters' => $request->only(['status', 'project_id', 'search', 'per_page']),
            'permissions' => [
                'approve' => $this->checkPermission('expense_approve'),
                'reject' => $this->checkPermission('expense_reject'),
                'request_info' => $this->checkPermission('expense_request_info'),
            ]
        ]);
    }

    public function pendingApprovals()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $pendingExpenses = ProjectExpense::with(['project', 'budgetCategory', 'submitter'])
            ->whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return response()->json([
            'expenses' => $pendingExpenses
        ]);
    }

    /**
     * Get budget summary for a project
     */
    public function getBudgetSummary(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id'
        ]);
        
        $summary = $this->budgetService->getProjectBudgetSummary($validated['project_id']);
        
        return response()->json($summary);
    }

    /**
     * Get expense approval statistics
     */
    public function getApprovalStats()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $stats = [
            'pending_count' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'pending')->count(),
            
            'approved_today' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'approved')
              ->whereDate('updated_at', today())->count(),
              
            'total_approved_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->approved()->sum('amount'),
            
            'pending_amount' => ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'pending')->sum('amount')
        ];
        
        return response()->json($stats);
    }
}