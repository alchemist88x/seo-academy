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
            font-family: {{ $config['font_family'] ?? 'Arial, sans-serif' }};
            margin: 0;
            padding: 40px;
            background-color: #ffffff;
            color: #334155;
            box-sizing: border-box;
            height: 100%;
        }
        .container {
            border: 1px solid #cbd5e1;
            padding: 40px;
            height: 85%;
            box-sizing: border-box;
            position: relative;
        }
        .academy {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
        }
        .title {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 3px;
            color: #0f172a;
            margin: 20px 0 30px 0;
            text-transform: uppercase;
        }
        .student {
            font-size: 32px;
            font-weight: 600;
            color: #047857;
            margin-bottom: 15px;
        }
        .course {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
            margin-top: 5px;
        }
        .footer {
            position: absolute;
            bottom: 30px;
            left: 40px;
            right: 40px;
        }
        .meta {
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="academy">{{ $academy?->name ?? 'SEO Academy' }}</div>
        <div class="title">Certificate of Completion</div>

        <div style="font-size: 13px; color: #64748b; margin-bottom: 10px;">Granted to</div>
        <div class="student">{{ $student?->name ?? 'Student' }}</div>

        <div style="font-size: 13px; color: #64748b; margin-top: 20px;">For successful completion of</div>
        <div class="course">{{ $course?->title ?? 'Course' }}</div>

        <div class="footer">
            <table style="width: 100%;">
                <tr>
                    <td style="text-align: left; font-size: 11px; color: #64748b;">
                        Cert No: {{ $certificate->certificate_number }}<br>
                        Issued: {{ $certificate->issued_at?->format('Y-m-d') ?? date('Y-m-d') }}
                    </td>
                    <td style="text-align: right; font-size: 11px; color: #64748b;">
                        {{ $config['signatory_name'] }}<br>
                        {{ $config['signatory_title'] }}
                    </td>
                </tr>
            </table>
            <div class="meta" style="margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 5px;">
                Verify at: {{ $verificationUrl }}
            </div>
        </div>
    </div>
</body>
</html>

