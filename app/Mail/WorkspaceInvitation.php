<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation as WorkspaceInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitationModel $invitation
    ) {}

    public function build()
    {
        return $this->subject('You\'re invited to join ' . $this->invitation->workspace->name)
                    ->view('emails.workspace-invitation')
                    ->with([
                        'invitation' => $this->invitation,
                        'acceptUrl' => route('invitations.show', $this->invitation->token)
                    ]);
    }
}