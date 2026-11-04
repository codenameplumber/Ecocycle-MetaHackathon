<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Livewire\Agent\Today;
use App\Models\User;
use App\Models\WasteBatch;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentTodayAcceptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    #[Test]
    public function an_agent_can_accept_a_job_from_the_today_screen(): void
    {
        Notification::fake();

        $batch = WasteBatch::where('status', BatchStatus::Available->value)->firstOrFail();
        $agentUser = User::whereHas('agent')->firstOrFail();

        // The Today screen (route /agent) renders job-card without
        // $confirmable, so its Accept button calls accept() directly on Today.
        Livewire::actingAs($agentUser)
            ->test(Today::class)
            ->call('accept', $batch->id)
            ->assertHasNoErrors();

        $batch->refresh();
        $this->assertSame(BatchStatus::Accepted, $batch->status);
        $this->assertSame($agentUser->agent->id, $batch->agent_id);
    }
}
