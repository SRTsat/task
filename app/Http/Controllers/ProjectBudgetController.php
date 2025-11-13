<?php

namespace App\Http\Controllers;

use App\Models\ProjectBudget;
use App\Models\Project;
use App\Models\BudgetCategory;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Database\Seeders\BudgetCategorySeeder;

class ProjectBudgetController extends Controller
{
    use HasPermissionChecks;
    public function index(Request $request)
    {
        $this->authorizePermission('budget_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        $query = ProjectBudget::with(['project.creator', 'categories', 'creator'])
            ->where('workspace_id', $workspace->id);
            
        // Managers and clients only see budgets from their assigned projects
        if (in_array($userWorkspaceRole, ['manager', 'client'])) {
            $query->whereHas('project', function($q) use ($user) {
                $q->where(function($projectQuery) use ($user) {
                    $projectQuery->whereHas('members', function($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id);
                    })
                    ->orWhereHas('clients', function($clientQuery) use ($user) {
                        $clientQuery->where('user_id', $user->id);
                    })
                    ->orWhere('created_by', $user->id);
                });
            });
        }

        if ($request->search) {
            $query->whereHas('project', function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        $perPage = $request->get('per_page', 12);
        $budgets = $query->latest()->paginate($perPage);
        
        // Add budget utilization data
        $budgets->getCollection()->transform(function ($budget) {
            $budget->total_spent = $budget->total_spent;
            $budget->remaining_budget = $budget->remaining_budget;
            $budget->utilization_percentage = $budget->utilization_percentage;
            return $budget;
        });
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Get projects without budgets for the workspace with access control
        $projectsQuery = Project::forWorkspace($workspace->id)
            ->whereDoesntHave('budget')
            ->orderBy('title');
            
        // Managers and clients only see their assigned projects
        if (in_array($userWorkspaceRole, ['manager', 'client'])) {
            $projectsQuery->where(function($q) use ($user) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })
                ->orWhereHas('clients', function($clientQuery) use ($user) {
                    $clientQuery->where('user_id', $user->id);
                })
                ->orWhere('created_by', $user->id);
            });
        }
        
        $projects = $projectsQuery->get(['id', 'title']);

        // Get all projects for filtering with access control
        $allProjectsQuery = Project::forWorkspace($workspace->id)
            ->orderBy('title');
            
