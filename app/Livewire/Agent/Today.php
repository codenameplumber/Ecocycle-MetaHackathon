<?php

namespace App\Livewire\Agent;

use App\Enums\BatchStatus;
use App\Services\JobMatcher;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AcceptsJobs;
use App\Livewire\Concerns\TracksAgentPosition;
use Livewire\Component;

#[Layout('layouts.phone')]
class Today extends Component
{
    use AcceptsJobs;
    use TracksAgentPosition;

    /**
     * Go on or off shift.
     *
     * Being offline stops new jobs being offered; it does not hide the
     * marketplace, and it never abandons a load already on board.
     */
    public function toggleOnline(): void
    {
        $agent = auth()->user()->agent;
        $agent->update(['is_online' => ! $agent->is_online, 'last_seen_at' => now()]);

        $this->dispatch(
            'toast',
            message: $agent->is_online ? 'You are online — jobs will appear' : 'You are offline',
            kind: $agent->is_online ? 'ok' : 'warn',
        );
    }

    public function render(JobMatcher $matcher)
    {
        $agent = auth()->user()->agent;

        $current = $agent->batches()
            ->whereIn('status', [BatchStatus::Accepted->value, BatchStatus::Collected->value, BatchStatus::InTransit->value])
            ->with(['household.user', 'category'])
            ->latest('accepted_at')
            ->first();

        $earnedToday = (float) $agent->ledgerEntries()
            ->where('direction', 'credit')
            ->whereDate('created_at', today())
            ->sum('amount');

        $weekKg = (float) $agent->batches()
            ->where('verified_at', '>=', now()->startOfWeek())
            ->sum('verified_kg');

        // Seven days of collected weight and what it earned, Monday first, so
        // the bar chart and the three figures below it agree with each other.
        $week = collect(range(6, 0))->map(function ($d) use ($agent) {
            $day = now()->subDays($d);

            return [
                'label' => $day->isoFormat('dd')[0],
                'kg' => (float) $agent->batches()->whereDate('collected_at', $day)->sum('collected_kg'),
            ];
        });

        $weekStart = now()->startOfWeek();
        $priorKg = (float) $agent->batches()
            ->whereBetween('collected_at', [$weekStart->copy()->subWeek(), $weekStart])
            ->sum('collected_kg');
        $thisKg = (float) $week->sum('kg');

        return view('livewire.agent.today', [
            'agent'       => $agent,
            'week'        => $week,
            'weekEarned'  => (float) $agent->batches()
                ->where('verified_at', '>=', $weekStart)->sum('agent_share'),
            'weekCollected' => $thisKg,
            'weekDelta'   => $priorKg > 0 ? ($thisKg - $priorKg) / $priorKg * 100 : null,
            'current'     => $current,
            'earnedToday' => $earnedToday,
            'balance'     => $agent->balance(),
            'onBoard'     => $agent->loadOnBoardKg(),
            'remaining'   => $agent->remainingCapacityKg(),
            'held'        => $agent->batches()->whereIn('status', ['COLLECTED', 'IN_TRANSIT'])->count(),
            'jobsToday'   => $agent->batches()->whereDate('accepted_at', today())->count(),
            'weekKg'      => $weekKg,
            'capacity'    => $agent->capacityState(),
            'lastHourKg'  => $agent->collectedLastHourKg(),
            'nearby'      => $matcher->rank($agent, 3),
        ])->layoutData([
            'app' => 'agent', 'page' => 'today',
            'heading' => 'Today',
            'sub' => auth()->user()->name . ' · ' . $agent->vehicle_type,
        ]);
    }
}
