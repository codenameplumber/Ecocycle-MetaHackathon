<?php

namespace App\Models;

use App\Concerns\HasLedger;
use App\Models\Concerns\HasPayoutAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasLedger;
    use HasPayoutAccount;

    protected $fillable = [
        'user_id', 'code', 'vehicle_type', 'capacity_kg', 'plate',
        'operating_area', 'kyc_verified_at', 'integrity_score', 'rating',
        'is_online', 'last_lat', 'last_lng', 'last_seen_at', 'preferences', 'documents',
    ];

    protected function casts(): array
    {
        return [
            'kyc_verified_at' => 'datetime', 'last_seen_at' => 'datetime',
            'is_online' => 'boolean', 'integrity_score' => 'float',
            'rating' => 'float', 'last_lat' => 'float', 'last_lng' => 'float',
            'preferences' => 'array', 'documents' => 'array',
        ];
    }

    /**
     * Preference switches, with the shipped defaults. Same reasoning as on
     * Household: absent means "the default that was in force", not "off".
     *
     * @var array<string, bool>
     */
    public const PREFERENCE_DEFAULTS = [
        'accept_offers'      => true,
        'weekend_shifts'     => true,
        'notify_new_jobs'    => true,
        'notify_verified'    => true,
        'notify_payouts'     => true,
    ];

    public function pref(string $key): bool
    {
        return (bool) ($this->preferences[$key] ?? self::PREFERENCE_DEFAULTS[$key] ?? false);
    }

    /**
     * The shift the agent has told us they work.
     *
     * Kept out of `pref()` because these are not switches — casting a time to
     * bool is exactly the kind of quiet nonsense a shared accessor invites.
     *
     * @return array{days: list<int>, from: string, until: string}
     */
    public function availability(): array
    {
        $p = (array) ($this->preferences ?? []);

        return [
            // 1 = Monday. Six days by default; Sunday the gate is shut anyway.
            'days'  => $p['days'] ?? [1, 2, 3, 4, 5, 6],
            'from'  => $p['from'] ?? '07:00',
            'until' => $p['until'] ?? '19:00',
        ];
    }

    /** @return array<string, bool> */
    public function prefs(): array
    {
        return array_merge(self::PREFERENCE_DEFAULTS, (array) ($this->preferences ?? []));
    }

    /**
     * The compliance papers a collector must have on file.
     *
     * Declared here rather than inside docs() so registration can present the
     * same four an agent will later be judged against — an applicant who is
     * never told what is required should not meet the list for the first time
     * as four red "Missing" rows on their own profile.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_TYPES = [
        'id'      => 'Government ID',
        'address' => 'Proof of address',
        'vehicle' => 'Vehicle papers',
        'scale'   => 'Scale calibration certificate',
    ];

    /**
     * The compliance documents on file. Each is a status, not a file path — the
     * pilot verifies these by hand and records the outcome.
     *
     * @return array<string, array{label: string, status: string, note: ?string}>
     */
    public function docs(): array
    {
        $stored = (array) ($this->documents ?? []);

        $out = [];
        foreach (self::DOCUMENT_TYPES as $key => $label) {
            $out[$key] = [
                'label'  => $label,
                'status' => $stored[$key]['status'] ?? ($this->kyc_verified_at ? 'verified' : 'missing'),
                'note'   => $stored[$key]['note'] ?? null,
            ];
        }

        return $out;
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function batches(): HasMany     { return $this->hasMany(WasteBatch::class); }
    public function deliveries(): HasMany  { return $this->hasMany(Delivery::class); }
    public function flags(): HasMany       { return $this->hasMany(IntegrityFlag::class); }

    public function name(): string
    {
        return $this->user?->name ?? $this->code;
    }

    /** Kilograms currently on board -- collected but not yet delivered. */
    public function loadOnBoardKg(): float
    {
        return (float) $this->batches()
            ->whereIn('status', ['COLLECTED', 'IN_TRANSIT'])
            ->sum('collected_kg');
    }

    /** Kilograms promised to accepted jobs not yet picked up. */
    public function reservedKg(): float
    {
        return (float) $this->batches()->where('status', 'ACCEPTED')->sum('expected_kg');
    }

    /** Everything the vehicle is already committed to, physical or promised. */
    public function committedKg(): float
    {
        return $this->loadOnBoardKg() + $this->reservedKg();
    }

    /** Whether one more batch of this size may be accepted. */
    public function canAccept(float $expectedKg): bool
    {
        return $expectedKg <= $this->remainingCapacityKg();
    }

    /** Collected in the last sixty minutes. */
    public function collectedLastHourKg(): float
    {
        return (float) $this->batches()->where('collected_at', '>=', now()->subHour())->sum('collected_kg');
    }

    /** @return array{pct: int, level: string, remaining: float, committed: float} */
    public function capacityState(): array
    {
        $capacity = max(1.0, (float) $this->capacity_kg);
        $committed = $this->committedKg();
        $pct = (int) min(100, round($committed / $capacity * 100));

        return [
            'pct' => $pct,
            'level' => $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : 'ok'),
            'remaining' => $this->remainingCapacityKg(),
            'committed' => round($committed, 1),
        ];
    }

    public function remainingCapacityKg(): float
    {
        return max(0, (float) $this->capacity_kg - $this->committedKg());
    }

    /** Mean absolute variance between what this agent declares and the scale. */
    public function weightAccuracy(): float
    {
        $avg = $this->batches()->whereNotNull('variance_pct')->avg(\Illuminate\Support\Facades\DB::raw('ABS(variance_pct)'));

        return $avg === null ? 100.0 : round(100 - (float) $avg, 1);
    }

    public function tripsCompleted(): int
    {
        return $this->batches()->whereIn('status', ['VERIFIED', 'PROCESSING', 'PROCESSED'])->count();
    }
}
