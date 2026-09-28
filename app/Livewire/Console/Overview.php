<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Delivery;
use App\Models\Facility;
use App\Models\Household;
use App\Models\IntegrityFlag;
use App\Models\LedgerEntry;
use App\Models\ProcessingLine;
use App\Models\WasteBatch;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.console')]
class Overview extends Component
{
    public function render()
    {
        $today = now()->startOfDay();

        $intakeToday = (float) WasteBatch::whereDate('received_at', $today)->sum('collected_kg');
        // Counted over the same rows as the weight above it, so the tile cannot
        // read "0 kg across 4 batches".
        $receivedTodayCount = WasteBatch::whereDate('received_at', $today)->count();
        $processedToday = (float) WasteBatch::whereDate('processed_at', $today)->sum('verified_kg');
        $awaiting = WasteBatch::awaitingInspection();
        $awaitingKg = (float) $awaiting->clone()->sum(DB::raw('COALESCE(collected_kg, expected_kg, 0)'));

        // Fourteen days of received vs processed, for the intake chart.
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $received = $days->map(fn ($d) => (float) WasteBatch::whereDate('received_at', $d)->sum('collected_kg'));
        $processed = $days->map(fn ($d) => (float) WasteBatch::whereDate('processed_at', $d)->sum('verified_kg'));

        $mix = WasteBatch::query()
            ->settleable()
            ->join('waste_categories', 'waste_categories.id', '=', DB::raw('COALESCE(waste_batches.confirmed_category_id, waste_batches.waste_category_id)'))
            ->groupBy('waste_categories.label', 'waste_categories.color')
            ->selectRaw('waste_categories.label, waste_categories.color, SUM(verified_kg) AS kg')
            ->orderByDesc('kg')
            ->get();
        $mixTotal = max(0.01, (float) $mix->sum('kg'));

        $lines = ProcessingLine::with('category')->get();
        $dailyCapacity = (float) (Facility::first()?->daily_capacity_kg ?: 9500);

        return view('livewire.console.overview', [
            'intakeToday'    => $intakeToday,
            'receivedTodayCount' => $receivedTodayCount,
            'processedToday' => $processedToday,
            'awaitingCount'  => $awaiting->clone()->count(),
            'awaitingKg'     => $awaitingKg,
            'settledMonth'   => (float) LedgerEntry::where('direction', 'credit')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'openAlerts'     => IntegrityFlag::open()->count(),
            'agentsOnline'   => Agent::where('is_online', true)->count(),
            'inTransit'      => WasteBatch::where('status', BatchStatus::InTransit->value)->count(),
            'chartLabels'    => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('j M')),
            'chartReceived'  => $received,
            'chartProcessed' => $processed,
            'mix'            => $mix,
            'mixTotal'       => $mixTotal,
            'lines'          => $lines,
            'dailyCapacity'  => $dailyCapacity,
            'capacityUsed'   => $dailyCapacity > 0 ? min(100, $intakeToday / $dailyCapacity * 100) : 0,
            // The single line closest to its ceiling, so the callout can name it
            // and say by how much rather than warning in general terms.
            'strainedLine'   => $lines->where('utilisation', '>', 85)->sortByDesc('utilisation')->first(),
            // Whatever is currently standing on the weighbridge, if anything.
            'atGate'         => Delivery::with('agent')
                ->whereIn('status', ['open', 'weighed'])
                ->latest('created_at')
                ->first(),
            'pins'           => $this->pins(),
            'counts'         => collect(BatchStatus::pipeline())
                ->mapWithKeys(fn ($s) => [$s->value => WasteBatch::where('status', $s->value)->count()]),
            'alerts'         => IntegrityFlag::open()->with(['agent.user', 'batch'])->latest()->limit(5)->get(),
            'latest'         => WasteBatch::with(['household.user', 'agent.user', 'category'])
                ->latest('created_at')->limit(8)->get(),
        ])->layoutData(['page' => 'overview', 'heading' => 'Overview']);
    }

    /**
     * Pins for the live network map: a sample of households for context, every
     * agent currently on shift, and the plant. Agents are not sampled -- an
     * operator watching this screen needs all of them.
     *
     * @return list<array<string, mixed>>
     */
    private function pins(): array
    {
        $pins = Household::whereNotNull('lat')
            ->inRandomOrder()
            ->limit(70)
            ->get(['lat', 'lng'])
            ->map(fn ($h) => Geo::pin((float) $h->lat, (float) $h->lng, 'house'))
            ->all();

        foreach (Agent::where('is_online', true)->whereNotNull('last_lat')->get(['last_lat', 'last_lng']) as $a) {
            $pins[] = Geo::pin((float) $a->last_lat, (float) $a->last_lng, 'agent', live: true);
        }

        $f = Facility::first();
        $pins[] = Geo::pin((float) ($f?->lat ?? 6.5794), (float) ($f?->lng ?? 3.3792), 'plant', label: $f?->name ?? 'Lagos Facility');

        return $pins;
    }
}
