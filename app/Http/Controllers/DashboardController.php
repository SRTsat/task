<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use HasPermissionChecks;
    
    public function __construct(private PermissionService $permissionService)
    {
    }
    public function index()
    {
        $user = auth()->user();
        
        // Super admin gets their own dashboard
        if ($user->type === 'superadmin' || $user->type === 'super admin') {
            return $this->renderSuperAdminDashboard();
        }
        
        // Check if user has dashboard permission or is a company user
        if ($this->checkPermission('dashboard_view') || $user->type === 'company') {
            return $this->renderDashboard();
        }
        
        // Redirect to first available page
        return $this->redirectToFirstAvailablePage();
    }
    
    public function redirectToFirstAvailablePage()
    {
        $user = auth()->user();
        
        // Define available routes with their permissions
        $routes = [
            ['route' => 'projects.index', 'permission' => 'project_view_any'],
            ['route' => 'tasks.index', 'permission' => 'task_view_any'],
            ['route' => 'timesheets.index', 'permission' => 'timesheet_view_any'],
            ['route' => 'expenses.index', 'permission' => 'expense_view_any'],
            ['route' => 'budgets.index', 'permission' => 'budget_view_any'],
            ['route' => 'invoices.index', 'permission' => 'invoice_view_any'],
            ['route' => 'bugs.index', 'permission' => 'bug_view_any'],
            ['route' => 'workspaces.index', 'permission' => 'workspace_view_any'],
            ['route' => 'plans.index', 'permission' => 'plan_view_any'],
            ['route' => 'companies.index', 'permission' => 'company_view_any'],
            ['route' => 'users.index', 'permission' => 'user_view_any'],
        ];
        
        // Find first available route
        foreach ($routes as $routeData) {
            if ($this->checkPermission($routeData['permission'])) {
                return redirect()->route($routeData['route']);
            }
        }
        
        // If no permissions found, logout user
        auth()->logout();
        return redirect()->route('login')->with('error', __('No access permissions found.'));
    }
    
    private function renderDashboard()
    {
        $user = auth()->user();
        
        // Get current workspace - try multiple methods
        $workspace = $this->getCurrentWorkspace($user);
        
        // Get comprehensive dashboard data
        $dashboardData = [
            'cards' => [
                [
                    'title' => __('Total Users'),
                    'value' => $this->getTotalUsers($user),
                    'icon' => 'Users',
                ],
                [
                    'title' => __('Active Projects'),
                    'value' => $this->getActiveProjects($workspace),
                    'icon' => 'Activity',
                ],
                [
                    'title' => __('Tasks Completed'),
                    'value' => $this->getCompletedTasks($workspace),
                    'icon' => 'UserPlus',
                ],
                [
                    'title' => __('Revenue'),
                    'value' => $this->getRevenue($user),
                    'format' => 'currency',
                    'icon' => 'DollarSign',
                ]
            ],
            'projects' => $this->getProjectStats($workspace),
            'tasks' => $this->getTaskStats($workspace),
            'taskStages' => $this->getTaskStages($workspace),
            'timesheets' => $this->getTimesheetStats($workspace),
            'budgets' => $this->getBudgetStats($workspace),
            'expenses' => $this->getExpenseStats($workspace),
            'invoices' => $this->getInvoiceStats($workspace),
            'bugs' => $this->getBugStats($workspace),
            'recentActivities' => $this->getRecentActivities($workspace),
            'currentWorkspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'role' => $this->getUserWorkspaceRole($user, $workspace)
            ] : null
        ];

        return Inertia::render('dashboard', [
            'dashboardData' => $dashboardData,
            'permissions' => $this->permissionService->getDashboardPermissions($user)
        ]);
    }
    
    private function getCurrentWorkspace($user)
    {
        try {
            // Try to get current workspace from session or user preference
            if (session('current_workspace_id')) {
                $workspace = \App\Models\Workspace::find(session('current_workspace_id'));
                if ($workspace && $user->workspaces()->where('workspace_id', $workspace->id)->exists()) {
                    return $workspace;
                }
            }
            
            // Try user's currentWorkspace relationship if it exists
            if (method_exists($user, 'currentWorkspace') && $user->currentWorkspace) {
                return $user->currentWorkspace;
            }
            
            // Get first workspace user belongs to
            return $user->workspaces()->first();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getUserWorkspaceRole($user, $workspace)
    {
        try {
            if (!$workspace) return null;
            
            $member = \App\Models\WorkspaceMember::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->first();
                
            return $member ? $member->role : 'member';
        } catch (\Exception $e) {
            return 'member';
        }
    }
    
    private function getTotalUsers($user)
    {
        try {
            // Super admin sees all users
            if ($user->type === 'superadmin' || $user->type === 'super admin') {
                if (class_exists('\App\Models\User')) {
                    return \App\Models\User::count();
                }
                return 1;
            }
            
            // Regular users see workspace members
            $workspace = $this->getCurrentWorkspace($user);
            if ($workspace && class_exists('\App\Models\WorkspaceMember')) {
                return \App\Models\WorkspaceMember::where('workspace_id', $workspace->id)->count();
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getActiveProjects($workspace)
    {
        try {
            if (!class_exists('\App\Models\Project')) {
                return 0;
            }
            
            if (!$workspace) {
                return 0;
            }
            
            return \App\Models\Project::where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getCompletedTasks($workspace)
    {
        try {
            if (!class_exists('\App\Models\Task') || !class_exists('\App\Models\TaskStage')) {
                return 0;
            }
            
            if (!$workspace) {
                return 0;
            }
            
            $completedStages = \App\Models\TaskStage::where('workspace_id', $workspace->id)
                ->where(function($q) {
                    $q->where('name', 'like', '%done%')
                      ->orWhere('name', 'like', '%completed%')
                      ->orWhere('name', 'like', '%finished%');
                })->pluck('id');
                
            return \App\Models\Task::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->whereIn('task_stage_id', $completedStages)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    

    
    private function getRevenue($user)
    {
        try {
            // Super admin sees all revenue
            if ($user->type === 'superadmin') {
                if (class_exists('\App\Models\PlanOrder')) {
                    return \App\Models\PlanOrder::whereIn('status', ['approved', 'completed', 'paid'])
                        ->sum('final_price') ?? 0;
                }
                return 0;
            }
            
            // Regular users see workspace invoice revenue
            $workspace = $this->getCurrentWorkspace($user);
            if ($workspace && class_exists('\App\Models\Invoice')) {
                return \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                })->where('status', 'paid')
                ->where('created_at', '>=', now()->subDays(30))
                ->sum('total') ?? 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getProjectStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\Project')) {
                return ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
            }
            
            if (!$workspace) {
                $total = \App\Models\Project::count();
                $active = \App\Models\Project::where('status', 'active')->count();
                $completed = \App\Models\Project::where('status', 'completed')->count();
                $overdue = \App\Models\Project::where('deadline', '<', now()->toDateString())
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->count();
                    
                return [
                    'total' => $total,
                    'active' => $active,
                    'completed' => $completed,
                    'overdue' => $overdue
                ];
            }
            
            $total = \App\Models\Project::where('workspace_id', $workspace->id)->count();
            $active = \App\Models\Project::where('workspace_id', $workspace->id)
                ->where('status', 'active')->count();
            $completed = \App\Models\Project::where('workspace_id', $workspace->id)
                ->where('status', 'completed')->count();
            $overdue = \App\Models\Project::where('workspace_id', $workspace->id)
                ->where('deadline', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
                
            return [
                'total' => $total,
                'active' => $active,
                'completed' => $completed,
                'overdue' => $overdue
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
        }
    }
    
    private function getTaskStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\Task')) {
                return ['total' => 0, 'pending' => 0, 'inProgress' => 0, 'completed' => 0];
            }
            
            if (!$workspace) {
                $stages = \App\Models\TaskStage::withCount('tasks')->get();
                $total = \App\Models\Task::count();
                $pending = $stages->first() ? $stages->first()->tasks_count : 0;
                $inProgress = $stages->skip(1)->first() ? $stages->skip(1)->first()->tasks_count : 0;
                $completed = $stages->skip(2)->first() ? $stages->skip(2)->first()->tasks_count : 0;
                
                return [
                    'total' => $total,
                    'pending' => $pending,
                    'inProgress' => $inProgress,
                    'completed' => $completed
                ];
            }
            
            $total = \App\Models\Task::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->count();
            
            $stages = \App\Models\TaskStage::withCount(['tasks' => function($q) use ($workspace) {
                $q->whereHas('project', function($pq) use ($workspace) {
                    $pq->where('workspace_id', $workspace->id);
                });
            }])->get();
            
            $pending = $stages->first() ? $stages->first()->tasks_count : 0;
            $inProgress = $stages->skip(1)->first() ? $stages->skip(1)->first()->tasks_count : 0;
            $completed = $stages->skip(2)->first() ? $stages->skip(2)->first()->tasks_count : 0;
            
            return [
                'total' => $total,
                'pending' => $pending,
                'inProgress' => $inProgress,
                'completed' => $completed
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'pending' => 0, 'inProgress' => 0, 'completed' => 0];
        }
    }
    
    private function getTaskStages($workspace)
    {
        try {
            if (!class_exists('\App\Models\TaskStage')) {
                return [];
            }
            
            if (!$workspace) {
                $stages = \App\Models\TaskStage::withCount('tasks')->get();
            } else {
                $stages = \App\Models\TaskStage::withCount(['tasks' => function($q) use ($workspace) {
                    $q->whereHas('project', function($pq) use ($workspace) {
                        $pq->where('workspace_id', $workspace->id);
                    });
                }])->get();
            }
            
            // Group by name and sum counts
            $grouped = $stages->groupBy('name')->map(function($group) {
                return [
                    'name' => $group->first()->name,
                    'count' => $group->sum('tasks_count')
                ];
            })->values()->toArray();
            
            return $grouped;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getTimesheetStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\TimesheetEntry') || !class_exists('\App\Models\Timesheet')) {
                return ['totalHours' => 0, 'thisWeek' => 0, 'pendingApprovals' => 0];
            }
            
            if (!$workspace) {
                $totalHours = \App\Models\TimesheetEntry::sum('hours') ?? 0;
                $thisWeek = \App\Models\TimesheetEntry::whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->sum('hours') ?? 0;
                $pendingApprovals = \App\Models\Timesheet::where('status', 'submitted')->count();
                
                return [
                    'totalHours' => (int)$totalHours,
                    'thisWeek' => (int)$thisWeek,
                    'pendingApprovals' => $pendingApprovals
                ];
            }
            
            $totalHours = \App\Models\TimesheetEntry::whereHas('timesheet.user', function($q) use ($workspace) {
                $q->whereHas('workspaces', function($wq) use ($workspace) {
                    $wq->where('workspace_id', $workspace->id);
                });
            })->sum('hours') ?? 0;
            
            $thisWeek = \App\Models\TimesheetEntry::whereHas('timesheet.user', function($q) use ($workspace) {
                $q->whereHas('workspaces', function($wq) use ($workspace) {
                    $wq->where('workspace_id', $workspace->id);
                });
            })->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('hours') ?? 0;
            
            $pendingApprovals = \App\Models\Timesheet::whereHas('user', function($q) use ($workspace) {
                $q->whereHas('workspaces', function($wq) use ($workspace) {
                    $wq->where('workspace_id', $workspace->id);
                });
            })->where('status', 'submitted')->count();
            
            return [
                'totalHours' => (int)$totalHours,
                'thisWeek' => (int)$thisWeek,
                'pendingApprovals' => $pendingApprovals
            ];
        } catch (\Exception $e) {
            return ['totalHours' => 0, 'thisWeek' => 0, 'pendingApprovals' => 0];
        }
    }
    
    private function getBudgetStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\ProjectBudget') || !class_exists('\App\Models\ProjectExpense')) {
                return ['totalBudget' => 0, 'spent' => 0, 'remaining' => 0, 'utilization' => 0];
            }
            
            if (!$workspace) {
                $totalBudget = \App\Models\ProjectBudget::sum('total_budget') ?? 0;
                $spent = \App\Models\ProjectExpense::where('status', 'approved')->sum('amount') ?? 0;
                $remaining = $totalBudget - $spent;
                $utilization = $totalBudget > 0 ? ($spent / $totalBudget) * 100 : 0;
                
                return [
                    'totalBudget' => (int)$totalBudget,
                    'spent' => (int)$spent,
                    'remaining' => (int)$remaining,
                    'utilization' => round($utilization, 1)
                ];
            }
            
            $totalBudget = \App\Models\ProjectBudget::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->sum('total_budget') ?? 0;
            
            $spent = \App\Models\ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'approved')->sum('amount') ?? 0;
            
            $remaining = $totalBudget - $spent;
            $utilization = $totalBudget > 0 ? ($spent / $totalBudget) * 100 : 0;
            
            return [
                'totalBudget' => (int)$totalBudget,
                'spent' => (int)$spent,
                'remaining' => (int)$remaining,
                'utilization' => round($utilization, 1)
            ];
        } catch (\Exception $e) {
            return ['totalBudget' => 0, 'spent' => 0, 'remaining' => 0, 'utilization' => 0];
        }
    }
    
    private function getInvoiceStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\Invoice')) {
                return ['total' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0];
            }
            
            if (!$workspace) {
                $total = \App\Models\Invoice::count();
                $paid = \App\Models\Invoice::where('status', 'paid')->count();
                $pending = \App\Models\Invoice::whereIn('status', ['draft', 'sent', 'viewed'])->count();
                $overdue = \App\Models\Invoice::where('due_date', '<', now())
                    ->where('status', '!=', 'paid')->count();
                
                return [
                    'total' => $total,
                    'paid' => $paid,
                    'pending' => $pending,
                    'overdue' => $overdue
                ];
            }
            
            $total = \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->count();
            
            $paid = \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'paid')->count();
            
            $pending = \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->whereIn('status', ['draft', 'sent', 'viewed'])->count();
            
            $overdue = \App\Models\Invoice::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('due_date', '<', now())
            ->where('status', '!=', 'paid')->count();
            
            return [
                'total' => $total,
                'paid' => $paid,
                'pending' => $pending,
                'overdue' => $overdue
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0];
        }
    }
    
    private function getBugStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\Bug') || !class_exists('\App\Models\BugStatus')) {
                return [];
            }
            
            if (!$workspace) {
                $statuses = \App\Models\BugStatus::withCount('bugs')->take(6)->get();
            } else {
                $statuses = \App\Models\BugStatus::where('workspace_id', $workspace->id)
                    ->withCount('bugs')
                    ->take(6)
                    ->get();
            }
            
            return $statuses->map(function($status) {
                return [
                    'name' => $status->name,
                    'count' => $status->bugs_count
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getExpenseStats($workspace)
    {
        try {
            if (!class_exists('\App\Models\ProjectExpense')) {
                return ['pending' => 0, 'approved' => 0, 'total' => 0];
            }
            
            if (!$workspace) {
                $total = \App\Models\ProjectExpense::count();
                $pending = \App\Models\ProjectExpense::where('status', 'pending')->count();
                $approved = \App\Models\ProjectExpense::where('status', 'approved')->count();
                
                return [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved
                ];
            }
            
            $total = \App\Models\ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->count();
            
            $pending = \App\Models\ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'pending')->count();
            
            $approved = \App\Models\ProjectExpense::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->where('status', 'approved')->count();
            
            return [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved
            ];
        } catch (\Exception $e) {
            return ['pending' => 0, 'approved' => 0, 'total' => 0];
        }
    }
    
    private function getRecentActivities($workspace)
    {
        try {
            if (!$workspace) {
                return $this->getDefaultActivities();
            }
            
            $activities = \App\Models\ProjectActivity::whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type ?? 'activity',
                    'description' => $activity->description,
                    'user' => $activity->user->name ?? 'Unknown User',
                    'time' => $activity->created_at->diffForHumans()
                ];
            });
            
            // If no activities found, return default activities
            if ($activities->isEmpty()) {
                return $this->getDefaultActivities();
            }
            
            return $activities->toArray();
        } catch (\Exception $e) {
            return $this->getDefaultActivities();
        }
    }
    
    private function getDefaultActivities()
    {
        return [
            [
                'id' => 1, 
                'type' => 'task', 
                'description' => 'Task "API Integration for Payment Gateway" completed successfully', 
                'user' => 'John Doe', 
                'time' => '2 hours ago'
            ],
            [
                'id' => 2, 
                'type' => 'project', 
                'description' => 'New project "E-commerce Mobile App" created with initial setup', 
                'user' => 'Jane Smith', 
                'time' => '3 hours ago'
            ],
            [
                'id' => 3, 
                'type' => 'expense', 
                'description' => 'Travel expense of $450 approved for client meeting', 
                'user' => 'Mike Johnson', 
                'time' => '4 hours ago'
            ],
            [
                'id' => 4, 
                'type' => 'bug', 
                'description' => 'Critical security bug fixed in user authentication system', 
                'user' => 'Sarah Wilson', 
                'time' => '5 hours ago'
            ],
            [
                'id' => 5, 
                'type' => 'invoice', 
                'description' => 'Invoice #INV-2024-001 sent to client for $2,500', 
                'user' => 'David Brown', 
                'time' => '6 hours ago'
            ],
            [
                'id' => 6, 
                'type' => 'timesheet', 
                'description' => 'Weekly timesheet submitted for approval (40 hours)', 
                'user' => 'Emily Davis', 
                'time' => '8 hours ago'
            ],
            [
                'id' => 7, 
                'type' => 'project', 
                'description' => 'Project "Website Redesign" milestone completed ahead of schedule', 
                'user' => 'Alex Chen', 
                'time' => '10 hours ago'
            ],
            [
                'id' => 8, 
                'type' => 'task', 
                'description' => 'Database optimization task assigned to development team', 
                'user' => 'Lisa Wang', 
                'time' => '12 hours ago'
            ],
            [
                'id' => 9, 
                'type' => 'expense', 
                'description' => 'Software license renewal expense of $199 submitted', 
                'user' => 'Tom Anderson', 
                'time' => '1 day ago'
            ],
            [
                'id' => 10, 
                'type' => 'bug', 
                'description' => 'UI responsiveness issue reported on mobile devices', 
                'user' => 'Rachel Green', 
                'time' => '1 day ago'
            ]
        ];
    }
    
    private function renderSuperAdminDashboard()
    {
        // Companies (Users) Statistics
        $totalCompanies = \App\Models\User::where('type', 'company')->count();
        $activeCompanies = \App\Models\User::where('type', 'company')->where('is_enable_login', 1)->count();
        $inactiveCompanies = $totalCompanies - $activeCompanies;

        // Plans Statistics
        $totalPlans = class_exists('\App\Models\Plan') ? \App\Models\Plan::count() : 0;
        $activePlans = class_exists('\App\Models\Plan') ? \App\Models\Plan::count() : 0;
        $inactivePlans = $totalPlans - $activePlans;

        // Plan Orders Statistics
        $totalOrders = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::count() : 0;
        $pendingOrders = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::where('status', 'pending')->count() : 0;
        $approvedOrders = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::where('status', 'approved')->count() : 0;
        $rejectedOrders = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::where('status', 'rejected')->count() : 0;

        // Plan Requests Statistics
        $totalRequests = class_exists('\App\Models\PlanRequest') ? \App\Models\PlanRequest::count() : 0;
        $pendingRequests = class_exists('\App\Models\PlanRequest') ? \App\Models\PlanRequest::where('status', 'pending')->count() : 0;
        $approvedRequests = class_exists('\App\Models\PlanRequest') ? \App\Models\PlanRequest::where('status', 'approved')->count() : 0;
        $rejectedRequests = class_exists('\App\Models\PlanRequest') ? \App\Models\PlanRequest::where('status', 'rejected')->count() : 0;

        // Coupons Statistics
        $totalCoupons = class_exists('\App\Models\Coupon') ? \App\Models\Coupon::count() : 0;
        $activeCoupons = class_exists('\App\Models\Coupon') ? \App\Models\Coupon::where('status', 1)->count() : 0;
        $expiredCoupons = class_exists('\App\Models\Coupon') ? \App\Models\Coupon::where('expiry_date', '<', now())->count() : 0;

        // Revenue Statistics
        $totalRevenue = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::where('status', 'approved')->sum('final_price') : 0;
        $monthlyRevenue = class_exists('\App\Models\PlanOrder') ? \App\Models\PlanOrder::where('status', 'approved')
            ->whereMonth('created_at', now()->month)
            ->sum('final_price') : 0;

        // Most Bought Plan
        $mostBoughtPlan = $this->getMostBoughtPlan();

        // Most Used Coupon
        $mostUsedCoupon = $this->getMostUsedCoupon();

        // Recent Activities
        $recentActivities = $this->getSuperAdminRecentActivities();

        $dashboardData = [
            'cards' => [
                ['value' => $totalCompanies],
                ['value' => $totalPlans],
                ['value' => $totalOrders],
                ['value' => $totalRevenue]
            ],
            'companies' => [
                'total' => $totalCompanies,
                'active' => $activeCompanies,
                'inactive' => $inactiveCompanies
            ],
            'plans' => [
                'total' => $totalPlans,
                'active' => $activePlans,
                'inactive' => $inactivePlans
            ],
            'planOrders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'approved' => $approvedOrders,
                'rejected' => $rejectedOrders
            ],
            'planRequests' => [
                'total' => $totalRequests,
                'pending' => $pendingRequests,
                'approved' => $approvedRequests,
                'rejected' => $rejectedRequests
            ],
            'coupons' => [
                'total' => $totalCoupons,
                'active' => $activeCoupons,
                'expired' => $expiredCoupons
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'monthly' => $monthlyRevenue
            ],
            'mostBoughtPlan' => $mostBoughtPlan,
            'mostUsedCoupon' => $mostUsedCoupon,
            'recentActivities' => $recentActivities
        ];

        return Inertia::render('dashboard', [
            'dashboardData' => $dashboardData,
            'isSuperAdmin' => true,
            'permissions' => $this->permissionService->getDashboardPermissions(auth()->user())
        ]);
    }
    
    private function getSuperAdminRecentActivities()
    {
        try {
            $activities = collect();
            
            // Get recent plan orders
            if (class_exists('\App\Models\PlanOrder')) {
                $planOrders = \App\Models\PlanOrder::with('user', 'plan')->latest()->take(5)->get();
                foreach ($planOrders as $order) {
                    $activities->push([
                        'id' => $order->id,
                        'type' => 'plan_order',
                        'description' => "Plan order for {$order->plan->name}",
                        'user' => $order->user->name,
                        'time' => $order->created_at->diffForHumans(),
                        'status' => $order->status
                    ]);
                }
            }
            
            // Get recent plan requests
            if (class_exists('\App\Models\PlanRequest')) {
                $planRequests = \App\Models\PlanRequest::with('user', 'plan')->latest()->take(5)->get();
                foreach ($planRequests as $request) {
                    $activities->push([
                        'id' => $request->id,
                        'type' => 'plan_request',
                        'description' => "Plan request for {$request->plan->name}",
                        'user' => $request->user->name,
                        'time' => $request->created_at->diffForHumans(),
                        'status' => $request->status
                    ]);
                }
            }
            
            // Get recent company registrations
            $companies = \App\Models\User::where('type', 'company')->latest()->take(3)->get();
            foreach ($companies as $user) {
                $activities->push([
                    'id' => $user->id,
                    'type' => 'company_registration',
                    'description' => "New company registered",
                    'user' => $user->name,
                    'time' => $user->created_at->diffForHumans(),
                    'status' => 'active'
                ]);
            }
            
            return $activities->sortByDesc('time')->take(10)->values()->toArray();
        } catch (\Exception $e) {
            return [
                [
                    'id' => 1,
                    'type' => 'system',
                    'description' => 'Super Admin Dashboard initialized',
                    'user' => 'System',
                    'time' => '1 hour ago',
                    'status' => 'active'
                ]
            ];
        }
    }
    
    private function getMostBoughtPlan()
    {
        try {
            if (!class_exists('\App\Models\PlanOrder') || !class_exists('\App\Models\Plan')) {
                return null;
            }
            
            $planOrder = \App\Models\PlanOrder::select('plan_id', \DB::raw('COUNT(*) as order_count'))
                ->where('status', 'approved')
                ->groupBy('plan_id')
                ->orderBy('order_count', 'desc')
                ->with('plan')
                ->first();
                
            return $planOrder ? [
                'name' => $planOrder->plan->name ?? 'Unknown Plan',
                'count' => $planOrder->order_count
            ] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getMostUsedCoupon()
    {
        try {
            if (!class_exists('\App\Models\PlanOrder') || !class_exists('\App\Models\Coupon')) {
                return null;
            }
            
            $couponOrder = \App\Models\PlanOrder::select('coupon_id', \DB::raw('COUNT(*) as usage_count'))
                ->whereNotNull('coupon_id')
                ->groupBy('coupon_id')
                ->orderBy('usage_count', 'desc')
                ->with('coupon')
                ->first();
                
            return $couponOrder ? [
                'name' => $couponOrder->coupon->name ?? 'Unknown Coupon',
                'code' => $couponOrder->coupon->code ?? '',
                'count' => $couponOrder->usage_count
            ] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}