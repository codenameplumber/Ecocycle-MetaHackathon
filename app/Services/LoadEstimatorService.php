<?php

namespace App\Services;

use App\Models\Household;
use App\Models\WasteCategory;

/**
 * A photo-first weight estimate, expressed as a range.
 *
 * A photograph cannot weigh waste: it cannot see inside a closed bag, and a
 * bag of bottles and a bag of organics occupy the same volume at very
 * different weights. So this never pretends to a point figure. It blends
 * three signals that each know part of the answer:
 *
 *   1. Bag count x a per-stream density for a standard refuse bag.
 *   2. The household's own verified history (Household::predictedKg()).
 *   3. When a classification exists, its mix nudges the density.
 *
 * The output is advisory only. It fills expected_kg, it is what an agent
 * sees before travelling, and it never settles money -- verified_kg does.
 */
class LoadEstimatorService
{
    /** Kg per full standard refuse bag, by stream. */
    private const KG_PER_BAG = [
        'organic' => 11.0,
        'plastic' => 3.8,
        'paper'   => 5.2,
        'metal'   => 9.0,
        'glass'   => 13.5,
        'ewaste'  => 8.0,
        'mixed'   => 7.0,
    ];

    /**
     * @param  array<string, mixed>|null  $classification  OpenAiService output, if a photo was read
     * @return array{low: float, mid: float, high: float, confidence: int, label: string, basis: list<string>}
     */
    public function estimate(?Household $household, ?WasteCategory $category, int $bags = 2, ?array $classification = null): array
    {
        $bags = max(1, min(20, $bags));
        $key = $category?->key ?? 'mixed';

        $perBag = self::KG_PER_BAG[$key] ?? self::KG_PER_BAG['mixed'];

        // A read photograph refines the density: weight the per-bag figure
        // by the mix the model saw, when it saw one.
        $basis = [sprintf('%d bag%s of %s', $bags, $bags === 1 ? '' : 's', $category?->label ?? 'mixed waste')];

        if (! empty($classification['mix']) && is_array($classification['mix'])) {
            $weighted = 0.0;
            $total = 0;
            foreach ($classification['mix'] as $stream => $pct) {
                $weighted += (self::KG_PER_BAG[$stream] ?? self::KG_PER_BAG['mixed']) * (int) $pct;
                $total += (int) $pct;
            }
            if ($total > 0) {
                $perBag = $weighted / $total;
                $basis[] = 'photo composition read';
            }
        }

        $bagEstimate = $perBag * $bags;
        $mid = $bagEstimate;
        $confidence = 45;

        // History calibrates: a household that reliably sends 9 kg should
        // not be estimated at 22 kg because the bags looked full.
        if ($household && $household->batches()->whereNotNull('verified_kg')->exists()) {
            $history = $household->predictedKg();
            $mid = ($bagEstimate * 0.65) + ($history * 0.35);
            $confidence = 68;
            $basis[] = 'your last eight verified collections';
        }

        if (! empty($classification['generated'])) {
            $confidence = min(88, $confidence + 12);
        }

        $spread = $confidence >= 65 ? 0.22 : 0.38;

        return [
            'low' => round(max(0.5, $mid * (1 - $spread)), 1),
            'mid' => round($mid, 1),
            'high' => round($mid * (1 + $spread), 1),
            'confidence' => $confidence,
            'label' => match (true) {
                $confidence >= 75 => 'High',
                $confidence >= 55 => 'Medium',
                default => 'Low',
            },
            'basis' => $basis,
        ];
    }
}
