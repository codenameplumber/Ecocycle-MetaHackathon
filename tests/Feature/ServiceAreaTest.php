<?php

namespace Tests\Feature;

use App\Livewire\Agent\Jobs;
use App\Livewire\Auth\Register;
use App\Livewire\Household\RequestPickup;
use App\Models\Area;
use App\Models\User;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A device location from outside the network is refused, not stored.
 *
 * The browser reports wherever the phone is. Opened at a venue in Abuja, an
 * agent screen used to move that agent 500 km from every pickup, and every job
 * on the board then read "527 km away".
 */
class ServiceAreaTest extends TestCase
{
    use RefreshDatabase;

    /** Central Abuja: roughly 530 km from the Ojota facility. */
    private const ABUJA = [9.0765, 7.3986];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    private function agentUser(): User
    {
        return User::where('role', 'agent')->whereHas('agent')->firstOrFail();
    }

    #[Test]
    public function an_agent_position_inside_lagos_is_stored(): void
    {
        $user = $this->agentUser();

        Livewire::actingAs($user)->test(Jobs::class)
            ->call('pingPosition', 6.6018, 3.3515);

        $this->assertEqualsWithDelta(6.6018, (float) $user->agent->fresh()->last_lat, 0.0001);
    }

    #[Test]
    public function an_agent_position_outside_the_service_area_is_refused(): void
    {
        $user = $this->agentUser();
        $before = [$user->agent->last_lat, $user->agent->last_lng];

        Livewire::actingAs($user)->test(Jobs::class)
            ->call('pingPosition', self::ABUJA[0], self::ABUJA[1], 12.0, true)
            ->assertDispatched('toast');

        $agent = $user->agent->fresh();
        $this->assertSame($before, [$agent->last_lat, $agent->last_lng]);
    }

    #[Test]
    public function a_pickup_point_outside_the_service_area_keeps_the_registered_address(): void
    {
        $user = User::where('role', 'household')->whereHas('household')->firstOrFail();

        Livewire::actingAs($user)->test(RequestPickup::class)
            ->call('setCoords', self::ABUJA[0], self::ABUJA[1], 12.0, true)
            ->assertSet('lat', $user->household->lat)
            ->assertSet('lng', $user->household->lng)
            ->assertSet('located', false);
    }

    #[Test]
    public function registering_from_outside_the_service_area_uses_the_area_centre(): void
    {
        $area = Area::firstOrFail();

        Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')
            ->set('name', 'Amina Bello')
            ->set('email', 'amina.bello@example.test')
            ->set('phone', '08031234567')
            ->set('password', 'a-real-password')
            ->call('next')
            ->set('area_id', $area->id)
            ->set('address', 'Plot 3, Wuse II')
            ->call('setCoords', self::ABUJA[0], self::ABUJA[1])
            ->assertSet('locateOutside', true)
            ->assertSet('lat', null)
            ->call('next')
            ->assertHasNoErrors();

        $household = User::where('email', 'amina.bello@example.test')->firstOrFail()->household;
        $this->assertEqualsWithDelta((float) $area->lat, (float) $household->lat, 0.0001);
        $this->assertEqualsWithDelta((float) $area->lng, (float) $household->lng, 0.0001);
    }
}
