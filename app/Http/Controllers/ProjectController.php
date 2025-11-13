<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use HasPermissionChecks;
    public function __construct(private PlanLimitService $planLimitService)
    {
    }
    public function index(Request $request): Response
    {
        $this->authorizePermission('project_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        $query = Project::with(['workspace', 'clients', 'creator', 'members.user'])
            ->forWorkspace($user->current_workspace_id);
            
        // Access control based on workspace role
        if ($userWorkspaceRole === 'owner') {
            // Owner: Full access to all projects
        } else {
            // Non-owners: Only assigned projects
            $query->where(function($q) use ($user, $userWorkspaceRole) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })
                ->orWhereHas('clients', function($clientQuery) use ($user) {
                    $clientQuery->where('user_id', $user->id);
                });
                
                // Client/Member: Only self-created projects
                if (in_array($userWorkspaceRole, ['client', 'member'])) {
                    $q->orWhere('created_by', $user->id);
                }
            });
        }

        if ($request->search) $query->search($request->search);
        if ($request->status) $query->byStatus($request->status);
        if ($request->priority) $query->byPriority($request->priority);

        $perPage = in_array($request->get('per_page', 12), [12, 24, 48]) ? $request->get('per_page', 12) : 12;
        $projects = $query->latest()->paginate($perPage);

        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active')->where('role', 'member');
        })->get();
        
        $managers = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active')->where('role', 'manager');
        })->get();
        
        $clients = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active')->where('role', 'client');
        })->get();
        
        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'members' => $members,
            'managers' => $managers,
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'priority']),
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => $this->getModuleCrudPermissions('project')
        ]);
    }



    public function store(Request $request)
    {
        $this->authorizePermission('project_create');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }
        
        // Check plan limits before creating project
        $limitCheck = $this->planLimitService->canCreateProject($workspace);
        if (!$limitCheck['allowed']) {
            return back()->withErrors(['error' => $limitCheck['message']])->withInput();
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'array',
            'client_ids.*' => 'exists:users,id',
            'status' => 'required|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date|after:start_date',
            'estimated_hours' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'is_public' => 'boolean',
            'member_ids' => 'array',
            'member_ids.*' => 'exists:users,id'
        ]);
        
        $clientIds = $validated['client_ids'] ?? [];
        unset($validated['client_ids']);

        $project = Project::create([
            ...$validated,
            'workspace_id' => auth()->user()->current_workspace_id,
            'created_by' => auth()->id(),
            'budget' => $validated['budget'] ?? 0,
            'estimated_hours' => $validated['estimated_hours'] ?? 0
        ]);
        
        // Assign clients
        foreach ($clientIds as $clientId) {
            \App\Models\ProjectClient::create([
                'project_id' => $project->id,
                'user_id' => $clientId,
                'assigned_by' => auth()->id()
            ]);
        }

        // Assign members
        if (!empty($validated['member_ids'])) {
            foreach ($validated['member_ids'] as $userId) {
                ProjectMember::create([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'assigned_by' => auth()->id()
                ]);
            }
        }

        $project->logActivity('created', "Project '{$project->title}' was created");

        return redirect()->route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorizePermission('project_view');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace) {
            abort(404, 'No workspace found. Please select a workspace.');
        }
        
        // Ensure project belongs to current workspace
        if ($project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        if (!$userWorkspaceRole) {
            abort(403, 'You are not a member of this workspace.');
        }
        
        // Access control
        if ($userWorkspaceRole !== 'owner') {
            $hasAccess = $project->members()->where('user_id', $user->id)->exists() ||
                        $project->clients()->where('user_id', $user->id)->exists();
            
            // Client/Member: Can also see self-created projects
            if (in_array($userWorkspaceRole, ['client', 'member'])) {
                $hasAccess = $hasAccess || $project->created_by === $user->id;
            }
            
            if (!$hasAccess) abort(403);
        }
        
        $project->load([
            'workspace', 'clients', 'creator', 'members.user',
            'milestones',
            'expenses' => function($query) {
                $query->with('budgetCategory')->latest()->limit(5);
            }
        ]);
        
        // Handle attachments with pagination and search
        $attachmentsQuery = $project->attachments()->with(['mediaItem', 'uploadedBy']);
        
        if ($request->attachment_search) {
            $attachmentsQuery->where(function($query) use ($request) {
                $query->whereHas('mediaItem', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->attachment_search . '%');
                })
                ->orWhereHas('uploadedBy', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->attachment_search . '%');
                });
            });
        }
        
        $attachmentsPerPage = in_array($request->attachments_per_page, [6, 12, 24, 48]) ? $request->attachments_per_page : 12;
        $attachments = $attachmentsQuery->latest()->paginate($attachmentsPerPage, ['*'], 'attachments_page');
        
        $project->setRelation('attachments', $attachments);
        
        // Handle notes with pagination and search
        $notesQuery = $project->notes()->with('creator');
        
        if ($request->notes_search) {
            $notesQuery->where(function($query) use ($request) {
                $query->where('title', 'like', '%' . $request->notes_search . '%')
                      ->orWhere('content', 'like', '%' . $request->notes_search . '%');
            });
        }
        
        $notesPerPage = in_array($request->notes_per_page, [5, 10, 20, 50]) ? $request->notes_per_page : 5;
        $notes = $notesQuery->latest()->paginate($notesPerPage, ['*'], 'notes_page');
        
        $project->setRelation('notes', $notes);
        
        // Handle activities with pagination and search
        $activitiesQuery = $project->activities()->with('user');
        
        if ($request->activity_search) {
            $activitiesQuery->where('description', 'like', '%' . $request->activity_search . '%');
        }
        
        $activityPerPage = in_array($request->activity_per_page, [5, 10, 20, 50]) ? $request->activity_per_page : 10;
        $activities = $activitiesQuery->latest()->paginate($activityPerPage, ['*'], 'activity_page');
        
        $project->setRelation('activities', $activities);
        
        // Load project tasks with related data
        $projectTasks = \App\Models\Task::with(['taskStage', 'assignedTo', 'creator'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();
            
        // Load project bugs with related data
        $projectBugs = \App\Models\Bug::with(['bugStatus', 'assignedTo', 'reportedBy'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();
            
        // Load project timesheets with related data
        $projectTimesheets = \App\Models\Timesheet::with(['user', 'entries' => function($query) use ($project) {
                $query->whereHas('task', function($taskQuery) use ($project) {
                    $taskQuery->where('project_id', $project->id);
                });
            }])
            ->whereHas('entries.task', function($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->latest()
            ->get();
        
        // Load single budget for this project
        $budget = \App\Models\ProjectBudget::with(['categories', 'creator'])
            ->where('project_id', $project->id)
            ->first();
        
        // Add computed attributes to budget
        if ($budget) {
            $budget->total_spent = $budget->total_spent;
            $budget->remaining_budget = $budget->remaining_budget;
            $budget->utilization_percentage = $budget->utilization_percentage;
            
            // Load recent expenses for this budget
            $budget->expenses = \App\Models\ProjectExpense::with('submitter')
                ->where('project_id', $project->id)
                ->latest()
                ->limit(3)
                ->get();
        }
        
        // Get workspace members (users with member role in workspace)
        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active')
              ->where('role', 'member');
        })->get();
        
        // Get workspace managers (users with manager role in workspace)
        $managers = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active')
              ->where('role', 'manager');
        })->get();
        
        // Get clients (users with client role in workspace)
        $clients = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active')
              ->where('role', 'client');
        })->get();

        return Inertia::render('projects/Show', [
            'project' => $project,
            'budget' => $budget,
            'members' => $members,
            'managers' => $managers,
            'clients' => $clients,
            'projectTasks' => $projectTasks,
            'projectBugs' => $projectBugs,
            'projectTimesheets' => $projectTimesheets,
            'userWorkspaceRole' => $userWorkspaceRole,
            'canManageBudget' => $this->checkPermission('project_manage_budget'),
            'canDeleteProject' => $this->checkPermission('project_delete'),
            'canViewBudget' => $this->checkPermission('project_manage_budget'),
            'canManageMembers' => $this->checkPermission('project_assign_members'),
            'canManageClients' => $this->checkPermission('project_assign_clients'),
            'canManageAttachments' => $this->checkPermission('project_manage_attachments'),
            'canManageNotes' => $this->checkPermission('project_manage_notes'),
            'canTrackProgress' => $this->checkPermission('project_track_progress'),
            'attachmentFilters' => $request->only(['attachment_search', 'attachments_per_page']),
            'noteFilters' => $request->only(['notes_search', 'notes_per_page']),
            'activityFilters' => $request->only(['activity_search', 'activity_per_page'])
        ]);
    }



    public function update(Request $request, Project $project)
    {
        $this->authorizePermission('project_update');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_ids' => 'array',
            'client_ids.*' => 'exists:users,id',
            'status' => 'required|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date|after:start_date',
            'estimated_hours' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'is_public' => 'boolean',
            'member_ids' => 'array',
            'member_ids.*' => 'exists:users,id'
        ]);
        
        $clientIds = $validated['client_ids'] ?? [];
        unset($validated['client_ids']);

        $project->update([
            ...$validated,
            'updated_by' => auth()->id()
        ]);
        
        // Update clients
        $project->projectClients()->delete();
        foreach ($clientIds as $clientId) {
            \App\Models\ProjectClient::create([
                'project_id' => $project->id,
                'user_id' => $clientId,
                'assigned_by' => auth()->id()
            ]);
        }

        // Update members
        $project->members()->delete();
        if (!empty($validated['member_ids'])) {
            foreach ($validated['member_ids'] as $userId) {
                ProjectMember::create([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'assigned_by' => auth()->id()
                ]);
            }
        }

        $project->logActivity('updated', "Project '{$project->title}' was updated");

        return redirect()->route('projects.show', $project);
    }

    public function destroy(Project $project)
    {
        $this->authorizePermission('project_delete');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        if (!$userWorkspaceRole) {
            abort(403, 'You are not a member of this workspace.');
        }
        
        // Only owner and managers can delete projects
        if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
            abort(403);
        }
        
        // Managers cannot delete projects
        if ($userWorkspaceRole === 'manager') {
            abort(403);
        }
        
        $projectTitle = $project->title;
        $project->logActivity('deleted', "Project '{$projectTitle}' deleted");
        $project->delete();
        
        return redirect()->route('projects.index');
    }

    public function createBudget(Request $request, Project $project)
    {
        $this->authorizePermission('project_manage_budget');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        if (!$userWorkspaceRole) {
            abort(403, 'You are not a member of this workspace.');
        }
        
        // Only owner and managers can create/manage budgets
        if (!in_array($userWorkspaceRole, ['owner', 'manager'])) {
            abort(403);
        }
        
        $validated = $request->validate([
            'total_budget' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'period_type' => 'required|in:project,monthly,quarterly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string',
            'categories.*.allocated_amount' => 'required|numeric|min:0',
            'categories.*.color' => 'nullable|string',
            'categories.*.description' => 'nullable|string'
        ]);

        if ($project->budget) return back()->withErrors(['budget' => 'Budget exists']);

        $budget = $project->budget()->create([
            'workspace_id' => $project->workspace_id,
            'total_budget' => $validated['total_budget'],
            'currency' => $validated['currency'],
            'period_type' => $validated['period_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'],
            'created_by' => $user->id
        ]);

        foreach ($validated['categories'] as $index => $category) {
            $budget->categories()->create([
                'name' => $category['name'],
                'allocated_amount' => $category['allocated_amount'],
                'color' => $category['color'] ?? '#3B82F6',
                'description' => $category['description'],
                'sort_order' => $index + 1
            ]);
        }

        return back();
    }

    public function assignMember(Request $request, Project $project)
    {
        $this->authorizePermission('project_assign_members');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:owner,manager,member,client'
        ]);

        ProjectMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role'], 'assigned_by' => auth()->id()]
        );

        $user = User::find($validated['user_id']);
        $project->logActivity('member_assigned', "User '{$user->name}' was assigned to project");

        return back();
    }

    public function removeMember(Project $project, User $user)
    {
        $this->authorizePermission('project_assign_members');
        
        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $project->members()->where('user_id', $user->id)->delete();
        $project->logActivity('member_removed', "User '{$user->name}' was removed from project");

        return back();
    }

    public function updateProgress(Request $request, Project $project)
    {
        $this->authorizePermission('project_track_progress');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100'
        ]);

        $project->update($validated);
        $project->logActivity('progress_updated', "Project progress updated to {$validated['progress']}%");

        return back();
    }

    public function assignClient(Request $request, Project $project)
    {
        $this->authorizePermission('project_assign_clients');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        \App\Models\ProjectClient::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $validated['user_id']],
            ['assigned_by' => auth()->id()]
        );

        $user = User::find($validated['user_id']);
        $project->logActivity('client_assigned', "Client '{$user->name}' was assigned to project");

        return back();
    }

    public function removeClient(Project $project, User $user)
    {
        $this->authorizePermission('project_assign_clients');
        
        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        \App\Models\ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();

        $project->logActivity('client_removed', "Client '{$user->name}' was removed from project");

        return back();
    }

    public function assignClients(Request $request, Project $project)
    {
        $this->authorizePermission('project_assign_clients');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:users,id'
        ]);

        foreach ($validated['client_ids'] as $clientId) {
            \App\Models\ProjectClient::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $clientId],
                ['assigned_by' => auth()->id()]
            );
        }

        $clientNames = User::whereIn('id', $validated['client_ids'])->pluck('name')->toArray();
        $project->logActivity('clients_assigned', "Clients '" . implode(', ', $clientNames) . "' were assigned to project");

        return back();
    }

    public function assignMembers(Request $request, Project $project)
    {
        $this->authorizePermission('project_assign_members');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:users,id'
        ]);

        foreach ($validated['member_ids'] as $memberId) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $memberId],
                ['role' => 'member', 'assigned_by' => auth()->id()]
            );
        }

        $memberNames = User::whereIn('id', $validated['member_ids'])->pluck('name')->toArray();
        $project->logActivity('members_assigned', "Members '" . implode(', ', $memberNames) . "' were assigned to project");

        return back();
    }

    public function assignManagers(Request $request, Project $project)
    {
        $this->authorizePermission('project_assign_members');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $validated = $request->validate([
            'manager_ids' => 'required|array',
            'manager_ids.*' => 'exists:users,id'
        ]);

        foreach ($validated['manager_ids'] as $managerId) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project->id, 'user_id' => $managerId],
                ['role' => 'manager', 'assigned_by' => auth()->id()]
            );
        }

        $managerNames = User::whereIn('id', $validated['manager_ids'])->pluck('name')->toArray();
        $project->logActivity('managers_assigned', "Managers '" . implode(', ', $managerNames) . "' were assigned to project");

        return back();
    }

    public function recalculateProgress(Project $project)
    {
        $this->authorizePermission('project_track_progress');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }
        $project->updateProgressFromMilestones();
        $project->logActivity('progress_recalculated', "Project progress was recalculated to {$project->fresh()->progress}%");

        return back()->with('success', 'Project progress has been recalculated.');
    }
}