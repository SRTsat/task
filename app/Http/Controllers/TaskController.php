<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\TaskStage;
use App\Models\TaskCollaborator;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use HasPermissionChecks;
    public function index(Request $request): Response
    {
        $this->authorizePermission('task_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        $query = Task::with(['project', 'taskStage', 'assignedTo', 'creator', 'milestone', 'collaborators.user'])
        ->whereHas('collaborators', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->orWhereHas('project', function($q) use ($user, $userWorkspaceRole) {
            $q->forWorkspace($user->current_workspace_id);
            
            // If not workspace owner, only show tasks from accessible projects
            if ($userWorkspaceRole !== 'owner') {
                $q->where(function($projectQuery) use ($user) {
                    $projectQuery->whereHas('members', function($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id);
                    })
                    ->orWhereHas('clients', function($clientQuery) use ($user) {
                        $clientQuery->where('user_id', $user->id);
                    })
                    ->orWhere('created_by', $user->id);
                });
            }
        });

        // Filter tasks by assignment for members only
        if ($userWorkspaceRole === 'member') {
            $query->where(function($taskQuery) use ($user) {
                $taskQuery->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        if ($request->project_id) {
            $query->forProject($request->project_id);
        }

        if ($request->stage_id) {
            $query->byStage($request->stage_id);
        }

        if ($request->priority) {
            $query->byPriority($request->priority);
        }

        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Default to kanban view and get all data without pagination
        $view = $request->get('view', 'kanban');
        
        if ($view === 'kanban') {
            $tasks = $query->get();
        } else {
            $perPage = $request->get('per_page', 20);
            $perPage = in_array($perPage, [20, 50, 100]) ? $perPage : 20;
            $tasks = $query->latest()->paginate($perPage);
        }

        // Apply same access control to projects dropdown as used for task filtering
        $projectsQuery = Project::forWorkspace($user->current_workspace_id)
            ->with(['milestones', 'members.user']);
            
        // If not workspace owner, only show accessible projects
        if ($userWorkspaceRole !== 'owner') {
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
        
        $projects = $projectsQuery->get();
        $stages = TaskStage::forWorkspace($user->current_workspace_id)->ordered()->get();
        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'stages' => $stages,
            'members' => $members,
            'filters' => array_merge(
                $request->only(['project_id', 'stage_id', 'priority', 'assigned_to', 'search', 'per_page']),
                ['view' => $view]
            ),
            'project_name' => $request->project_name,
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => [
                'create' => $this->checkPermission('task_create'),
                'update' => $this->checkPermission('task_update'),
                'delete' => $this->checkPermission('task_delete'),
                'duplicate' => $this->checkPermission('task_duplicate'),
                'change_status' => $this->checkPermission('task_change_status'),
                'assign_users' => $this->checkPermission('task_assign_users'),
                'manage_stages' => $this->checkPermission('task_manage_stages'),
                'add_comments' => $this->checkPermission('task_add_comments'),
                'add_attachments' => $this->checkPermission('task_add_attachments'),
                'manage_checklists' => $this->checkPermission('task_manage_checklists'),
            ],
            // 'collaborators' => $taskCollaborator,
        ]);
    }

    public function show(Task $task)
    {
        $this->authorizePermission('task_view');
        
        $task->load([
            'project.workspace', 'project.members.user', 'taskStage', 'assignedTo', 'creator', 'milestone',
            'comments.user', 'checklists.assignedTo', 'checklists.creator', 'attachments.mediaItem', 'collaborators.user'
        ]);
        
        // Ensure MediaItem appended attributes are loaded
        $task->attachments->load('mediaItem');
        $task->attachments->each(function($attachment) {
            if ($attachment->mediaItem) {
                // Force load the media to ensure appended attributes work
                $attachment->mediaItem->getFirstMedia('images');
            }
        });

        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;
        
        // Ensure task belongs to current workspace
        if (!$workspace || $task->project->workspace_id !== $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        
        // Add permission flags to comments
        $task->comments->each(function($comment) use ($currentUser) {
            $comment->can_update = $comment->canBeUpdatedBy($currentUser);
            $comment->can_delete = $comment->canBeDeletedBy($currentUser);
        });
        
        // Add permission flags to checklists
        $task->checklists->each(function($checklist) use ($currentUser) {
            $checklist->can_update = $checklist->canBeUpdatedBy($currentUser);
            $checklist->can_delete = $checklist->canBeDeletedBy($currentUser);
        });
        
        $allMembers = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();
        
        // Get project members only (no clients)
        $projectMembers = $task->project->members->filter(function($member) {
            return $member->user && $member->user->type !== 'client';
        })->pluck('user');
        
        $stages = TaskStage::forWorkspace($currentUser->current_workspace_id)->ordered()->get();
        $milestones = $task->project->milestones ?? [];
        $collaborators = $task->collaborators->pluck('user')->filter();

        return response()->json([
            'task' => $task,
            'members' => $projectMembers->isNotEmpty() ? $projectMembers : $allMembers,
            'stages' => $stages,
            'milestones' => $milestones,
            'permissions' => [
                'update' => $this->checkPermission('task_update'),
                'delete' => $this->checkPermission('task_delete'),
                'duplicate' => $this->checkPermission('task_duplicate'),
                'change_status' => $this->checkPermission('task_change_status'),
                'assign_users' => $this->checkPermission('task_assign_users'),
                'add_comments' => $this->checkPermission('task_add_comments'),
                'add_attachments' => $this->checkPermission('task_add_attachments'),
                'manage_checklists' => $this->checkPermission('task_manage_checklists'),
            ],
            'collaborators' => $collaborators,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('task_create');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace) {
            abort(403, 'No workspace selected.');
        }
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_to' => 'required|exists:users,id'
        ]);
        
        // Ensure project belongs to current workspace
        $project = Project::find($validated['project_id']);
        if (!$project || $project->workspace_id !== $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }

        // Get first stage for the workspace
        $firstStage = TaskStage::forWorkspace(auth()->user()->current_workspace_id)
            ->ordered()
            ->first();

        $task = Task::create([
            ...$validated,
            'task_stage_id' => $firstStage->id,
            'created_by' => auth()->id(),
            'progress' => 0
        ]);

        return back()->with('success', __('Task created successfully!'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizePermission('task_update');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $task->project->workspace_id !== $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_to' => 'nullable|exists:users,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'collaborators' => 'nullable|array',
        ]);

        // Validate collaborators
        if (isset($validated['collaborators']) && is_array($validated['collaborators'])) {
            foreach ($validated['collaborators'] as $collaboratorName => $collaboratorId) {
                if (TaskCollaborator::where('task_id', $task->id)->where('user_id', $collaboratorId)->exists())
                {
                    // Remove existing collaborators not in the new list
                    TaskCollaborator::where('task_id', $task->id)
                        ->where('user_id', $collaboratorId)
                        ->delete();
                }
                else
                {
                    if ($collaboratorId !== '0' || $collaboratorId !== 0 || $collaboratorId !== true)
                    {
                        $userInTable = User::where('id', $collaboratorId)->orWhere('name', $collaboratorId)->first();
                        if ($userInTable->name !== $user->name)
                        {
                            if ($userInTable)
                            {
                                $task_collaborator = TaskCollaborator::firstOrCreate([
                                    'task_id' => $task->id,
                                    'user_id' => $userInTable->id,
                                ]);
                            }
                            else
                            {
                                $task_collaborator = TaskCollaborator::firstOrCreate([
                                    'task_id' => $task->id,
                                    'user_id' => $collaboratorId,
                                ]);
                            }
                        }
                    }
                }
                // $collaborator = User::find($collaboratorId);
                // if (!$collaborator || !$collaborator->isInWorkspace($workspace->id)) {
                //     return back()->withErrors(['collaborators' => 'All collaborators must be members of the current workspace.'])->withInput();
                // }
            }
        } else {
            $validated['collaborators'] = [];
        }

        $task->update($validated);

        return back()->with('success', __('Task updated successfully!'));
    }

    public function destroy(Task $task)
    {
        $this->authorizePermission('task_delete');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $task->project->workspace_id !== $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        $task->delete();

        return back()->with('success', __('Task deleted successfully!'));
    }

    public function duplicate(Task $task)
    {
        $this->authorizePermission('task_duplicate');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $task->project->workspace_id !== $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        $newTask = $task->replicate();
        $newTask->title = $task->title . ' (Copy)';
        $newTask->start_date = null;
        $newTask->end_date = null;
        $newTask->progress = 0;
        $newTask->created_by = auth()->id();
        $newTask->save();

        // Copy checklists
        foreach ($task->checklists as $checklist) {
            $newChecklist = $checklist->replicate();
            $newChecklist->task_id = $newTask->id;
            $newChecklist->is_completed = false;
            $newChecklist->created_by = auth()->id();
            $newChecklist->save();
        }

        return back()->with('success', __('Task duplicated successfully!'));
    }

    public function changeStage(Request $request, Task $task)
    {
        $this->authorizePermission('task_change_status');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace || $task->project->workspace_id !== $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        $validated = $request->validate([
            'task_stage_id' => 'required|exists:task_stages,id'
        ]);

        $task->update($validated);

        return back()->with('success', __('Task stage updated successfully!'));
    }
}