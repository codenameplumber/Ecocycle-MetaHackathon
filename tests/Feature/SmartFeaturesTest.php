<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\WasteBatch;
use App\Models\WasteCategory;
use App\Services\BehaviourForecastService;
use App\Services\LoadEstimatorService;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmartFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    #[Test]
    public function the_load_estimator_returns_a_range_not_a_point(): void
    {
        $category = WasteCategory::where('key', 'plastic')->firstOrFail();
        $estimate = app(LoadEstimatorService::class)->estimate(null, $category, 3);

        $this->assertLessThan($estimate['mid'], $estimate['low']);
        $this->assertLessThan($estimate['high'], $estimate['mid']);
    }

    #[Test]
    public function snapping_bags_replaces_manual_weight_entry(): void
    {
        $user = \App\Models\User::whereHas('household')->firstOrFail();
        $category = WasteCategory::where('key', 'organic')->firstOrFail();

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Household\RequestPickup::class)
            ->set('categoryId', $category->id)
            ->call('setBags', 4);

        $this->assertGreaterThan(0, (float) $component->get('kg'));
        $component->call('submit')->assertHasNoErrors();

        $batch = WasteBatch::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta((float) $component->get('kg'), (float) $batch->expected_kg, 0.01);
        $this->assertNull($batch->verified_kg, 'the smart estimate must never settle money');
    }

    #[Test]
    public function muse_predicts_the_category_from_the_photo_read(): void
    {
        $user = \App\Models\User::whereHas('household')->firstOrFail();
        $plastic = WasteCategory::where('key', 'plastic')->firstOrFail();

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Household\RequestPickup::class)
            ->set('classification', [
                'suggested' => 'plastic',
                'confidence' => 82,
                'generated' => true,
                'mix' => ['plastic' => 70, 'organic' => 30],
            ])
            ->call('applyPredictedCategory');

        $component->assertSet('categoryId', $plastic->id)
            ->assertSet('predictedCategoryKey', 'plastic')
            ->assertSet('predictedConfidence', 82)
            ->assertSet('predictionSource', 'photo');
    }

    #[Test]
    public function a_photo_prediction_without_a_model_read_does_not_relabel_the_load(): void
    {
        $user = \App\Models\User::whereHas('household')->firstOrFail();

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Household\RequestPickup::class);
        $before = $component->get('categoryId');

        $component->set('classification', [
                'suggested' => 'mixed',
                'confidence' => 0,
                'generated' => false,
                'mix' => ['organic' => 62],
            ])
            ->call('applyPredictedCategory');

        $component->assertSet('categoryId', $before);
    }

    #[Test]
    public function a_full_agent_cannot_accept_another_job(): void
    {
        $agent = Agent::firstOrFail();
        $agent->update(['capacity_kg' => 1]);

        $batch = WasteBatch::where('status', 'AVAILABLE')->firstOrFail();
        $batch->update(['expected_kg' => 10]);

        $this->assertFalse($agent->canAccept(10.0));
        $this->assertFalse(app(\App\Services\BatchStateMachine::class)->claim($batch, $agent));
        $this->assertSame('AVAILABLE', $batch->fresh()->status->value);
    }

    #[Test]
    public function the_behaviour_forecast_reports_areas_and_streams(): void
    {
        $service = app(BehaviourForecastService::class);

        $this->assertIsArray($service->tomorrowByArea());
        $this->assertIsArray($service->weekByCategory());
        $this->assertNotSame('', $service->briefing());
    }
}
