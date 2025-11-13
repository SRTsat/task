<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspace Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2563eb;">You're invited to join {{ $invitation->workspace->name }}</h2>
        
        <p>Hello,</p>
        
        <p>{{ $invitation->invitedBy->name }} has invited you to join the <strong>{{ $invitation->workspace->name }}</strong> workspace as a {{ $invitation->role }}.</p>
        
        @if($invitation->workspace->description)
        <p><em>{{ $invitation->workspace->description }}</em></p>
        @endif
        
        <div style="margin: 30px 0;">
            <a href="{{ $acceptUrl }}" 
               style="background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                Accept Invitation
            </a>
        </div>
        
        <p>This invitation will expire on {{ $invitation->expires_at->format('M j, Y') }}.</p>
        
        <p>If you can't click the button, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666;">{{ $acceptUrl }}</p>
        
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
        <p style="font-size: 12px; color: #666;">
            If you didn't expect this invitation, you can safely ignore this email.
        </p>
    </div>
</body>
</html>