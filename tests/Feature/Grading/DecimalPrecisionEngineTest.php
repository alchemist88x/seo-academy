<?php

namespace Tests\Feature\Grading;

use App\Domain\Grading\Services\DecimalPrecisionEngine;
use DivisionByZeroError;
use InvalidArgumentException;
use Tests\TestCase;

class DecimalPrecisionEngineTest extends TestCase
{
    public function test_raw_score_separation_and_validation(): void
    {
        // 45 earned out of 50 max points -> 90% (computed at 16 decimal places)
        $pct16 = DecimalPrecisionEngine::rawToPercentage('45', '50');
        $this->assertSame('90.0000000000000000', $pct16);

        // Explicit scale 4 request
        $pct4 = DecimalPrecisionEngine::rawToPercentage('45', '50', 4);
        $this->assertSame('90.0000', $pct4);

        // 37 earned out of 40 max points -> 92.5%
        $this->assertSame('92.5000000000000000', DecimalPrecisionEngine::rawToPercentage('37', '40'));

        // Zero earned out of 100 max points
        $this->assertSame('0.0000000000000000', DecimalPrecisionEngine::rawToPercentage('0', '100'));

        // Genuinely negative raw score must be strictly rejected
        $this->expectException(InvalidArgumentException::class);
        DecimalPrecisionEngine::rawToPercentage('-5', '50');
    }

    public function test_raw_score_zero_max_points_rejected(): void
    {
        $this->expectException(DivisionByZeroError::class);
        DecimalPrecisionEngine::rawToPercentage('10', '0');
    }

    public function test_maximum_permitted_scores_enforcement(): void
    {
        // Valid score at max permitted
        $pct = DecimalPrecisionEngine::rawToPercentage('500', '500', 16, '500');
        $this->assertSame('100.0000000000000000', $pct);

        // Exceeding maximum permitted raw score
        $this->expectException(InvalidArgumentException::class);
        DecimalPrecisionEngine::rawToPercentage('500.01', '500', 16, '500');
    }

