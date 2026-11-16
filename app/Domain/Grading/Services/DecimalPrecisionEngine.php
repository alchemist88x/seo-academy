<?php

namespace App\Domain\Grading\Services;

use DivisionByZeroError;
use InvalidArgumentException;

class DecimalPrecisionEngine
{
    /**
     * Calculation precision scale (16 decimal places) for all intermediate
     * arithmetic, category calculations, and weight distributions.
     */
    public const INTERNAL_SCALE = 16;

    public const DEFAULT_SCALE = self::INTERNAL_SCALE;

    /**
     * Official academic reporting and final transcript grade scale (2 decimal places).
     * Only the final composite grade is rounded to this scale.
     */
    public const FINAL_GRADE_SCALE = 2;

    /**
     * Add two arbitrary precision numbers.
     */
    public static function add(string|int|float $a, string|int|float $b, int $scale = self::INTERNAL_SCALE): string
    {
        $cleanA = self::sanitize($a);
        $cleanB = self::sanitize($b);

        $result = bcadd($cleanA, $cleanB, $scale);

        return self::normalizeZero($result, $scale);
    }

    /**
     * Subtract $b from $a with arbitrary precision.
     */
    public static function sub(string|int|float $a, string|int|float $b, int $scale = self::INTERNAL_SCALE): string
    {
        $cleanA = self::sanitize($a);
        $cleanB = self::sanitize($b);

        $result = bcsub($cleanA, $cleanB, $scale);

        return self::normalizeZero($result, $scale);
    }

    /**
     * Multiply two arbitrary precision numbers.
     */
    public static function mul(string|int|float $a, string|int|float $b, int $scale = self::INTERNAL_SCALE): string
    {
        $cleanA = self::sanitize($a);
        $cleanB = self::sanitize($b);

        $result = bcmul($cleanA, $cleanB, $scale);

        return self::normalizeZero($result, $scale);
    }

    /**
     * Divide $a by $b with arbitrary precision.
     *
     * @throws DivisionByZeroError
     */
    public static function div(string|int|float $a, string|int|float $b, int $scale = self::INTERNAL_SCALE): string
    {
        $cleanA = self::sanitize($a);
        $cleanB = self::sanitize($b);

        if (bccomp($cleanB, '0', $scale + 4) === 0) {
            throw new DivisionByZeroError('Division by zero encountered in DecimalPrecisionEngine');
        }

        $result = bcdiv($cleanA, $cleanB, $scale);

        return self::normalizeZero($result, $scale);
    }

    /**
     * Compare two arbitrary precision numbers.
     * Returns:
     *  -1 if $a < $b
     *   0 if $a == $b
     *   1 if $a > $b
     */
    public static function compare(string|int|float $a, string|int|float $b, int $scale = self::INTERNAL_SCALE): int
    {
        $cleanA = self::sanitize($a);
        $cleanB = self::sanitize($b);

        return bccomp($cleanA, $cleanB, $scale);
    }

    /**
     * Round half-up to the specified number of decimal places.
     * Guaranteed exact mathematical rounding without native IEEE-754 floating point artifacts.
     */
    public static function roundHalfUp(string|int|float $value, int $scale = self::FINAL_GRADE_SCALE): string
    {
        $val = self::sanitize($value);
        $negative = str_starts_with($val, '-');
        $sign = $negative ? '-' : '';
        $abs = $negative ? substr($val, 1) : $val;

        if (! str_contains($abs, '.')) {
            $res = $scale > 0 ? $abs.'.'.str_repeat('0', $scale) : $abs;

            return ($negative && bccomp($res, '0', $scale) !== 0) ? '-'.$res : $res;
        }

        $parts = explode('.', $abs);
        $intPart = $parts[0];
        $decPart = $parts[1] ?? '';

        if (strlen($decPart) <= $scale) {
            $padded = str_pad($decPart, $scale, '0');
            $res = $scale > 0 ? "{$intPart}.{$padded}" : $intPart;

            return ($negative && bccomp($res, '0', $scale) !== 0) ? '-'.$res : $res;
        }

        $increment = '0.'.str_repeat('0', $scale).'5';
        $internalBufferScale = max(strlen($abs), $scale + 8);
        $added = bcadd($abs, $increment, $internalBufferScale);

        $dotPos = strpos($added, '.');
        $truncated = $scale > 0 ? substr($added, 0, $dotPos + 1 + $scale) : substr($added, 0, $dotPos);

        if (bccomp($truncated, '0', $scale) === 0) {
            return $scale > 0 ? '0.'.str_repeat('0', $scale) : '0';
        }

        return $sign.$truncated;
    }

