<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $portfolio->title ?? 'SEO Practical Portfolio' }}</title>
    <style>
        @page {
            margin: 25mm 20mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 10pt;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0f172a;
            font-size: 20pt;
            margin: 0 0 4px 0;
            font-weight: 700;
        }
        .header .headline {
            color: #0284c7;
            font-size: 12pt;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .header .meta {
            color: #64748b;
            font-size: 8.5pt;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .bio-box {
            background-color: #f8fafc;
            border-left: 3px solid #0284c7;
            padding: 10px 14px;
            font-size: 9.5pt;
            color: #334155;
            margin-bottom: 15px;
        }
        .skill-badge {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            font-size: 8pt;
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .project-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .project-title {
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .project-meta {
            color: #64748b;
            font-size: 8.5pt;
            margin-bottom: 8px;
        }
        .project-case-study {
            font-size: 9pt;
            color: #334155;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .achievement-badge {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            font-size: 8pt;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $student->name }}</h1>
        <div class="headline">{{ $portfolio->headline ?? 'SEO Specialist Portfolio' }}</div>
        <div class="meta">
            SEO Academy Certified Practical Portfolio &bull; Exported on {{ $exportedAt }} &bull; Student Code: {{ $profile->student_code ?? 'N/A' }}
        </div>
    </div>

    @if(!empty($portfolio->bio))
        <div class="bio-box">
            {{ $portfolio->bio }}
        </div>
    @endif

    @if(!empty($portfolio->custom_skills) && is_array($portfolio->custom_skills))
        <div class="section-title">Core Competencies & SEO Skills</div>
        <div style="margin-bottom: 15px;">
            @foreach($portfolio->custom_skills as $skill)
                <span class="skill-badge">{{ $skill }}</span>
            @endforeach
        </div>
    @endif

    <div class="section-title">Verified Practical SEO Projects</div>

    @forelse($portfolio->visibleItems as $item)
        @php
            $sub = $item->submission;
            $proj = $sub?->project;
            $ach = $sub?->achievement;
        @endphp
        <div class="project-card">
            <div class="project-title">{{ $item->title }}</div>
            <div class="project-meta">
                <strong>Category:</strong> {{ $proj?->categoryLabel() ?? 'Practical SEO' }} &bull;
                <strong>Difficulty:</strong> {{ $proj?->difficultyLabel() ?? 'Intermediate' }} &bull;
                <strong>Evaluated Score:</strong> {{ number_format((float) ($sub?->score_percentage ?? 100), 1) }}%
            </div>

            <div class="project-case-study">
                {{ $item->case_study }}
            </div>

            @if(!empty($sub?->website_url))
                <div style="font-size: 8.5pt; color: #0284c7; margin-bottom: 6px;">
                    <strong>Target Website / Audit Subject:</strong> {{ $sub->website_url }}
                </div>
            @endif

            @if(!empty($item->skills_demonstrated) && is_array($item->skills_demonstrated))
                <div style="margin-top: 6px; margin-bottom: 6px;">
                    @foreach($item->skills_demonstrated as $skill)
                        <span class="skill-badge" style="background-color: #f1f5f9; color: #475569;">{{ $skill }}</span>
                    @endforeach
                </div>
            @endif

            @if($ach)
                <div style="margin-top: 8px;">
                    <span class="achievement-badge">
                        &check; Verified Achievement Code: {{ $ach->achievement_number }}
                    </span>
                </div>
            @endif
        </div>
    @empty
        <p style="color: #64748b; font-style: italic;">No practical projects have been included in this portfolio yet.</p>
    @endforelse

    <div class="footer">
        Confidential Academic Portfolio &bull; Verified by SEO Academy Practical Evaluation Engine &bull; Page 1 of 1
    </div>
</body>
</html>