    public function test_boundary_checks_and_assertions(): void
    {
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('0', '0', '100'));
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('100', '0', '100'));
        $this->assertTrue(DecimalPrecisionEngine::boundsCheck('75.25', '0', '100'));

        $this->assertFalse(DecimalPrecisionEngine::boundsCheck('-0.0000000000000001', '0', '100'));
        $this->assertFalse(DecimalPrecisionEngine::boundsCheck('100.0000000000000001', '0', '100'));

        // assertScoreWithinBounds does not throw for valid values
        DecimalPrecisionEngine::assertScoreWithinBounds('85.50', '0', '100');

        // Throws for out of bounds
        $this->expectException(InvalidArgumentException::class);
        DecimalPrecisionEngine::assertScoreWithinBounds('105.00', '0', '100');
    }

    public function test_zero_negative_normalization(): void
    {
        // Normalizes -0, -0.00, -0.0000000000000000 to positive zero
        $this->assertSame('0.00', DecimalPrecisionEngine::roundHalfUp('-0.00', 2));
        $this->assertSame('0.0000', DecimalPrecisionEngine::roundHalfUp('-0.0000', 4));
        $this->assertSame('0.0000000000000000', DecimalPrecisionEngine::roundHalfUp('-0.0000000000000000', 16));
        $this->assertSame('0.00', DecimalPrecisionEngine::roundHalfUp('-0', 2));

        // Subtraction and addition resulting in zero
        $this->assertSame('0.0000000000000000', DecimalPrecisionEngine::sub('5.00', '5.00'));
        $this->assertSame('0.0000000000000000', DecimalPrecisionEngine::add('-5.00', '5.00'));
    }

    public function test_no_global_bcscale_mutation(): void
    {
        $originalScale = bcscale();

        DecimalPrecisionEngine::add('1.2345', '2.3456');
        DecimalPrecisionEngine::percentage('25', '75');
        DecimalPrecisionEngine::weightedScore('80', '25');

        $this->assertSame($originalScale, bcscale(), 'Global bcscale must never be mutated by DecimalPrecisionEngine');
    }

    public function test_values_immediately_below_at_and_above_half_up_boundaries(): void
    {
        // Boundary around 85.455 at scale 2:
        // Immediately below boundary: 85.4549999999999999 -> rounds to 85.45
        $this->assertSame('85.45', DecimalPrecisionEngine::roundHalfUp('85.4549999999999999', 2));

        // Exactly at boundary: 85.4550000000000000 -> rounds half-up to 85.46
        $this->assertSame('85.46', DecimalPrecisionEngine::roundHalfUp('85.4550000000000000', 2));

        // Immediately above boundary: 85.4550000000000001 -> rounds to 85.46
        $this->assertSame('85.46', DecimalPrecisionEngine::roundHalfUp('85.4550000000000001', 2));

        // Boundary around 69.995 at scale 2:
        $this->assertSame('69.99', DecimalPrecisionEngine::roundHalfUp('69.9949999999999999', 2));
        $this->assertSame('70.00', DecimalPrecisionEngine::roundHalfUp('69.9950000000000000', 2));
        $this->assertSame('70.00', DecimalPrecisionEngine::roundHalfUp('69.9950000000000001', 2));
    }

    public function test_pass_thresholds_immediately_around_rounding_boundary(): void
    {
        $passingGrade = '70.00';

        // 1. Below boundary: 69.9949999999999999
        // Unrounded is < 70; rounds to 69.99 -> FAILS
        $scoreBelow = '69.9949999999999999';
        $roundedBelow = DecimalPrecisionEngine::roundHalfUp($scoreBelow, 2);
        $this->assertSame('69.99', $roundedBelow);
        $this->assertFalse(DecimalPrecisionEngine::isPassing($scoreBelow, $passingGrade));

        // 2. Exactly at boundary: 69.9950000000000000
        // Rounds half-up to 70.00 -> PASSES
        $scoreAt = '69.9950000000000000';
        $roundedAt = DecimalPrecisionEngine::roundHalfUp($scoreAt, 2);
        $this->assertSame('70.00', $roundedAt);
        $this->assertTrue(DecimalPrecisionEngine::isPassing($scoreAt, $passingGrade));

        // 3. Immediately above boundary: 69.9950000000000001
        // Rounds to 70.00 -> PASSES
        $scoreAbove = '69.9950000000000001';
        $roundedAbove = DecimalPrecisionEngine::roundHalfUp($scoreAbove, 2);
        $this->assertSame('70.00', $roundedAbove);
        $this->assertTrue(DecimalPrecisionEngine::isPassing($scoreAbove, $passingGrade));
    }

    public function test_very_small_fractional_values_and_micro_contributions(): void
    {
        $tinyValueA = '0.0000000000000001';
        $tinyValueB = '0.0000000000000002';

        $sum = DecimalPrecisionEngine::add($tinyValueA, $tinyValueB);
        $this->assertSame('0.0000000000000003', $sum);

        // 1 point out of 1,000,000 with 0.01% weight
        $pct = DecimalPrecisionEngine::percentage('1', '1000000');
        $this->assertSame('0.0001000000000000', $pct);

        $microContrib = DecimalPrecisionEngine::weightedScore($pct, '0.01');
        $this->assertSame('0.0000000100000000', $microContrib);
    }

    public function test_multiple_weighted_assessments_across_multiple_categories(): void
    {
        // Category 1: Assignments (40% total course weight)
        // - Assignment 1: 45 / 50 (90.0000%) with 40% category share -> 16.00% course weight
        // - Assignment 2: 88 / 95 (92.6315789473684211%) with 60% category share -> 24.00% course weight
        $asmt1Pct = DecimalPrecisionEngine::percentage('45', '50'); // 90.0000000000000000
        $asmt1Contrib = DecimalPrecisionEngine::weightedScore($asmt1Pct, '16.0000000000000000'); // 14.4000000000000000

        $asmt2Pct = DecimalPrecisionEngine::percentage('88', '95'); // 92.6315789473684211
        $asmt2Contrib = DecimalPrecisionEngine::weightedScore($asmt2Pct, '24.0000000000000000'); // 22.2315789473684211

        $cat1Contrib = DecimalPrecisionEngine::add($asmt1Contrib, $asmt2Contrib); // 36.6315789473684211
        $this->assertSame('36.6315789473684211', $cat1Contrib);

        // Category 2: Final Capstone Exam (60% total course weight)
        // - Exam: 172 / 200 (86.0000%) with 100% category share -> 60.00% course weight
        $examPct = DecimalPrecisionEngine::percentage('172', '200'); // 86.0000000000000000
        $cat2Contrib = DecimalPrecisionEngine::weightedScore($examPct, '60.0000000000000000'); // 51.6000000000000000
        $this->assertSame('51.6000000000000000', $cat2Contrib);

        // Unrounded composite sum across categories (retains 16 decimal places)
        $unroundedFinal = DecimalPrecisionEngine::add($cat1Contrib, $cat2Contrib);
        // 36.6315789473684211 + 51.6000000000000000 = 88.2315789473684211
        $this->assertSame('88.2315789473684211', $unroundedFinal);

        // ONLY the final composite grade is rounded to 2 decimal places
        $finalGrade = DecimalPrecisionEngine::roundHalfUp($unroundedFinal, 2);
        $this->assertSame('88.23', $finalGrade);

        // Verify with calculateCumulativeFinalGrade pipeline
        $pipeline = DecimalPrecisionEngine::calculateCumulativeFinalGrade([
            ['score' => $asmt1Pct, 'weight' => '16'],
            ['score' => $asmt2Pct, 'weight' => '24'],
            ['score' => $examPct, 'weight' => '60'],
        ], '70.00');

        $this->assertSame('88.2315789473684211', $pipeline['unrounded_score']);
        $this->assertSame('88.23', $pipeline['final_score']);
        $this->assertTrue($pipeline['is_passing']);
    }

    public function test_exempted_category_weight_redistribution_at_16_scale(): void
    {
        // 4 categories originally weighted:
        // Cat A: 20%, Cat B: 25%, Cat C: 15%, Cat D: 40% (Sum: 100%)
        // Cat C (15%) is exempted due to formal medical accommodation.
        // Remaining active weights: Cat A (20), Cat B (25), Cat D (40) -> Total active = 85%
        $activeWeights = [
            'cat_a' => '20.0000000000000000',
            'cat_b' => '25.0000000000000000',
            'cat_d' => '40.0000000000000000',
        ];

        $redistributed = DecimalPrecisionEngine::redistributeWeights($activeWeights, 16);

        // Share A: 20 / 85 * 100 = 23.5294117647058824
        // Share B: 25 / 85 * 100 = 29.4117647058823529
        // Share D: 100 - (Share A + Share B) = 47.0588235294117647
        $this->assertSame('23.5294117647058824', $redistributed['cat_a']);
        $this->assertSame('29.4117647058823529', $redistributed['cat_b']);
        $this->assertSame('47.0588235294117647', $redistributed['cat_d']);

        // Sum must equal exactly 100.0000000000000000 without any precision drift
        $sum = DecimalPrecisionEngine::add(
            DecimalPrecisionEngine::add($redistributed['cat_a'], $redistributed['cat_b']),
            $redistributed['cat_d']
        );
        $this->assertSame('100.0000000000000000', $sum);
    }
}