    /**
     * Calculate percentage: ($earned / $max) * 100
     * By default computes to high internal calculation scale (16 decimal places) without premature rounding.
     */
    public static function percentage(
        string|int|float $earned,
        string|int|float $max,
        int $scale = self::INTERNAL_SCALE
    ): string {
        $cleanEarned = self::sanitize($earned);
        $cleanMax = self::sanitize($max);

        if (bccomp($cleanMax, '0', $scale + 4) === 0) {
            throw new DivisionByZeroError('Max score cannot be zero in percentage calculation');
        }

        // Compute with guard precision
        $intermediate = bcdiv(bcmul($cleanEarned, '100', $scale + 8), $cleanMax, $scale + 4);

        return self::roundHalfUp($intermediate, $scale);
    }

    /**
     * Calculate weighted score contribution: ($score * $weightPercent) / 100
     * By default computes to high internal calculation scale (16 decimal places) without premature rounding.
     */
    public static function weightedScore(
        string|int|float $score,
        string|int|float $weightPercent,
        int $scale = self::INTERNAL_SCALE
    ): string {
        $cleanScore = self::sanitize($score);
        $cleanWeight = self::sanitize($weightPercent);

        $product = bcmul($cleanScore, $cleanWeight, $scale + 8);
        $intermediate = bcdiv($product, '100', $scale + 4);

        return self::roundHalfUp($intermediate, $scale);
    }

    /**
     * Determine whether an academic score meets or exceeds a passing threshold.
     * The rounded final percentage determines passing.
     */
    public static function isPassing(
        string|int|float $finalScore,
        string|int|float $passingThreshold,
        int $scale = self::FINAL_GRADE_SCALE
    ): bool {
        $roundedFinal = self::roundHalfUp($finalScore, $scale);
        $roundedThreshold = self::roundHalfUp($passingThreshold, $scale);

        return self::compare($roundedFinal, $roundedThreshold, $scale) >= 0;
    }

    /**
     * Compute a full cumulative grade across multiple weighted components.
     * Retains 16 decimal places of precision throughout all component aggregations,
     * and applies exact half-up rounding ONLY to the final composite percentage.
     *
     * @param  array<int, array{score: string|int|float, weight: string|int|float}>  $components
     * @return array{
     *     unrounded_score: string,
     *     final_score: string,
     *     is_passing: bool,
     *     stages: array<int, array{score: string, weight: string, contribution: string}>
     * }
     */
    public static function calculateCumulativeFinalGrade(
        array $components,
        string|int|float $passingThreshold = '70.00',
        int $finalScale = self::FINAL_GRADE_SCALE
    ): array {
        $runningSum16 = '0.'.str_repeat('0', self::INTERNAL_SCALE);
        $stages = [];

        foreach ($components as $comp) {
            $score = (string) $comp['score'];
            $weight = (string) $comp['weight'];

            // Calculate contribution at 16 decimal places
            $contrib16 = self::weightedScore($score, $weight, self::INTERNAL_SCALE);
            $runningSum16 = self::add($runningSum16, $contrib16, self::INTERNAL_SCALE);

            $stages[] = [
                'score' => $score,
                'weight' => $weight,
                'contribution' => $contrib16,
            ];
        }

        $finalRounded = self::roundHalfUp($runningSum16, $finalScale);
        $passing = self::isPassing($finalRounded, $passingThreshold, $finalScale);

        return [
            'unrounded_score' => $runningSum16,
            'final_score' => $finalRounded,
            'is_passing' => $passing,
            'stages' => $stages,
        ];
    }

    /**
     * Check if a value is within [$min, $max] inclusive.
     */
    public static function boundsCheck(
        string|int|float $value,
        string|int|float $min = '0',
        string|int|float $max = '100',
        int $scale = self::INTERNAL_SCALE
    ): bool {
        $cleanVal = self::sanitize($value);
        $cleanMin = self::sanitize($min);
        $cleanMax = self::sanitize($max);

        return bccomp($cleanVal, $cleanMin, $scale) >= 0 && bccomp($cleanVal, $cleanMax, $scale) <= 0;
    }

    /**
     * Assert that a score is within allowed academic bounds [min, max].
     *
     * @throws InvalidArgumentException
     */
    public static function assertScoreWithinBounds(
        string|int|float $score,
        string|int|float $min = '0',
        string|int|float $max = '100',
        int $scale = self::INTERNAL_SCALE
    ): void {
        if (! self::boundsCheck($score, $min, $max, $scale)) {
            $val = self::sanitize($score);
            $minVal = self::sanitize($min);
            $maxVal = self::sanitize($max);
            throw new InvalidArgumentException("Score '{$val}' is outside permitted academic bounds [{$minVal}, {$maxVal}].");
        }
    }

