<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    use HasPermissionChecks;
    
    public function __construct(private WorkspaceService $workspaceService)
    {
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizePermission('workspace_invite_members');
        
        if (!auth()->user()->canAccessWorkspace($workspace)) {
            abort(403);
        }
        
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:manager,member,client,admin,user'
        ]);

        // Check if invitation already exists
        $existingInvitation = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->where('email', $request->email)
            ->where('accepted_at', null)
            ->first();

        if ($existingInvitation) {
            // Update existing invitation role if different
            if ($existingInvitation->role !== $request->role) {
                $existingInvitation->update(['role' => $request->role]);
            }
            
            // Resend existing invitation
            try {
                \App\Services\MailConfigService::setDynamicConfig($workspace->owner_id, $workspace->id);
                
                // If workspace SMTP not configured and in SaaS mode, fallback to super admin
                if (config('mail.default') === 'log' && isSaasMode()) {
                    \App\Services\MailConfigService::setDynamicConfig(null, null);
                }
                
                if (config('mail.default') !== 'log') {
                    \Illuminate\Support\Facades\Mail::to($request->email)
                        ->send(new \App\Mail\WorkspaceInvitation($existingInvitation->load(['workspace', 'invitedBy'])));
                    return back()->with('success', __('Invitation resent successfully'));
                } else {
                    return back()->with('success', __('Invitation updated successfully. Email not sent (SMTP not configured).'));
                }
            } catch (\Exception $e) {
                return back()->with('success', __('Invitation exists. Email sending failed but invitation is still valid.'));
            }
        } else {
            // Create new invitation
            try {
                $invitation = $this->workspaceService->inviteUser(
                    $workspace,
                    $request->email,
                    $request->role,
                    auth()->user()
                );

                if (config('mail.default') === 'log') {
                    return back()->with('success', __('Invitation created successfully. Email not sent (SMTP not configured).'));
                } else {
                    return back()->with('success', __('Invitation sent successfully'));
                }
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
    }

    public function show(string $token)
    {
        $invitation = WorkspaceInvitation::where('token', $token)
            ->with(['workspace', 'invitedBy'])
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login');
        }

        $existingUser = auth()->check() || User::where('email', $invitation->email)->exists();

        return Inertia::render('Invitations/Accept', [
            'invitation' => $invitation,
            'existingUser' => $existingUser
        ]);
    }

    public function accept(Request $request, string $token)
    {
        try {
            $invitation = WorkspaceInvitation::where('token', $token)->firstOrFail();
            
            if ($invitation->isExpired() || $invitation->isAccepted()) {
                abort(404);
            }

            $existingUser = auth()->check();
            
            if (!$existingUser) {
                $request->validate([
                    'password' => 'required|min:8|confirmed'
                ]);
            }

            $result = $this->workspaceService->acceptInvitation(
                $token,
                $request->password
            );

            if (!$existingUser) {
                auth()->login($result['user']);
            }

            return redirect()->route('workspaces.show', $result['workspace']->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Re-throw validation exceptions
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function resend(WorkspaceInvitation $invitation)
    {
        $this->authorizePermission('workspace_invite_members');
        
        if (!auth()->user()->canAccessWorkspace($invitation->workspace)) {
            abort(403);
        }

        try {
            \App\Services\MailConfigService::setDynamicConfig($invitation->workspace->owner_id, $invitation->workspace->id);
            
            // If workspace SMTP not configured and in SaaS mode, fallback to super admin
            if (config('mail.default') === 'log' && isSaasMode()) {
                \App\Services\MailConfigService::setDynamicConfig(null, null);
            }
            
            if (config('mail.default') !== 'log') {
                \Illuminate\Support\Facades\Mail::to($invitation->email)
                    ->send(new \App\Mail\WorkspaceInvitation($invitation->load(['workspace', 'invitedBy'])));
                return back()->with('success', __('Invitation resent successfully'));
            } else {
                return back()->with('success', __('Invitation exists. Email not sent (SMTP not configured).'));
            }
        } catch (\Exception $e) {
            return back()->with('success', __('Invitation exists. Email sending failed but invitation is still valid.'));
        }
    }

    public function destroy(WorkspaceInvitation $invitation)
    {
        $this->authorizePermission('workspace_manage_members');
        
        if (!auth()->user()->canAccessWorkspace($invitation->workspace)) {
            abort(403);
        }

        $invitation->delete();
        return back()->with('success', __('Invitation deleted successfully'));
    }
}