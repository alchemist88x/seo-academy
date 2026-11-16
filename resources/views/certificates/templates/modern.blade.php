<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Achievement</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: {{ $config['font_family'] ?? 'Helvetica, Arial, sans-serif' }};
            margin: 0;
            padding: 30px;
            background-color: #f8fafc;
            color: #0f172a;
            box-sizing: border-box;
            height: 100%;
        }
        .outer-border {
            background-color: #ffffff;
            border: 3px solid {{ $config['border_color'] ?? '#0f172a' }};
            padding: 30px;
            height: 90%;
            box-sizing: border-box;
            text-align: left;
            position: relative;
        }
        .header-bar {
            border-bottom: 2px solid {{ $config['accent_color'] ?? '#2563eb' }};
            padding-bottom: 12px;
            margin-bottom: 25px;
        }
        .academy-name {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: {{ $config['accent_color'] ?? '#2563eb' }};
        }
        .cert-title {
            font-size: 32px;
            font-weight: 800;
            color: {{ $config['header_color'] ?? '#0f172a' }};
            margin-top: 5px;
        }
        .recipient-section {
            margin: 30px 0;
        }
        .cert-subtitle {
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .recipient-name {
            font-size: 36px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .course-text {
            font-size: 14px;
            color: #475569;
            margin-bottom: 6px;
        }
        .course-title {
            font-size: 22px;
            font-weight: 700;
            color: {{ $config['accent_color'] ?? '#2563eb' }};
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            width: 180px;
            margin-bottom: 4px;
        }
        .meta-text {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }
        .verification-bar {
            position: absolute;
            bottom: 20px;
            left: 30px;
            right: 30px;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px dashed #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        <div class="header-bar">
            <div class="academy-name">
                {{ $academy?->name ?? config('app.name', 'SEO Academy') }}
            </div>
            <div class="cert-title">
                CERTIFICATE OF COMPLETION
            </div>
        </div>

        <div class="recipient-section">
            <div class="cert-subtitle">Presented to</div>
            <div class="recipient-name">{{ $student?->name ?? 'Student' }}</div>
            <div class="course-text">who has successfully demonstrated proficiency and completed all prescribed coursework in</div>
            <div class="course-title">{{ $course?->title ?? 'Professional Course' }}</div>
        </div>

        <table class="footer-table">
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <div class="meta-text">
                        <strong>Certificate Identifier:</strong> {{ $certificate->certificate_number }}<br>
                        <strong>Issue Date:</strong> {{ $certificate->issued_at?->format('F d, Y') ?? date('F d, Y') }}<br>
                        @if ($enrollment?->completed_at)
                            <strong>Completion Date:</strong> {{ $enrollment->completed_at->format('F d, Y') }}<br>
                        @endif
                        <strong>Status:</strong> Active &amp; Verified
                    </div>
                </td>
                <td style="width: 50%; vertical-align: bottom; text-align: right;">
                    <div class="signature-line" style="margin-left: auto; margin-right: 0;"></div>
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a;">
                        {{ $config['signatory_name'] }}
                    </div>
                    <div class="meta-text">
                        {{ $config['signatory_title'] }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="verification-bar">
            Online verification: {{ $verificationUrl }} &bull; Issued under the authority of {{ $academy?->name ?? 'SEO Academy' }}
        </div>
    </div>
</body>
</html>

