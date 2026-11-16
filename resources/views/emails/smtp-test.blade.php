<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Connection Test</title>
</head>
<body style="font-family: sans-serif; padding: 20px; color: #333;">
    <div style="max-width: 500px; margin: auto; border: 1px solid #ddd; padding: 24px; border-radius: 8px;">
        <h2 style="color: #16a34a; margin-top: 0;">✓ SMTP Test Succeeded</h2>
        <p>This email verifies that your SMTP mail server settings are configured properly in SEO Academy.</p>
        <p style="font-size: 13px; color: #666;">
            <strong>Sent by:</strong> {{ $senderName }}<br>
            <strong>Timestamp:</strong> {{ $testTimestamp }}
        </p>
    </div>
</body>
</html>

