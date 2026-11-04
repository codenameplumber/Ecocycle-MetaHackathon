<?php

namespace App\Services;

use App\Models\Area;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use Illuminate\Support\Facades\DB;

/**
 * What the network will probably do next, by place and by stream.
 *
 * Arithmetic, not a model: tomorrow's facility total comes from
 * ForecastService's weekday mean, and this distributes it by each area's
 * recent verified share and names the stream that area actually sends.
 * Confidence is a function of how many verified batches stand behind a
 * row -- a segment built on four batches says so.
 */
class BehaviourForecastService
{
    public function __construct(private ForecastService $forecast) {}

    /**
     * Tomorrow, area by area.
     *
     * @return list<array{area: string, kg: float, batches: float, top_stream: ?string, households: int, confidence: int}>
     */
    public function tomorrowByArea(): array
    {
        $tomorrowKg = collect($this->forecast->week())->first()['kg'] ?? 0.0;

        $rows = DB::table('waste_batches')
            ->join('households', 'households.id', '=', 'waste_batches.household_id')
            ->whereNotNull('waste_batches.verified_kg')
            ->where('waste_batches.verified_at', '>=', now()->subDays(30))
            ->groupBy('households.area_id')
            ->selectRaw('households.area_id, SUM(waste_batches.verified_kg) as kg, COUNT(*) as n')
            ->get()
            ->keyBy('area_id');

        $total = max(0.01, (float) $rows->sum('kg'));
        $avgKg = (float) WasteBatch::whereNotNull('verified_kg')->avg('verified_kg') ?: 10.0;

        return Area::withCount('households')->get()
            ->map(function (Area $area) use ($rows, $total, $tomorrowKg, $avgKg) {
                $row = $rows[$area->id] ?? null;
                $share = $row ? (float) $row->kg / $total : 0.0;
                $kg = $tomorrowKg * $share;

                return [
                    'area' => $area->name,
                    'kg' => round($kg, 1),
                    'share' => round($share * 100, 1),
                    'batches' => $avgKg > 0 ? round($kg / $avgKg, 1) : 0.0,
                    'top_stream' => $this->topStreamForArea($area->id),
                    'households' => $area->households_count,
                    'confidence' => (int) round(min(92, 40 + (($row->n ?? 0) / 40) * 52)),
                ];
            })
            ->sortByDesc('kg')
            ->values()
            ->all();
    }

    /**
     * The next seven days, stream by stream.
     *
     * @return list<array{label: string, key: string, color: ?string, kg: float, share: float}>
     */
    public function weekByCategory(): array
    {
        $weekKg = $this->forecast->weekTotal();

        $rows = WasteBatch::query()
            ->whereNotNull('verified_kg')
            ->where('verified_at', '>=', now()->subDays(30))
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.id', 'waste_categories.label', 'waste_categories.key', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.key, waste_categories.color, SUM(waste_batches.verified_kg) as kg')
            ->get();

        $total = max(0.01, (float) $rows->sum('kg'));

        return $rows->map(fn ($r) => [
            'label' => $r->label,
            'key' => $r->key,
            'color' => $r->color,
            'kg' => round($weekKg * ((float) $r->kg / $total), 1),
            'share' => round((float) $r->kg / $total * 100, 1),
        ])->sortByDesc('kg')->values()->all();
    }

    /** A one-paragraph local briefing, from computed figures only. */
    public function briefing(): string
    {
        $areas = $this->tomorrowByArea();
        $streams = $this->weekByCategory();
        $tomorrow = collect($this->forecast->week())->first();

        if (! $areas || ! $tomorrow) {
            return 'Not enough verified history yet to brief tomorrow. Forecasts appear once collections have been verified.';
        }

        $top = $areas[0];
        $stream = $streams[0] ?? null;

        return sprintf(
            'Tomorrow (%s) is forecast at %s kg across the network. %s is expected to lead with about %s kg, mostly %s. Over the next seven days %s carries the largest share of intake — staff inspection and line time accordingly.',
            $tomorrow['date']->format('l'),
            number_format((float) $tomorrow['kg'], 0),
            $top['area'],
            number_format($top['kg'], 0),
            $top['top_stream'] ? mb_strtolower($top['top_stream']) : 'mixed waste',
            $stream ? $stream['label'] : 'Mixed waste',
        );
    }

    private function topStreamForArea(int $areaId): ?string
    {
        return WasteBatch::query()
            ->whereNotNull('waste_batches.verified_kg')
            ->where('waste_batches.verified_at', '>=', now()->subDays(30))
            ->join('households', 'households.id', '=', 'waste_batches.household_id')
            ->where('households.area_id', $areaId)
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label')
            ->orderByRaw('SUM(waste_batches.verified_kg) DESC')
            ->value('waste_categories.label');
    }
}
