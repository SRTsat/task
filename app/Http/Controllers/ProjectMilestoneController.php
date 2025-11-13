<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        $milestone = $project->milestones()->create([
            ...$validated,
            'progress' => 0,
            'order' => $project->milestones()->max('order') + 1,
            'created_by' => auth()->id()
        ]);
        
        // Calculate initial progress from tasks
        $milestone->updateProgressFromTasks();

        $project->logActivity('milestone_created', "Milestone '{$milestone->title}' was created");

        return back();
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        $milestone->update($validated);
        
        // Auto-calculate progress from tasks
        $milestone->updateProgressFromTasks();

        if ($validated['status'] === 'completed' && $milestone->status !== 'completed') {
            $milestone->update([
                'completed_at' => now(),
                'completed_by' => auth()->id()
            ]);
        }

        $project->logActivity('milestone_updated', "Milestone '{$milestone->title}' was updated");

        return back();
    }

    public function destroy(Project $project, ProjectMilestone $milestone)
    {
        $milestone->delete();
        $project->logActivity('milestone_deleted', "Milestone '{$milestone->title}' was deleted");

        return back();
    }

    public function updateStatus(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        $milestone->update($validated);

        if ($validated['status'] === 'completed') {
            $milestone->markCompleted();
        }

        return back();
    }

    public function reorder(Request $request, Project $project)
    {
        $validated = $request->validate([
            'milestones' => 'required|array',
            'milestones.*.id' => 'required|exists:project_milestones,id',
            'milestones.*.order' => 'required|integer'
        ]);

        foreach ($validated['milestones'] as $milestoneData) {
            ProjectMilestone::where('id', $milestoneData['id'])
                ->update(['order' => $milestoneData['order']]);
        }

        $project->logActivity('milestones_reordered', 'Project milestones were reordered');

        return back();
    }
}