        // Managers and clients only see their assigned projects
        if (in_array($userWorkspaceRole, ['manager', 'client'])) {
            $allProjectsQuery->where(function($q) use ($user) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })
                ->orWhereHas('clients', function($clientQuery) use ($user) {
                    $clientQuery->where('user_id', $user->id);
                })
                ->orWhere('created_by', $user->id);
            });
        }
        
        $allProjects = $allProjectsQuery->get(['id', 'title']);

        // Get available currencies from database
        $currencies = \App\Models\Currency::orderBy('name')->get(['code', 'name', 'symbol']);

        return Inertia::render('budgets/Index', [
            'budgets' => $budgets,
            'projects' => $projects,
            'allProjects' => $allProjects,
            'currencies' => $currencies,
            'workspace' => $workspace,
            'filters' => $request->only(['search', 'status', 'project_id', 'per_page']),
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => [
                'create' => $this->checkPermission('budget_create'),
                'update' => $this->checkPermission('budget_update'),
                'delete' => $this->checkPermission('budget_delete'),
                'view' => $this->checkPermission('budget_view'),
            ]
        ]);
    }

    public function show(ProjectBudget $budget)
    {
        $this->authorizePermission('budget_view');
        
        $budget->load([
            'project.creator', 
            'project.members.user',
            'categories', 
            'expenses.submitter', 
            'revisions.revisor'
        ]);
        
        // Add computed attributes for budget
        $budget->total_spent = $budget->total_spent;
        $budget->remaining_budget = $budget->remaining_budget;
        $budget->utilization_percentage = $budget->utilization_percentage;
        
        // Add computed attributes for each category
        $budget->categories->transform(function ($category) {
            $category->total_spent = $category->total_spent;
            $category->remaining_amount = $category->remaining_amount;
            $category->utilization_percentage = $category->utilization_percentage;
            $category->is_over_budget = $category->is_over_budget;
            return $category;
        });
        
        return Inertia::render('budgets/Show', [
            'budget' => $budget,
            'permissions' => [
                'update' => $this->checkPermission('budget_update'),
                'delete' => $this->checkPermission('budget_delete'),
            ]
        ]);
    }



    public function store(Request $request)
    {
        $this->authorizePermission('budget_create');
        
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'total_budget' => 'required|numeric|min:0',
            'period_type' => 'required|in:project,monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date|after_or_equal:today',
            'description' => 'nullable|string',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.allocated_amount' => 'required|numeric|min:0',
            'categories.*.color' => 'nullable|string',
            'categories.*.description' => 'nullable|string',
            'categories.*.sort_order' => 'nullable|integer|min:1'
        ]);

        $user = auth()->user();
        $project = Project::findOrFail($validated['project_id']);

        // Check if budget already exists for this project
        $existingBudget = ProjectBudget::where('project_id', $validated['project_id'])->first();

        if ($existingBudget) {
            return redirect()->route('budgets.show', $existingBudget->id)
                ->with('info', __('Project already has a budget. Redirected to existing budget.'));
        }

        try {
            $budget = ProjectBudget::create([
                'project_id' => $validated['project_id'],
                'workspace_id' => $project->workspace_id,
                'total_budget' => $validated['total_budget'],
                'period_type' => $validated['period_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'description' => $validated['description'],
                'created_by' => $user->id
            ]);

            // Create budget categories
            foreach ($validated['categories'] as $index => $category) {
                BudgetCategory::create([
                    'project_budget_id' => $budget->id,
                    'name' => $category['name'],
                    'allocated_amount' => $category['allocated_amount'],
                    'color' => $category['color'] ?? '#3B82F6',
                    'description' => $category['description'] ?? '',
                    'sort_order' => $category['sort_order'] ?? ($index + 1)
                ]);
            }

            return redirect()->route('budgets.index')->with('success', __('Budget created successfully!'));
        } catch (\Exception $e) {
            \Log::error('Budget creation failed: ' . $e->getMessage());
            return back()->with('error', __('Failed to create budget: ') . $e->getMessage());
        }
    }

    public function update(Request $request, ProjectBudget $budget)
    {
        $this->authorizePermission('budget_update');
        
        $validated = $request->validate([
            'total_budget' => 'required|numeric|min:0',
            'period_type' => 'required|in:project,monthly,quarterly,yearly',
            'description' => 'nullable|string',
            'status' => 'required|in:active,completed,cancelled',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.allocated_amount' => 'required|numeric|min:0',
            'categories.*.color' => 'nullable|string',
            'categories.*.description' => 'nullable|string'
        ]);

        $budget->update([
            'total_budget' => $validated['total_budget'],
            'period_type' => $validated['period_type'],
            'description' => $validated['description'],
            'status' => $validated['status']
        ]);

        // Update categories
        $budget->categories()->delete();
        foreach ($validated['categories'] as $index => $category) {
            $budget->categories()->create([
                'name' => $category['name'],
                'allocated_amount' => $category['allocated_amount'],
                'color' => $category['color'] ?? '#3B82F6',
                'description' => $category['description'] ?? '',
                'sort_order' => $index + 1
            ]);
        }

        return back()->with('success', __('Budget updated successfully!'));
    }

    public function destroy(ProjectBudget $budget)
    {
        $this->authorizePermission('budget_delete');
        
        $budget->delete();
        return back()->with('success', __('Budget deleted successfully!'));
    }

    public function getDefaultCategories()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        // Get default categories with workspace context
        $defaultCategories = BudgetCategorySeeder::getDefaultCategories();
        
        // Add workspace-specific customizations if needed
        $categories = collect($defaultCategories)->map(function ($category) {
            return [
                'name' => $category['name'],
                'color' => $category['color'],
                'description' => $category['description'],
                'allocated_amount' => 0 // Will be calculated when adding
            ];
        })->toArray();
        
        return response()->json([
            'categories' => $categories,
            'workspace_id' => $workspace->id
        ]);
    }


}