<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notificationTitle }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 32px;
        }
        .header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .academy-name {
            font-size: 18px;
            font-weight: 700;
            color: #2563eb;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin-top: 12px;
            margin-bottom: 16px;
            color: #0f172a;
        }
        .message {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="academy-name">{{ $academyName }}</div>
        </div>

        <h1 class="title">{{ $notificationTitle }}</h1>

        <div class="message">
            {!! nl2br(e($notificationMessage)) !!}
        </div>

        @if (! empty($actionUrl))
            <div>
                <a href="{{ $actionUrl }}" class="btn">{{ $actionText ?? 'View in Academy' }}</a>
            </div>
        @endif

        <div class="footer">
            This is an automated notification from {{ $academyName }}. You can customize your notification preferences from your academy dashboard.
        </div>
    </div>
</body>
</html>

