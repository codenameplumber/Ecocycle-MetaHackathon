<?php

namespace App\Livewire\Household;

use App\Enums\BatchStatus;
use App\Models\Facility;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\BatchCodeGenerator;
use App\Services\BatchStateMachine;
use App\Services\LoadEstimatorService;
use App\Services\OpenAiService;
use App\Support\Geo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Creates a batch and puts it on the marketplace.
 *
 * Photo-first: the household snaps the waste, the estimator returns a
 * weight range from bag count, stream density and their own verified
 * history, and that range fills expected_kg. collected_kg and verified_kg
 * stay null until an agent and then the facility scale fill them in.
 */
#[Layout('layouts.phone')]
class RequestPickup extends Component
{
    use WithFileUploads;

    public $photo;
    public int $bags = 2;

    /** @var array<string, mixed>|null */
    public ?array $classification = null;

    public bool $estimating = false;

    /** Category Muse predicts the waste falls under, before the user confirms. */
    public ?string $predictedCategoryKey = null;
    public int $predictedConfidence = 0;

    /** photo | history | manual — where the current category came from. */
    public string $predictionSource = '';

    public ?int $categoryId = null;
    public float $kg = 10.0;
    public string $window = 'Morning';
    public string $day = 'tomorrow';
    public string $note = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public ?float $accuracy = null;
    public bool $located = false;

    /** The batch just created, if any — drives the confirmation sheet. */
    public ?int $createdId = null;

    public function mount(): void
    {
        $household = auth()->user()->household;
        $this->categoryId = $household->default_category_id ?? WasteCategory::active()->value('id');
        $this->kg = $household->predictedKg();
        $this->lat = $household->lat;
        $this->lng = $household->lng;
        $this->bags = max(1, (int) round($this->kg / 7));
        $this->predictCategoryFromHistory();
    }

    /**
     * Before any photo exists, predict from what this household usually
     * disposes of. A household that mostly sends plastic probably is again.
     */
    public function predictCategoryFromHistory(): void
    {
        $household = auth()->user()->household;

        if (! $household) {
            return;
        }

        $total = $household->batches()->count();
        if ($total < 3) {
            return;
        }

        $top = $household->batches()
            ->selectRaw('waste_category_id, COUNT(*) as n')
            ->groupBy('waste_category_id')
            ->orderByDesc('n')
            ->first();

        $category = $top ? WasteCategory::active()->find($top->waste_category_id) : null;
        if (! $category) {
            return;
        }

        $this->categoryId = $category->id;
        $this->predictedCategoryKey = $category->key;
        $this->predictedConfidence = (int) min(85, round($top->n / $total * 100));
        $this->predictionSource = 'history';
    }

    /**
     * Apply the category read from the snapped photo.
     *
     * The photo prediction wins over history and over the default. The
     * user's own tap still wins over both — updatedCategoryId() marks the
     * source manual, and nothing re-applies a prediction after that.
     */
    public function applyPredictedCategory(): bool
    {
        $key = $this->classification['suggested'] ?? null;
        if (! $key) {
            return false;
        }

        $category = WasteCategory::active()->where('key', $key)->first();
        if (! $category) {
            return false;
        }

        $this->predictedCategoryKey = $category->key;
        $this->predictedConfidence = (int) ($this->classification['confidence'] ?? 0);
        $this->predictionSource = 'photo';

        // Only a real model read may move the user's selection. The
        // no-key fallback suggests "mixed" for everything; applying that
        // would silently re-label every load.
        if (! empty($this->classification['generated'])) {
            $this->categoryId = $category->id;
        }

        return true;
    }

    /** The category Muse currently predicts, for the screen to show. */
    public function predictedCategory(): ?WasteCategory
    {
        return $this->predictedCategoryKey
            ? WasteCategory::active()->where('key', $this->predictedCategoryKey)->first()
            : null;
    }

    /** A snapped photo is read once, then the estimate follows it. */
    public function updatedPhoto(): void
    {
        $this->validate(['photo' => 'image|max:8192']);
        $this->estimating = true;
        $this->classification = app(OpenAiService::class)->classifyPhoto($this->photo->getRealPath());
        $this->estimating = false;
        $this->applyPredictedCategory();
        $this->applyEstimate();
    }

    public function updatedBags(): void
    {
        $this->applyEstimate();
    }

    public function updatedCategoryId(): void
    {
        // The user's tap always wins over a prediction.
        $this->predictionSource = 'manual';
        $this->predictedCategoryKey = WasteCategory::find($this->categoryId)?->key;
        $this->predictedConfidence = 100;
        $this->applyEstimate();
    }

    public function setBags(int $bags): void
    {
        $this->bags = max(1, min(20, $bags));
        $this->applyEstimate();
    }

    /** Fill the expected weight from the smart estimate. */
    public function applyEstimate(): void
    {
        $estimate = $this->smartEstimate();
        if ($estimate) {
            $this->kg = $estimate['mid'];
        }
    }

    /** @return array{low: float, mid: float, high: float, confidence: int, label: string, basis: list<string>}|null */
    public function smartEstimate(): ?array
    {
        $household = auth()->user()->household;
        $category = WasteCategory::find($this->categoryId);

        if (! $category) {
            return null;
        }

        return app(LoadEstimatorService::class)
            ->estimate($household, $category, $this->bags, $this->classification);
    }

    public function setCoords(float $lat, float $lng, ?float $accuracy = null, bool $manual = true): void
    {
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
            'kg' => 'required|numeric|min:1|max:200',
            'photo' => 'nullable|image|max:8192',
        ]);

        $household = auth()->user()->household;

        $windowStart = match ($this->day) {
            'today' => now(),
            'tomorrow' => now()->addDay()->startOfDay(),
            default => now()->addDays(2)->startOfDay(),
        };

        $offset = match ($this->window) {
            'Morning' => [8, 12],
            'Afternoon' => [12, 16],
            'Evening' => [16, 20],
            default => [8, 20],
        };

        $batch = WasteBatch::create([
            'ulid' => $codes->ulid(),
            'code' => $codes->batchCode(),
            'household_id' => $household->id,
            'waste_category_id' => $this->categoryId,
            'status' => BatchStatus::Requested,
            'expected_kg' => $this->kg,
            'photo_path' => $this->photo ? $this->photo->store('pickups', 'public') : null,
            'classification' => $this->classification,
            'pickup_lat' => $this->lat ?? $household->lat,
            'pickup_lng' => $this->lng ?? $household->lng,
            'note' => $this->note ?: null,
            'requested_at' => now(),
            'window_start' => $windowStart->copy()->setHour($offset[0]),
            'window_end' => $windowStart->copy()->setHour($offset[1]),
        ]);

        $machine->transition($batch, BatchStatus::Available, auth()->user());
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
            'household' => $household,
            'pins' => $pins,
            'smart' => $this->smartEstimate(),
            'predicted' => $this->predictedCategory(),
        ])->layoutData([
            'app' => 'household', 'page' => 'request',
            'heading' => 'Request a pickup', 'sub' => 'Snap it, we estimate it',
            'back' => route('app'),
        ]);
    }
}
