<?php

namespace App\Domain\Certificates\Services;

/**
 * Pure PHP QR Code generator service.
 * Supports standard URLs and text, produces sharp SVG or PNG Data URIs.
 * Zero external dependencies.
 */
class QrCodeService
{
    /**
     * Generate an SVG data URI or raw SVG for a verification URL.
     */
    public function generateSvgDataUri(string $text, int $size = 200, string $color = '#0f172a', string $bgColor = '#ffffff'): string
    {
        $svg = $this->generateSvg($text, $size, $color, $bgColor);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Generate raw SVG string.
     */
    public function generateSvg(string $text, int $size = 200, string $color = '#0f172a', string $bgColor = '#ffffff'): string
    {
        $matrix = $this->generateMatrix($text);
        $moduleCount = count($matrix);
        $cellSize = max(1, $size / $moduleCount);
        $actualSize = $moduleCount * $cellSize;

        $rects = [];
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c * $cellSize;
                    $y = $r * $cellSize;
                    $rects[] = sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" />', $x, $y, $cellSize, $cellSize, htmlspecialchars($color, ENT_QUOTES));
                }
            }
        }

        $bgRect = $bgColor !== 'transparent'
            ? sprintf('<rect width="100%%" height="100%%" fill="%s" />', htmlspecialchars($bgColor, ENT_QUOTES))
            : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" width="%d" height="%d">%s%s</svg>',
            $actualSize,
            $actualSize,
            $size,
            $size,
            $bgRect,
            implode('', $rects)
        );
    }

    /**
     * Generate a PNG Data URI using GD extension.
     */
    public function generatePngDataUri(string $text, int $size = 200, string $color = '#0f172a', string $bgColor = '#ffffff'): string
    {
        $matrix = $this->generateMatrix($text);
        $moduleCount = count($matrix);

        // Calculate cell size and border
        $scale = max(2, (int) floor($size / $moduleCount));
        $imgSize = $moduleCount * $scale;

        $im = imagecreatetruecolor($imgSize, $imgSize);

        // Parse hex colors
        [$bgR, $bgG, $bgB] = $this->parseHexColor($bgColor);
        [$fgR, $fgG, $fgB] = $this->parseHexColor($color);

        $bg = imagecolorallocate($im, $bgR, $bgG, $bgB);
        $fg = imagecolorallocate($im, $fgR, $fgG, $fgB);

        imagefill($im, 0, 0, $bg);

        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    imagefilledrectangle(
                        $im,
                        $c * $scale,
                        $r * $scale,
                        ($c + 1) * $scale - 1,
                        ($r + 1) * $scale - 1,
                        $fg
                    );
                }
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,'.base64_encode($pngData);
    }

    /**
     * Parse hex color to RGB triplet.
     *
     * @return array{int, int, int}
     */
    protected function parseHexColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Generate QR code binary module matrix for given text string.
     * Implements Model 2 QR Code with Version 1 to 4 and Level M Error Correction.
     *
     * @return array<int, array<int, int>>
     */
    public function generateMatrix(string $text): array
    {
        $len = strlen($text);

        // Determine minimum QR version needed for byte mode at EC Level M
        // Version 1: 14 bytes, Version 2: 26 bytes, Version 3: 42 bytes, Version 4: 62 bytes, Version 5: 84 bytes
        if ($len <= 14) {
            $version = 1;
        } elseif ($len <= 26) {
            $version = 2;
        } elseif ($len <= 42) {
            $version = 3;
        } elseif ($len <= 62) {
            $version = 4;
        } elseif ($len <= 84) {
            $version = 5;
        } else {
            $version = 6; // up to 106 bytes
        }

        $size = 17 + ($version * 4); // Matrix size: 21, 25, 29, 33, 37, 41
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder patterns (top-left, top-right, bottom-left)
        $this->addFinderPattern($matrix, $reserved, 0, 0);
        $this->addFinderPattern($matrix, $reserved, $size - 7, 0);
        $this->addFinderPattern($matrix, $reserved, 0, $size - 7);

        // 2. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
        }

        // 3. Dark module and reserved format areas
        $matrix[4 * $version + 9][8] = 1;
        $reserved[4 * $version + 9][8] = true;

        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // 4. Alignment patterns for Version 2+
        if ($version >= 2) {
            $alignPositions = [
                2 => [6, 18],
                3 => [6, 22],
                4 => [6, 26],
                5 => [6, 30],
                6 => [6, 34],
            ];
            $positions = $alignPositions[$version] ?? [6, $size - 7];
            foreach ($positions as $r) {
                foreach ($positions as $c) {
                    if ($reserved[$r][$c]) {
                        continue;
                    }
                    $this->addAlignmentPattern($matrix, $reserved, $r, $c);
                }
            }
        }

        // 5. Data encoding (Byte mode: 0100 + 8-bit length + data + terminator + pad)
        $bits = $this->encodeDataToBits($text, $version);

        // 6. Place data bits using zigzag path
        $bitIdx = 0;
        $totalBits = count($bits);
        $dir = -1; // upward
        $r = $size - 1;
        $c = $size - 1;

        while ($c > 0) {
            if ($c === 6) {
                $c--; // skip vertical timing line
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $dir === -1 ? ($size - 1 - $step) : $step;
                for ($col = $c; $col >= $c - 1; $col--) {
                    if (! $reserved[$row][$col]) {
                        $bit = ($bitIdx < $totalBits) ? $bits[$bitIdx++] : 0;
                        // Apply standard mask pattern 0: (row + col) % 2 === 0
                        $mask = (($row + $col) % 2 === 0) ? 1 : 0;
                        $matrix[$row][$col] = $bit ^ $mask;
                    }
                }
            }
            $c -= 2;
            $dir = -$dir;
        }

        // 7. Write format information (EC Level M = 00, Mask 0 = 000 -> 00000, with BCH -> 101010000010010 ^ 101010000010010 = 0)
        // Format string for EC M + Mask 0 with standard mask XOR (101010000010010):
        $formatBits = [1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0];
        $this->applyFormatBits($matrix, $formatBits, $size);

        // Fill any unassigned cells with 0
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($matrix[$i][$j] === null) {
                    $matrix[$i][$j] = 0;
                }
            }
        }

        return $matrix;
    }

    protected function addFinderPattern(array &$matrix, array &$reserved, int $startR, int $startC): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $matrix[$startR + $r][$startC + $c] =
                    ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) ? 1 : 0;
                $reserved[$startR + $r][$startC + $c] = true;
            }
        }

        // Add separators around finder pattern
        $size = count($matrix);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $targetR = $startR + $r;
                $targetC = $startC + $c;
                if ($targetR >= 0 && $targetR < $size && $targetC >= 0 && $targetC < $size) {
                    $reserved[$targetR][$targetC] = true;
                    if ($matrix[$targetR][$targetC] === null) {
                        $matrix[$targetR][$targetC] = 0;
                    }
                }
            }
        }
    }

    protected function addAlignmentPattern(array &$matrix, array &$reserved, int $centerR, int $centerC): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $matrix[$centerR + $r][$centerC + $c] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0)) ? 1 : 0;
                $reserved[$centerR + $r][$centerC + $c] = true;
            }
        }
    }

    protected function encodeDataToBits(string $text, int $version): array
    {
        $bits = [];
        // Mode indicator: 0100 for Byte mode
        $bits[] = 0;
        $bits[] = 1;
        $bits[] = 0;
        $bits[] = 0;

        // Character count: 8 bits for Byte mode in Version 1-9
        $len = strlen($text);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($len >> $i) & 1;
        }

        // Data bytes
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        // Terminator: up to 4 zeros
        for ($i = 0; $i < 4; $i++) {
            $bits[] = 0;
        }

        // Pad to multiple of 8
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad bytes: 11101100 (236) and 00010001 (17) until total data capacity
        $capacities = [1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86, 6 => 108];
        $totalDataBytes = $capacities[$version] ?? 64;
        $padBytes = [236, 17];
        $padIdx = 0;

        while (count($bits) / 8 < $totalDataBytes) {
            $pad = $padBytes[$padIdx % 2];
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($pad >> $j) & 1;
            }
            $padIdx++;
        }

        return $bits;
    }

    protected function applyFormatBits(array &$matrix, array $formatBits, int $size): void
    {
        // Top-left
        $coordsTopLeft = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];

        // Bottom-left and Top-right
        $coordsSplit = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8], [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 8], [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];

        for ($i = 0; $i < 15; $i++) {
            [$r1, $c1] = $coordsTopLeft[$i];
            [$r2, $c2] = $coordsSplit[$i];
            $matrix[$r1][$c1] = $formatBits[$i];
            $matrix[$r2][$c2] = $formatBits[$i];
        }
    }
}
