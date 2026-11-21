<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $course?->title ?? 'SEO Academy' }} — {{ $lesson->title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 13mm 10mm 13mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #0f172a;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 8.5pt;
            line-height: 1.35;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .header-logo-cell {
            vertical-align: middle;
            text-align: left;
            width: 60%;
        }

        .header-meta-cell {
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }

        .academy-logo {
            max-height: 26px;
            max-width: 140px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .academy-brand-name {
            font-size: 13pt;
            font-weight: 700;
            color: {{ $branding['primary_color'] }};
            letter-spacing: -0.3px;
            display: inline-block;
            vertical-align: middle;
        }

        .academy-tagline {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 1px;
        }

        .doc-badge {
            display: inline-block;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            background-color: #f1f5f9;
            color: #334155;
            padding: 2px 7px;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
        }

        .doc-date {
            font-size: 7pt;
            color: #64748b;
            margin-top: 2px;
        }

        .brand-divider {
            height: 2.5px;
            background-color: {{ $branding['primary_color'] }};
            width: 100%;
            margin-bottom: 8px;
            border: none;
        }

        /* ── Course & Lesson Information Bar ── */
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-col-main {
            width: 68%;
            vertical-align: top;
        }

        .info-col-side {
            width: 32%;
            vertical-align: top;
            text-align: right;
        }

        .course-title-label {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: {{ $branding['secondary_color'] }};
        }

        .course-title {
            font-size: 9pt;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .lesson-title {
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
        }

        .pill {
            display: inline-block;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 3px;
            letter-spacing: 0.4px;
        }

        .pill-type {
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .pill-duration {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            margin-left: 3px;
        }

        .module-info {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 3px;
        }

        /* ── Lesson Overview Callout ── */
        .overview-box {
            background-color: #eff6ff;
            border-left: 3px solid {{ $branding['primary_color'] }};
            padding: 5px 8px;
            margin-bottom: 8px;
            border-radius: 0 3px 3px 0;
        }

        .overview-title {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e40af;
            margin-bottom: 2px;
        }

        .overview-text {
            font-size: 8pt;
            color: #1e3a8a;
            line-height: 1.3;
            margin: 0;
        }

        /* ── Main Content Area ── */
        .content-body {
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
        }

        .content-body h1 {
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 6px;
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            page-break-after: avoid;
        }

        .content-body h2 {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 5px;
            margin-bottom: 3px;
            page-break-after: avoid;
        }

        .content-body h3 {
            font-size: 9pt;
            font-weight: 700;
            color: #1e293b;
            margin-top: 4px;
            margin-bottom: 2px;
            page-break-after: avoid;
        }

        .content-body h4 {
            font-size: 8.5pt;
            font-weight: 600;
            color: #334155;
            margin-top: 3px;
            margin-bottom: 2px;
            page-break-after: avoid;
        }

        .content-body p {
            margin-top: 0;
            margin-bottom: 4px;
        }

        .content-body ul, .content-body ol {
            margin-top: 2px;
            margin-bottom: 5px;
            padding-left: 16px;
        }

        .content-body li {
            margin-bottom: 2px;
        }

        .content-body strong {
            font-weight: 700;
            color: #0f172a;
        }

        .content-body em {
            font-style: italic;
        }

        .content-body blockquote {
            background-color: #f8fafc;
            border-left: 3px solid {{ $branding['secondary_color'] }};
            margin: 4px 0;
            padding: 4px 8px;
            font-style: italic;
            color: #334155;
            page-break-inside: avoid;
        }

        .content-body pre {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 5px 7px;
            margin: 4px 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.5pt;
            line-height: 1.25;
            color: #0f172a;
            page-break-inside: avoid;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .content-body code {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f1f5f9;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7.5pt;
            color: #0f172a;
        }

        .content-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 7.5pt;
            page-break-inside: avoid;
        }

        .content-body th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            text-align: left;
            padding: 3px 5px;
            border: 1px solid #cbd5e1;
        }

        .content-body td {
            padding: 3px 5px;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        .content-body a {
            color: {{ $branding['primary_color'] }};
            text-decoration: underline;
        }

        /* ── Callout Box for Highlights ── */
        .callout-box {
            background-color: #fefce8;
            border: 1px solid #fef08a;
            border-left: 3px solid #eab308;
            border-radius: 3px;
            padding: 5px 8px;
            margin: 5px 0;
            font-size: 8pt;
            color: #713f12;
            page-break-inside: avoid;
        }

        /* ── Footer ── */
        .document-footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #cbd5e1;
            page-break-inside: avoid;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-left {
            width: 70%;
            font-size: 7pt;
            color: #64748b;
            vertical-align: middle;
        }

        .footer-right {
            width: 30%;
            font-size: 7pt;
            color: #64748b;
            text-align: right;
            vertical-align: middle;
        }

        .footer-brand {
            font-weight: 600;
            color: #334155;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                @if ($branding['logo_data_uri'])
                    <img src="{{ $branding['logo_data_uri'] }}" alt="{{ $branding['name'] }}" class="academy-logo" />
                @else
                    <span class="academy-brand-name">{{ $branding['name'] }}</span>
                @endif
                <div class="academy-tagline">{{ $branding['tagline'] }}</div>
            </td>
            <td class="header-meta-cell">
                <span class="doc-badge">Lesson Material &bull; One-Page Handout</span>
                <div class="doc-date">Generated: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>

    <div class="brand-divider"></div>

    {{-- Course & Lesson Information Bar --}}
    <div class="info-card">
        <table class="info-table">
            <tr>
                <td class="info-col-main">
                    <div class="course-title-label">{{ $course?->category?->name ?? 'Course' }} &bull; {{ $course?->difficulty ?? 'Professional' }}</div>
                    <div class="course-title">{{ $course?->title ?? 'SEO Training' }}</div>
                    <div class="lesson-title">{{ $lesson->title }}</div>
                </td>
                <td class="info-col-side">
                    <div>
                        <span class="pill pill-type">{{ ucfirst($lesson->type ?: 'lesson') }}</span>
                        @if ($durationFormatted)
                            <span class="pill pill-duration">{{ $durationFormatted }}</span>
                        @endif
                    </div>
                    @if ($module)
                        <div class="module-info">
                            Module {{ $module->display_order }}: {{ $module->title }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Overview / Objectives Box (if description is provided) --}}
    @if ($formattedDescription)
        <div class="overview-box">
            <div class="overview-title">Lesson Overview & Key Takeaway</div>
            <p class="overview-text">{{ $formattedDescription }}</p>
        </div>
    @endif

    {{-- Main Lesson Content (Markdown Rendered & Sanitized) --}}
    <div class="content-body">
        @if ($formattedContent)
            {!! $formattedContent !!}
        @else
            <p><em>No additional written notes or text content provided for this lesson. Refer to the video playback and attached lesson resources.</em></p>
        @endif
    </div>

    {{-- Footer with Page 1 of 1 --}}
    <div class="document-footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <span class="footer-brand">{{ $branding['name'] }}</span> &bull; {{ $branding['tagline'] }} &bull; <a href="{{ $branding['website_url'] }}" style="color: inherit; text-decoration: none;">{{ $branding['website_url'] }}</a>
                </td>
                <td class="footer-right">
                    <span>Page 1 of 1</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
