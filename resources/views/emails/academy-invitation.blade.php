<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invitation to {{ $academy->name }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0f172a; line-height: 1.6; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px;">
        <h2 style="margin-top: 0; color: #0f172a;">You're invited to {{ $academy->name }}</h2>

        <p>Hello{{ $invitation->name ? ' ' . $invitation->name : '' }},</p>

        <p>
            You have been invited to join <strong>{{ $academy->name }}</strong> as
            <strong>{{ str_replace('_', ' ', $invitation->role) }}</strong>.
        </p>

        <p style="margin: 24px 0;">
            <a href="{{ $acceptUrl }}"
               style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                Accept Invitation
            </a>
        </p>

        <p style="font-size: 13px; color: #64748b;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $acceptUrl }}" style="color: #4f46e5;">{{ $acceptUrl }}</a>
        </p>

        <p style="font-size: 12px; color: #94a3b8; margin-bottom: 0;">
            This invitation expires on {{ $invitation->expires_at->toDayDateTimeString() }}.
        </p>
    </div>
</body>
</html>
