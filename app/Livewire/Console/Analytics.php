<?php

namespace App\Livewire\Console;

use App\Models\Area;
use App\Models\LedgerEntry;
use App\Models\WasteBatch;
use App\Services\OpenAiService;
use App\Services\ForecastService;
use App\Services\InsightService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Plain-language questions over the waste graph.
 *
 * The model never sees the database and is never asked to compute anything.
 * We aggregate first, hand it real figures, and ask it only to phrase them —
 * which is why an answer here cannot invent a number.
 */
#[Layout('layouts.console')]
class Analytics extends Component
{
    public string $question = '';
    public ?array $answer = null;
    public bool $thinking = false;

    public array $suggestions = [
        'How much have we verified this month?',
        'Which area generates the most waste?',
        'What is our reject rate telling us?',
        'Where is our margin actually coming from?',
    ];

    public function ask(OpenAiService $ai): void
    {
        if (trim($this->question) === '') {
            return;
        }

        $this->thinking = true;
        $this->answer = $ai->answer($this->question, $this->facts());
        $this->thinking = false;
    }

    public function useSuggestion(string $q, OpenAiService $ai): void
    {
        $this->question = $q;
        $this->ask($ai);
    }

    /** Everything the model is allowed to know, computed by us. */
    private function facts(): array
    {
        $month = now()->startOfMonth();

        $byCategory = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label')
            ->selectRaw('waste_categories.label,
                         ROUND(SUM(verified_kg),1) AS kg,
                         ROUND(SUM(value_naira),2) AS value')
            ->get()
            ->keyBy('label')
            ->map(fn ($r) => ['kg' => (float) $r->kg, 'value_ngn' => (float) $r->value])
            ->toArray();

        $byArea = Area::withCount('households')->get()->mapWithKeys(fn ($a) => [
            $a->name => [
                'households' => $a->households_count,
                'kg' => round((float) WasteBatch::whereHas('household', fn ($h) => $h->where('area_id', $a->id))
                    ->sum('verified_kg'), 1),
            ],
        ])->toArray();

        $total = WasteBatch::count();
        $rejected = WasteBatch::where('status', 'REJECTED')->count();

        return [
            'currency'            => 'NGN',
            'facility'            => config('ecocycle.facility.name'),
            'batch_count'         => $total,
            'area_count'          => Area::count(),
            'household_count'     => \App\Models\Household::count(),
            'agent_count'         => \App\Models\Agent::count(),
            'intake_today_kg'     => round((float) WasteBatch::whereDate('received_at', today())->sum('collected_kg'), 1),
            'verified_kg_month'   => round((float) WasteBatch::where('verified_at', '>=', $month)->sum('verified_kg'), 1),
            'verified_kg_total'   => round((float) WasteBatch::sum('verified_kg'), 1),
            'settled_month_naira' => round((float) LedgerEntry::where('direction', 'credit')
                ->where('created_at', '>=', $month)->sum('amount'), 2),
            'reject_rate_pct'     => $total ? round($rejected / $total * 100, 1) : 0,
            'avg_variance_pct'    => round((float) WasteBatch::whereNotNull('variance_pct')->avg('variance_pct'), 2),
            'split'               => ['household' => 0.6, 'agent' => 0.4],
            'by_category'         => $byCategory,
            'by_area'             => $byArea,
        ];
    }

    /**
     * What each intelligence module is, and how it is actually doing.
     *
     * No invented accuracy figures: prediction reports its measured error,
     * matching reports the share of offered jobs that were taken, classification
     * reports whether a key is configured and how many batches it has graded,
     * and anomaly detection reports its live caseload.
     *
     * @return list<array<string, mixed>>
     */
    private function modules(ForecastService $forecast, OpenAiService $ai): array
    {
        $offered = WasteBatch::whereNotNull('accepted_at')->count();
        $posted = WasteBatch::whereNotNull('agent_id')->count();
        $classified = WasteBatch::whereNotNull('classification')->count();
        $openFlags = \App\Models\IntegrityFlag::open()->count();
        $accuracy = $forecast->accuracy();

        return [
            [
                'icon' => 'trend', 'name' => 'Volume prediction', 'sub' => 'weekday mean, per household',
                'live' => true,
                'metric' => $accuracy !== null ? number_format($accuracy, 0) . '% within actual' : 'building history',
            ],
            [
                'icon' => 'target', 'name' => 'Agent–job matching', 'sub' => 'distance, capacity and rating',
                'live' => true,
                'metric' => $posted ? number_format($offered / $posted * 100, 0) . '% of offers taken' : 'no offers yet',
            ],
            [
                'icon' => 'route', 'name' => 'Route ordering', 'sub' => 'nearest-next from the agent',
                'live' => true,
                'metric' => 'arithmetic, not learned',
            ],
            [
                'icon' => 'camera', 'name' => 'Waste classification', 'sub' => 'from the pickup photograph',
                'live' => $ai->enabled(),
                'metric' => $ai->enabled()
                    ? number_format($classified) . ' batches graded'
                    : 'no API key configured',
            ],
            [
                'icon' => 'shield', 'name' => 'Variance detection', 'sub' => 'declared against the gate scale',
                'live' => true,
                'metric' => $openFlags . ' open ' . str('case')->plural($openFlags),
            ],
            [
                'icon' => 'sparkles', 'name' => 'Standing insights', 'sub' => 'capacity, forecast, yield, coverage',
                'live' => true,
                'metric' => 'recomputed every 10 min',
            ],
        ];
    }

    public function render(OpenAiService $ai, ForecastService $forecast, InsightService $insights, \App\Services\BehaviourForecastService $behaviour)
    {
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d));
        $intake = $days->map(fn ($d) => (float) WasteBatch::whereDate('received_at', $d)->sum('collected_kg'));

