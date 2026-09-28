<?php

namespace App\Livewire\Household;

use App\Enums\BatchStatus;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\BatchCodeGenerator;
use App\Services\BatchStateMachine;
use App\Services\QrService;
use App\Services\SettlementService;
use App\Models\Facility;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Creates a batch and puts it on the marketplace.
 *
 * The payout shown here is explicitly an estimate. The batch is created with an
 * expected_kg only -- collected_kg and verified_kg stay null until an agent and
 * then the facility scale fill them in.
 */
#[Layout('layouts.phone')]
class RequestPickup extends Component
{
    public ?int $categoryId = null;
    public float $kg = 10.0;
    public string $window = 'Morning';
    public string $day = 'tomorrow';
    public string $note = '';
    public ?float $lat = null;
    public ?float $lng = null;

    /** The batch just created, if any — drives the confirmation sheet. */
    public ?int $createdId = null;

    public function mount(): void
    {
        $household = auth()->user()->household;
        $this->categoryId = $household->default_category_id ?? WasteCategory::active()->value('id');
        $this->kg = $household->predictedKg();
        $this->lat = $household->lat;
        $this->lng = $household->lng;
    }

    /** Metres of GPS error the browser reported for the pin, if it said. */
    public ?float $accuracy = null;

    /** True once the coordinates came from the device rather than the file. */
    public bool $located = false;

    /**
     * Coordinates pushed up from navigator.geolocation.
     *
     * These become the batch's pickup_lat/pickup_lng, which is what JobMatcher
     * measures every agent against — so this is the difference between a job
     * ranked from where the waste actually is and one ranked from wherever the
     * household happened to sign up.
     */
    public function setCoords(float $lat, float $lng, ?float $accuracy = null, bool $manual = true): void
    {
        // Lagos is at roughly 6.5N, 3.4E, but the guard here is only against a
        // malformed payload -- a household travelling is still a real pickup.
        if (abs($lat) > 90 || abs($lng) > 180) {
            $this->dispatch('toast', message: 'That location did not look right', kind: 'warn');

            return;
        }

        if (! Geo::withinServiceArea($lat, $lng)) {
            $this->dispatch('toast', message: 'That location is outside the service area — keeping your pickup point', kind: 'warn');

            return;
        }

        $this->lat = round($lat, 7);
        $this->lng = round($lng, 7);
        $this->accuracy = $accuracy ? round($accuracy) : null;
        $this->located = true;

        $this->dispatch('toast', message: 'Pickup point set from your device', kind: 'ok');
    }

    /** Fall back to the address the household registered with. */
    public function useRegisteredPoint(): void
    {
        $h = auth()->user()->household;

        $this->lat = $h->lat;
        $this->lng = $h->lng;
        $this->accuracy = null;
        $this->located = false;

        $this->dispatch('toast', message: 'Using your registered address', kind: 'ok');
    }

    /** Estimate only — the facility scale is what actually settles. */
    public function getEstimateProperty(): array
    {
        $category = WasteCategory::find($this->categoryId);
        $rate = (float) ($category?->rate_per_kg ?? 0);
        $value = round($this->kg * $rate, 2);
        $share = round($value * (float) config('ecocycle.household_share'), 2);

        return ['rate' => $rate, 'value' => $value, 'share' => $share];
    }

    public function submit(BatchCodeGenerator $codes, BatchStateMachine $machine)
    {
        $this->validate([
            'categoryId' => 'required|exists:waste_categories,id',
            'kg'         => 'required|numeric|min:1|max:200',
        ]);

        $household = auth()->user()->household;

        $windowStart = match ($this->day) {
            'today'    => now(),
            'tomorrow' => now()->addDay()->startOfDay(),
            default    => now()->addDays(2)->startOfDay(),
        };

        $offset = match ($this->window) {
            'Morning'   => [8, 12],
            'Afternoon' => [12, 16],
            'Evening'   => [16, 20],
            default     => [8, 20],
        };

        $batch = WasteBatch::create([
            'ulid'              => $codes->ulid(),
            'code'              => $codes->batchCode(),
            'household_id'      => $household->id,
            'waste_category_id' => $this->categoryId,
            'status'            => BatchStatus::Requested,
            'expected_kg'       => $this->kg,
            'pickup_lat'        => $this->lat ?? $household->lat,
            'pickup_lng'        => $this->lng ?? $household->lng,
            'note'              => $this->note ?: null,
            'requested_at'      => now(),
            'window_start'      => $windowStart->copy()->setHour($offset[0]),
            'window_end'        => $windowStart->copy()->setHour($offset[1]),
        ]);

        // Straight onto the marketplace so agents can see it.
        $machine->transition($batch, BatchStatus::Available, auth()->user());

        // Stay on the screen and show the tag. The batch code is the one thing
        // the household needs off this flow, and a redirect would take it away
        // before they had a chance to read it.
        $this->createdId = $batch->id;

        $this->dispatch('toast', message: 'Pickup requested — nearby agents notified', kind: 'ok');
    }

    public function getCreatedProperty(): ?WasteBatch
    {
        return $this->createdId ? WasteBatch::find($this->createdId) : null;
    }

    public function render()
    {
        $household = auth()->user()->household;
        $facility = Facility::first();

        // The point the agent will be sent to. `lat`/`lng` are whatever the
        // browser's geolocation last reported; otherwise the registered address.
        $pins = [
            Geo::pin(
                (float) ($this->lat ?? $household->lat),
                (float) ($this->lng ?? $household->lng),
                'house', live: true, label: 'Pickup point',
            ),
            Geo::pin(
                (float) ($facility?->lat ?? config('ecocycle.facility.lat')),
                (float) ($facility?->lng ?? config('ecocycle.facility.lng')),
                'plant', label: 'Facility'
            ),
        ];

        return view('livewire.household.request-pickup', [
            'categories' => WasteCategory::active()->get(),
            'household'  => $household,
            'pins'       => $pins,
        ])->layoutData([
            'app' => 'household', 'page' => 'request',
            'heading' => 'Request a pickup', 'sub' => 'Waste is ready for collection',
            'back' => route('app'),
        ]);
    }
}
