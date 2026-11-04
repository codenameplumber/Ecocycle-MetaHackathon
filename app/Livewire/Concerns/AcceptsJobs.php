<?php

namespace App\Livewire\Concerns;

use App\Models\WasteBatch;
use App\Services\BatchStateMachine;

/**
 * Claims a marketplace job for the signed-in agent.
 *
 * Shared by the jobs board and the Today screen: both render the job card,
 * whose Accept button calls `accept` on whichever component hosts it. Keeping
 * the claim in one place means the capacity gate, the race-safe claim and the
 * household notification cannot drift apart between the two screens.
 */
trait AcceptsJobs
{
    public function accept(int $batchId, BatchStateMachine $machine)
    {
        // The jobs board asks for confirmation first; clear that sheet if
        // this component has one. Today claims directly and has none.
        if (property_exists($this, 'confirmId')) {
            $this->confirmId = null;
        }

        $agent = auth()->user()->agent;
        $batch = WasteBatch::find($batchId);

        if (! $batch) {
            return;
        }

        if (! $agent->canAccept((float) ($batch->expected_kg ?? 0))) {
            $this->dispatch('toast', message: 'Vehicle is full — head to the facility to deliver your load first', kind: 'warn');

            return;
        }

        // The database decides who won, not the browser.
        if (! $machine->claim($batch, $agent)) {
            $this->dispatch('toast', message: 'Another agent took that one first', kind: 'warn');

            return;
        }

        $batch->household?->user?->notify(new \App\Notifications\JobAccepted($batch->refresh()));

        $this->dispatch('toast', message: 'Job accepted — it is on your route', kind: 'ok');

        return $this->redirect(route('agent.pickup', $batch), navigate: true);
    }
}
