<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['course_title'] ?? 'Certificate of Completion' }}</title>
    <style>
        @page {
            size: {{ $canvas['width'] ?? 842 }}pt {{ $canvas['height'] ?? 595 }}pt;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: {{ $canvas['width'] ?? 842 }}pt;
            height: {{ $canvas['height'] ?? 595 }}pt;
            overflow: hidden;
            font-family: {{ $canvas['fontFamily'] ?? 'Helvetica' }}, Arial, sans-serif;
            background-color: {{ $canvas['backgroundColor'] ?? '#ffffff' }};
            position: relative;
        }
        .canvas-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $canvas['width'] ?? 842 }}pt;
            height: {{ $canvas['height'] ?? 595 }}pt;
            overflow: hidden;
            background-color: {{ $canvas['backgroundColor'] ?? '#ffffff' }};
            @if(!empty($canvas['backgroundImage']))
            background-image: url('{{ $canvas['backgroundImage'] }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            @endif
        }
        @if(!empty($canvas['borderWidth']) && (int)$canvas['borderWidth'] > 0)
        .canvas-border-frame {
            position: absolute;
            top: {{ $canvas['borderInset'] ?? 20 }}pt;
            left: {{ $canvas['borderInset'] ?? 20 }}pt;
            width: {{ ($canvas['width'] ?? 842) - (($canvas['borderInset'] ?? 20) * 2) }}pt;
            height: {{ ($canvas['height'] ?? 595) - (($canvas['borderInset'] ?? 20) * 2) }}pt;
            border-width: {{ $canvas['borderWidth'] ?? 4 }}pt;
            border-style: {{ $canvas['borderStyle'] ?? 'solid' }};
            border-color: {{ $canvas['borderColor'] ?? '#1e3a8a' }};
            pointer-events: none;
            z-index: 1;
        }
        @endif
        @if(!empty($canvas['innerBorderWidth']) && (int)$canvas['innerBorderWidth'] > 0)
        .canvas-inner-border-frame {
            position: absolute;
            top: {{ ($canvas['borderInset'] ?? 20) + ($canvas['innerBorderInset'] ?? 6) }}pt;
            left: {{ ($canvas['borderInset'] ?? 20) + ($canvas['innerBorderInset'] ?? 6) }}pt;
            width: {{ ($canvas['width'] ?? 842) - ((($canvas['borderInset'] ?? 20) + ($canvas['innerBorderInset'] ?? 6)) * 2) }}pt;
            height: {{ ($canvas['height'] ?? 595) - ((($canvas['borderInset'] ?? 20) + ($canvas['innerBorderInset'] ?? 6)) * 2) }}pt;
            border-width: {{ $canvas['innerBorderWidth'] ?? 1 }}pt;
            border-style: {{ $canvas['innerBorderStyle'] ?? 'solid' }};
            border-color: {{ $canvas['innerBorderColor'] ?? '#d97706' }};
            pointer-events: none;
            z-index: 1;
        }
        @endif
        .designer-element {
            position: absolute;
            box-sizing: border-box;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="canvas-wrapper">
        @if(!empty($canvas['borderWidth']) && (int)$canvas['borderWidth'] > 0)
            <div class="canvas-border-frame"></div>
        @endif
        @if(!empty($canvas['innerBorderWidth']) && (int)$canvas['innerBorderWidth'] > 0)
            <div class="canvas-inner-border-frame"></div>
        @endif

        @foreach($elements as $el)
            @php
                $type = $el['type'] ?? 'text';
                $x = $el['x'] ?? 0;
                $y = $el['y'] ?? 0;
                $width = $el['width'] ?? 100;
                $height = $el['height'] ?? 50;
                $zIndex = $el['zIndex'] ?? 2;
                $rotation = $el['rotation'] ?? 0;
                $opacity = $el['opacity'] ?? 1;
                $fontFamily = $el['fontFamily'] ?? ($canvas['fontFamily'] ?? 'Helvetica');
                $fontSize = $el['fontSize'] ?? 14;
                $fontWeight = $el['fontWeight'] ?? 'normal';
                $fontStyle = $el['fontStyle'] ?? 'normal';
                $textAlign = $el['textAlign'] ?? 'left';
                $color = $el['color'] ?? '#000000';
                $lineHeight = $el['lineHeight'] ?? 1.2;
                $letterSpacing = $el['letterSpacing'] ?? 'normal';
                $textTransform = $el['textTransform'] ?? 'none';
                $backgroundColor = $el['backgroundColor'] ?? 'transparent';
                $borderColor = $el['borderColor'] ?? 'transparent';
                $borderWidth = $el['borderWidth'] ?? 0;
                $borderStyle = $el['borderStyle'] ?? 'solid';
                $borderRadius = $el['borderRadius'] ?? 0;
                $padding = $el['padding'] ?? 0;

                // Resolve placeholder text
                $content = $el['content'] ?? '';
                if ($type === 'text') {
                    foreach ($data as $k => $v) {
                        $content = str_replace('{{'.$k.'}}', (string)$v, $content);
                    }
                }

                $style = "left: {$x}pt; top: {$y}pt; width: {$width}pt; height: {$height}pt; z-index: {$zIndex};";
                if ($rotation) {
                    $style .= " transform: rotate({$rotation}deg); -webkit-transform: rotate({$rotation}deg);";
                }
                if ($opacity < 1) {
                    $style .= " opacity: {$opacity};";
                }
                if ($backgroundColor !== 'transparent') {
                    $style .= " background-color: {$backgroundColor};";
                }
                if ($borderWidth > 0 && $borderColor !== 'transparent') {
                    $style .= " border: {$borderWidth}pt {$borderStyle} {$borderColor};";
                }
                if ($borderRadius > 0) {
                    $style .= " border-radius: {$borderRadius}pt;";
                }
                if ($padding > 0) {
                    $style .= " padding: {$padding}pt;";
                }
            @endphp

            @if($type === 'text')
                <div class="designer-element" style="{{ $style }} font-family: {{ $fontFamily }}, sans-serif; font-size: {{ $fontSize }}pt; font-weight: {{ $fontWeight }}; font-style: {{ $fontStyle }}; text-align: {{ $textAlign }}; color: {{ $color }}; line-height: {{ $lineHeight }}; letter-spacing: {{ $letterSpacing }}; text-transform: {{ $textTransform }};">
                    {!! nl2br(e($content)) !!}
                </div>
            @elseif($type === 'line')
                @php
                    $isVertical = ($width < $height);
                    $lineColor = $el['color'] ?? $borderColor ?? '#000000';
                    $lineThickness = $el['borderWidth'] ?? 1;
                    $lineStyle = $el['borderStyle'] ?? 'solid';
                @endphp
                <div class="designer-element" style="{{ $style }}">
                    @if($isVertical)
                        <div style="width: 0; height: 100%; border-left: {{ $lineThickness }}pt {{ $lineStyle }} {{ $lineColor }}; margin: 0 auto;"></div>
                    @else
                        <div style="width: 100%; height: 0; border-top: {{ $lineThickness }}pt {{ $lineStyle }} {{ $lineColor }}; margin-top: {{ $height / 2 }}pt;"></div>
                    @endif
                </div>
            @elseif($type === 'rectangle')
                <div class="designer-element" style="{{ $style }}"></div>
            @elseif($type === 'circle')
                <div class="designer-element" style="{{ $style }} border-radius: 50%;"></div>
            @elseif($type === 'image' || $type === 'logo' || $type === 'signature' || $type === 'badge')
                @php
                    $src = $el['src'] ?? null;
                    // If image is a dynamic placeholder like {{signature_image}} or {{academy_logo}}
                    if ($src && isset($data[str_replace(['{{', '}}'], '', $src)])) {
                        $src = $data[str_replace(['{{', '}}'], '', $src)];
                    }
                @endphp
                <div class="designer-element" style="{{ $style }} overflow: hidden;">
                    @if(!empty($src))
                        <img src="{{ $src }}" style="width: 100%; height: 100%; object-fit: contain;" alt="Element" />
                    @else
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f1f5f9; color: #94a3b8; font-size: 10pt; text-align: center; border: 1px dashed #cbd5e1;">
                            {{ ucfirst($type) }}
                        </div>
                    @endif
                </div>
            @elseif($type === 'qr')
                @php
                    $qrSrc = $el['src'] ?? ($data['verification_qr'] ?? null);
                @endphp
                <div class="designer-element" style="{{ $style }} text-align: center;">
                    @if(!empty($qrSrc))
                        <img src="{{ $qrSrc }}" style="width: 100%; height: 100%; object-fit: contain;" alt="QR Code" />
                    @else
                        <div style="width: 100%; height: 100%; background: #e2e8f0; border: 1px dashed #94a3b8; font-size: 8pt; color: #475569; display: flex; align-items: center; justify-content: center;">
                            QR Code
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</body>
</html>
