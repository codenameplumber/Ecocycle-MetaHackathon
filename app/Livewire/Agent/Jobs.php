<?php

namespace App\Livewire\Agent;

use App\Livewire\Concerns\AcceptsJobs;
use App\Livewire\Concerns\TracksAgentPosition;
use App\Models\WasteBatch;
use App\Services\JobMatcher;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.phone')]
class Jobs extends Component
{
    use AcceptsJobs;
    use TracksAgentPosition;

    /** Batch the agent has tapped Accept on but not yet confirmed. */
    public ?int $confirmId = null;

    public function ask(int $batchId): void
    {
        $this->confirmId = $batchId;
    }

    public function dismiss(): void
    {
        $this->confirmId = null;
    }

    public function getConfirmingProperty(): ?WasteBatch
    {
        return $this->confirmId
            ? WasteBatch::with(['household.user', 'household.area', 'category'])->find($this->confirmId)
            : null;
    }

    public function render(JobMatcher $matcher)
    {
        $agent = auth()->user()->agent;

        $jobs = $matcher->rank($agent, 20);

        // The agent's own position first, then every job on offer. An agent
        // reading this map is asking "which way do I go", so their pin has to
        // be on it.
        $pins = [];
        if ($agent->last_lat) {
            $pins[] = Geo::pin((float) $agent->last_lat, (float) $agent->last_lng, 'agent', live: true, label: 'You');
        }
        foreach ($jobs as $j) {
            if ($j->household?->lat) {
                $pins[] = Geo::pin((float) $j->household->lat, (float) $j->household->lng, 'house');
            }
        }

        return view('livewire.agent.jobs', [
            'agent'     => $agent,
            'jobs'      => $jobs,
            'pins'      => $pins,
            'remaining' => $agent->remainingCapacityKg(),
            'capacity' => $agent->capacityState(),
            'lastHourKg' => $agent->collectedLastHourKg(),
            'posAge'    => $this->positionAgeMinutes(),
        ])->layoutData([
            'app' => 'agent', 'page' => 'jobs',
            'heading' => 'Available jobs',
            'sub' => 'Ranked for your vehicle and position',
        ]);
    }
}
