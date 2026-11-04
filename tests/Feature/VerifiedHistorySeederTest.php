<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\LedgerEntry;
use App\Models\WasteBatch;
use Database\Seeders\EcoCycleSeeder;
use Database\Seeders\VerifiedHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerifiedHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    #[Test]
    public function it_seeds_a_couple_of_weeks_of_settled_verified_batches(): void
    {
        $this->seed(VerifiedHistorySeeder::class);

        $batches = WasteBatch::where('code', 'like', 'EC-W-%-VH%')->get();

        $this->assertGreaterThanOrEqual(60, $batches->count());
        $this->assertTrue($batches->every(fn ($b) => $b->status === BatchStatus::Verified
            && $b->verified_kg > 0 && $b->verified_at && $b->value_naira > 0));

        // Every one of the last 14 calendar days carries verified weight, and
        // none of it is dated in the future.
        $days = $batches->groupBy(fn ($b) => $b->verified_at->toDateString());
        for ($i = 0; $i < 14; $i++) {
            $this->assertArrayHasKey(now()->subDays($i)->toDateString(), $days, "no verified batches {$i} day(s) ago");
        }
        $this->assertTrue($batches->every(fn ($b) => $b->verified_at->isPast()));

        // Settled for real: a household and an agent credit per batch.
        $this->assertSame(
            $batches->count() * 2,
            LedgerEntry::whereIn('waste_batch_id', $batches->pluck('id'))->where('kind', 'settlement')->count()
        );
    }

    #[Test]
    public function re_running_it_does_not_duplicate_batches_or_money(): void
    {
        $this->seed(VerifiedHistorySeeder::class);
        $batches = WasteBatch::where('code', 'like', 'EC-W-%-VH%')->count();
        $entries = LedgerEntry::where('kind', 'settlement')->count();

        $this->seed(VerifiedHistorySeeder::class);

        $this->assertSame($batches, WasteBatch::where('code', 'like', 'EC-W-%-VH%')->count());
        $this->assertSame($entries, LedgerEntry::where('kind', 'settlement')->count());
    }
}