        // Where the margin actually comes from -- the finding that shapes strategy.
        $margin = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=',
                DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.color,
                         SUM(verified_kg) AS kg, SUM(value_naira) AS value')
            ->orderByDesc('value')
            ->get();

        // Area performance: volume, growth, households, average pickup and the
        // rejection rate, all over the same 30-day window.
        $areas = $this->areaPerformance();

        $predicted = $forecast->week();
        $predictedTotal = $forecast->weekTotal();
        $capacity = $forecast->weekCapacity();

        return view('livewire.console.analytics', [
            'aiEnabled'  => $ai->enabled(),
            'forecast'   => $predicted,
            'forecastTotal' => $predictedTotal,
            'forecastCapacity' => $capacity,
            'forecastShortfall' => max(0, $predictedTotal - $capacity),
            'forecastAccuracy' => $forecast->accuracy(),
            'dailyCeiling' => $capacity / 6,
            'hours'      => $forecast->byHour(),
            'peakHours'  => $forecast->peakHours(),
            'insights'   => $insights->all(),
            'briefing'   => $behaviour->briefing(),
            'tomorrowByArea' => $behaviour->tomorrowByArea(),
            'weekByCategory' => $behaviour->weekByCategory(),
            'areas'      => $areas,
            'modules'    => $this->modules($forecast, $ai),
            'labels'     => $days->map(fn ($d) => $d->format('D')),
            'intake'     => $intake,
            'margin'     => $margin,
            'marginKg'   => max(0.01, (float) $margin->sum('kg')),
            'marginNgn'  => max(0.01, (float) $margin->sum('value')),
        ])->layoutData(['page' => 'analytics', 'heading' => 'Analytics']);
    }

    /**
     * Per-area performance over the last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    private function areaPerformance(): array
    {
        $since = now()->subDays(30);
        $prior = now()->subDays(60);

        $rows = Area::withCount('households')->get()->map(function ($a) use ($since, $prior) {
            $base = fn () => WasteBatch::whereHas('household', fn ($h) => $h->where('area_id', $a->id));

            $kg = (float) $base()->where('verified_at', '>=', $since)->sum('verified_kg');
            $was = (float) $base()->whereBetween('verified_at', [$prior, $since])->sum('verified_kg');
            $picked = $base()->where('verified_at', '>=', $since)->whereNotNull('verified_kg')->count();
            $total = $base()->where('created_at', '>=', $since)->count();
            $rejected = $base()->where('created_at', '>=', $since)->where('status', 'REJECTED')->count();

            return [
                'name' => $a->name,
                'kg' => $kg,
                'growth' => $was >= 20 ? ($kg - $was) / $was * 100 : 0.0,
                'households' => $a->households_count,
                'avg' => $picked ? $kg / $picked : 0.0,
                'reject_pct' => $total ? $rejected / $total * 100 : 0.0,
            ];
        })->sortByDesc('kg')->values();

        $total = max(0.01, (float) $rows->sum('kg'));

        return $rows->map(fn ($r) => $r + ['share' => $r['kg'] / $total * 100])->all();
    }
}
