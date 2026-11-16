<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: {{ $config['font_family'] ?? 'Georgia, serif' }};
            margin: 0;
            padding: 30px;
            background-color: #ffffff;
            color: #1e293b;
            box-sizing: border-box;
            height: 100%;
        }
        .outer-border {
            border: 6px double {{ $config['border_color'] ?? '#1e3a8a' }};
            padding: 25px;
            height: 90%;
            box-sizing: border-box;
            text-align: center;
            position: relative;
        }
        .academy-name {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: {{ $config['header_color'] ?? '#1e3a8a' }};
            margin-bottom: 8px;
        }
        .cert-title {
            font-size: 36px;
            font-weight: bold;
            color: {{ $config['accent_color'] ?? '#d97706' }};
            margin: 10px 0;
            text-transform: uppercase;
            letter-spacing: 4px;
        }
        .cert-subtitle {
            font-size: 14px;
            font-style: italic;
            color: #64748b;
            margin-bottom: 20px;
        }
        .recipient-name {
            font-size: 32px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 2px solid {{ $config['accent_color'] ?? '#d97706' }};
            display: inline-block;
            padding: 0 40px 6px 40px;
            margin: 15px auto;
        }
        .course-text {
            font-size: 15px;
            color: #475569;
            margin: 12px 0 6px 0;
        }
        .course-title {
            font-size: 24px;
            font-weight: bold;
            color: {{ $config['header_color'] ?? '#1e3a8a' }};
            margin-bottom: 20px;
        }
        .cert-footer {
            margin-top: 30px;
            width: 100%;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            width: 200px;
            margin: 0 auto 5px auto;
        }
        .meta-text {
            font-size: 11px;
            color: #64748b;
        }
        .verification-box {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        <div class="academy-name">
            {{ $academy?->name ?? config('app.name', 'SEO Academy') }}
        </div>
        <div class="cert-title">
            Certificate of Completion
        </div>
        <div class="cert-subtitle">
            This is proudly awarded to
        </div>

        <div class="recipient-name">
            {{ $student?->name ?? 'Student' }}
        </div>

        <div class="course-text">
            for successfully fulfilling all curriculum requirements and completing the course
        </div>

        <div class="course-title">
            {{ $course?->title ?? 'Mastery Course' }}
        </div>

        <div class="cert-footer">
            <table class="footer-table">
                <tr>
                    <td style="width: 33%; text-align: left; vertical-align: bottom;">
                        <div class="meta-text">
                            <strong>Certificate No:</strong> {{ $certificate->certificate_number }}<br>
                            <strong>Issue Date:</strong> {{ $certificate->issued_at?->format('F d, Y') ?? date('F d, Y') }}<br>
                            @if ($enrollment?->completed_at)
                                <strong>Completed:</strong> {{ $enrollment->completed_at->format('F d, Y') }}
                            @endif
                        </div>
                    </td>
                    <td style="width: 34%; text-align: center; vertical-align: middle;">
                        <div style="font-size: 13px; font-weight: bold; color: {{ $config['accent_color'] ?? '#d97706' }};">
                            OFFICIAL VERIFIED
                        </div>
                    </td>
                    <td style="width: 33%; text-align: right; vertical-align: bottom;">
                        <div class="signature-line" style="margin-left: auto; margin-right: 0;"></div>
                        <div style="font-size: 13px; font-weight: bold; color: #1e293b;">
                            {{ $config['signatory_name'] }}
                        </div>
                        <div class="meta-text">
                            {{ $config['signatory_title'] }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="verification-box">
            Verify authenticity online at: {{ $verificationUrl }}
        </div>
    </div>
</body>
</html>

