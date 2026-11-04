<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Facility;
use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\IntegrityService;
use App\Services\SettlementService;
use Database\Seeders\Support\TrailBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A couple of weeks of verified batches.
 *
 * The hand-written fixtures are all stamped "minutes ago", and NetworkSeeder
 * is a 90-day pilot build — neither gives a freshly seeded database a believable
 * last fortnight. Analytics compares this month against last, Earnings charts
 * week against week and the processing queue orders by verified_at, so those
 * screens need verified weight spread across real calendar days, not a pile
 * dated today.
 *
 * Every batch here is settled through the real SettlementService, so value,
 * shares and ledger entries are produced the way production produces them.
 * Codes are fixed (EC-W-<year>-VH####), which makes the seeder idempotent:
 * re-running it refreshes the same rows instead of seeding a second fortnight,
 * and the ledger's idempotency keys stop any double credit.
 */
class VerifiedHistorySeeder extends Seeder
{
    /** Calendar days of history, today included. */
    private const DAYS = 14;

    /** Verified batches per day; Sundays run lighter, like NetworkSeeder. */
    private const PER_DAY = 5;

    public function run(): void
    {
        $households = Household::with('user')->get();
        $agents     = Agent::all();
        $categories = WasteCategory::active()->get();
        $facility   = Facility::first();

        if ($households->isEmpty() || $agents->isEmpty() || $categories->isEmpty() || ! $facility) {
            $this->command->error('Run EcoCycleSeeder first — households, agents, categories or the facility are missing.');

            return;
        }

        $settlement = app(SettlementService::class);
        $integrity  = app(IntegrityService::class);

        $made = 0;
        $seq  = 0;

        for ($daysAgo = self::DAYS - 1; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo);
            $perDay = $date->isSunday() ? self::PER_DAY - 2 : self::PER_DAY;

            for ($slot = 0; $slot < $perDay; $slot++) {
                $seq++;

                $verifiedAt = $date->copy()->setTime(7, 0)->addMinutes($slot * 97 + ($seq % 40));

                // Nothing may be verified in the future, so today's last
                // batches slide back to just before now.
                if ($verifiedAt->isFuture()) {
                    $verifiedAt = now()->subMinutes(15 + $slot * 11);
                }

                $household = $households[($seq * 7) % $households->count()];
                $agent     = $agents[($seq * 5) % $agents->count()];
                $category  = $this->pickCategory($categories, $seq);

                $expected  = round(8 + (($seq * 37) % 340) / 10, 1);
                $collected = round($expected * (0.92 + (($seq * 13) % 18) / 100), 1);
                $verified  = round($collected * (0.94 + (($seq * 7) % 10) / 100), 1);

                // Walk backwards from the scale reading so every stamp in the
                // chain precedes the one after it, even for today's batches.
                $inspecting = $verifiedAt->copy()->subMinutes(25);
                $received   = $inspecting->copy()->subMinutes(50);
                $inTransit  = $received->copy()->subMinutes(40);
                $collectedAt = $inTransit->copy()->subMinutes(90);
                $accepted   = $collectedAt->copy()->subMinutes(70);
                $available  = $accepted->copy()->subMinutes(20);
                $requested  = $available->copy()->subMinutes(35);

                $batch = WasteBatch::updateOrCreate(
                    ['code' => sprintf('EC-W-%d-VH%04d', $verifiedAt->year, $seq)],
                    [
                        'ulid' => (string) Str::ulid(),
                        'household_id' => $household->id,
                        'agent_id' => $agent->id,
                        'facility_id' => $facility->id,
                        'waste_category_id' => $category->id,
                        'confirmed_category_id' => $category->id,
                        'status' => BatchStatus::Verified,
                        'expected_kg' => $expected,
                        'collected_kg' => $collected,
                        'verified_kg' => $verified,
                        'grade' => ['A', 'A', 'B', 'B', 'C'][$seq % 5],
                        'pickup_lat' => $household->lat ? round((float) $household->lat + (($seq % 9) - 4) / 10000, 7) : null,
                        'pickup_lng' => $household->lng ? round((float) $household->lng + (($seq % 7) - 3) / 10000, 7) : null,
                        'requested_at' => $requested,
                        'available_at' => $available,
                        'accepted_at' => $accepted,
                        'collected_at' => $collectedAt,
                        'in_transit_at' => $inTransit,
                        'received_at' => $received,
                        'inspecting_at' => $inspecting,
                        'verified_at' => $verifiedAt,
                        'created_at' => $requested,
                        'updated_at' => $verifiedAt,
                    ]
                );

                TrailBuilder::rebuild($batch);

                // Real settlement, through the real service, so the ledger is real.
                $settlement->settle($batch->refresh());

                // settle() stamps its ledger rows with now(); backdate them to
                // the day the scale actually read, or the money history would
                // claim a fortnight of income arrived this morning.
                LedgerEntry::where('waste_batch_id', $batch->id)
                    ->where('kind', 'settlement')
                    ->update(['created_at' => $verifiedAt]);

                if ($batch->wasRecentlyCreated) {
                    $integrity->evaluateBatch($batch->refresh());
                }

                $made++;
            }
        }

        $kg = (float) WasteBatch::where('code', 'like', 'EC-W-%-VH%')->sum('verified_kg');

        $this->command->info("Verified history: {$made} batches across " . self::DAYS . ' days, ' . number_format($kg, 1) . ' kg verified.');
    }

    /** Weighted stream selection, same Lagos composition as NetworkSeeder. */
    private function pickCategory($categories, int $seq)
    {
        static $weights = [
            'organic' => 52, 'mixed' => 14, 'paper' => 12,
            'plastic' => 13, 'glass' => 5, 'metal' => 3, 'ewaste' => 1,
        ];

        $roll = ($seq * 29) % 100 + 1;
        $running = 0;

        foreach ($weights as $key => $w) {
            $running += $w;
            if ($roll <= $running && $hit = $categories->firstWhere('key', $key)) {
                return $hit;
            }
        }

        return $categories->first();
    }
}