    /**
     * Convert raw points earned out of max points to an arbitrary-precision percentage.
     * Raw score separation: strictly validates earned points, max points, and non-negativity.
     *
     * @throws InvalidArgumentException
     * @throws DivisionByZeroError
     */
    public static function rawToPercentage(
        string|int|float $earnedPoints,
        string|int|float $maxPoints,
        int $scale = self::INTERNAL_SCALE,
        ?string $maxPermittedPoints = null
    ): string {
        $cleanEarned = self::sanitize($earnedPoints);
        $cleanMax = self::sanitize($maxPoints);

        if (bccomp($cleanEarned, '0', $scale) < 0) {
            throw new InvalidArgumentException("Raw score cannot be negative: '{$cleanEarned}'");
        }

        if (bccomp($cleanMax, '0', $scale) <= 0) {
            throw new DivisionByZeroError("Max points must be greater than zero: '{$cleanMax}'");
        }

        if ($maxPermittedPoints !== null) {
            $cleanPermitted = self::sanitize($maxPermittedPoints);
            if (bccomp($cleanEarned, $cleanPermitted, $scale) > 0) {
                throw new InvalidArgumentException("Raw score '{$cleanEarned}' exceeds maximum permitted points '{$cleanPermitted}'");
            }
        }

        return self::percentage($cleanEarned, $cleanMax, $scale);
    }

    /**
     * Redistribute weights proportionally when one or more assessments/categories are exempted,
     * ensuring total weight sums to exactly 100.000...% without truncation or rounding drift.
     *
     * @param  array<string|int, string|int|float>  $activeWeights  Map of [key => weight_percentage]
     * @param  int  $scale  Precision scale (default 16)
     * @return array<string|int, string> Map of [key => normalized_weight_percentage]
     */
    public static function redistributeWeights(array $activeWeights, int $scale = self::INTERNAL_SCALE): array
    {
        if (empty($activeWeights)) {
            return [];
        }

        $totalActiveWeight = '0';
        foreach ($activeWeights as $w) {
            $cleanW = self::sanitize($w);
            if (bccomp($cleanW, '0', $scale) < 0) {
                throw new InvalidArgumentException("Weight cannot be negative: '{$cleanW}'");
            }
            $totalActiveWeight = bcadd($totalActiveWeight, $cleanW, $scale + 8);
        }

        if (bccomp($totalActiveWeight, '0', $scale) === 0) {
            throw new InvalidArgumentException('Total active weight must be greater than zero.');
        }

        $redistributed = [];
        $runningSum = '0.'.str_repeat('0', $scale);
        $keys = array_keys($activeWeights);
        $lastKey = end($keys);

        foreach ($activeWeights as $key => $weight) {
            if ($key === $lastKey) {
                // To prevent rounding drift, last element receives exactly (100 - runningSum)
                $remainder = bcsub('100', $runningSum, $scale);
                $redistributed[$key] = self::normalizeZero($remainder, $scale);
            } else {
                $cleanWeight = self::sanitize($weight);
                $share = bcdiv(bcmul($cleanWeight, '100', $scale + 8), $totalActiveWeight, $scale + 4);
                $roundedShare = self::roundHalfUp($share, $scale);
                $redistributed[$key] = $roundedShare;
                $runningSum = bcadd($runningSum, $roundedShare, $scale);
            }
        }

        return $redistributed;
    }

    /**
     * Format a value to exact decimal scale.
     */
    public static function format(string|int|float $value, int $scale = self::FINAL_GRADE_SCALE): string
    {
        return self::roundHalfUp($value, $scale);
    }

    /**
     * Sanitize input into a clean numeric string.
     */
    protected static function sanitize(string|int|float $value): string
    {
        $val = trim((string) $value);

        if (! is_numeric($val)) {
            throw new InvalidArgumentException("Invalid numeric string passed to DecimalPrecisionEngine: '{$val}'");
        }

        return $val;
    }

    /**
     * Normalize negative zeroes (-0, -0.00, -0.0000000000000000) to positive zero.
     */
    public static function normalizeZero(string $value, int $scale): string
    {
        if (bccomp($value, '0', $scale) === 0) {
            return $scale > 0 ? '0.'.str_repeat('0', $scale) : '0';
        }

        return $value;
    }
}
