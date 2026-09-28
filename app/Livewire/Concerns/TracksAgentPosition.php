<?php

namespace App\Livewire\Concerns;

/**
 * Keeps an agent's position current from the device.
 *
 * Before this existed, `agents.last_lat/last_lng` was written once at
 * registration and never again — so JobMatcher ranked every job, and RoutePlan
 * ordered every round, against wherever the collector happened to be standing
 * on the day they signed up. The distances on screen were real arithmetic over
 * a stale number.
 *
 * A component using this trait renders `data-ec-position` on its root element;
 * the shared handler in partials/scripts then pings on load and on demand.
 */
trait TracksAgentPosition
{
    /**
     * Record where the collector is now.
     *
     * Silent by default, because this fires on every agent screen that loads and
     * a toast each time would be noise. A collector who pressed Update, though,
     * pressed a button and is owed an answer — hence `$announce`.
     */
    public function pingPosition(float $lat, float $lng, ?float $accuracy = null, bool $announce = false): void
    {
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        if (! \App\Support\Geo::withinServiceArea($lat, $lng)) {
            if ($announce) {
                $this->dispatch('toast', message: 'That fix is outside the service area — keeping your last position', kind: 'warn');
            }

            return;
        }

        $agent = auth()->user()?->agent;

        if (! $agent) {
            return;
        }

        $agent->update([
            'last_lat'     => round($lat, 7),
            'last_lng'     => round($lng, 7),
            'last_seen_at' => now(),
        ]);

        if ($announce) {
            $this->dispatch('toast', message: 'Position updated — jobs re-ranked', kind: 'ok');
        }
    }

    /**
     * How long ago the position on file was recorded, in minutes.
     *
     * A screen that ranks jobs by distance should be able to say how much to
     * trust that ranking, rather than presenting a stale figure as a live one.
     */
    public function positionAgeMinutes(): ?int
    {
        $seen = auth()->user()?->agent?->last_seen_at;

        return $seen ? (int) $seen->diffInMinutes(now()) : null;
    }
}
