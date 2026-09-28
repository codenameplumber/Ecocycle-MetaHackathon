<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Household;
use App\Models\User;
use App\Models\WasteCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Four steps: role, details, location, done.
 *
 * Coordinates matter more than the written address -- they are what decides
 * which agents ever see this household's jobs -- so step three captures them
 * from the browser and stores them on the profile.
 */
#[Layout('layouts.auth')]
class Register extends Component
{
    public int $step = 1;

    public string $role = 'household';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';

    // Household
    public int $size = 4;
    public string $frequency = 'Weekly';
    public string $category = 'organic';

    // Agent
    public string $vehicle = 'Tricycle';
    public int $capacity = 260;
    public string $plate = '';

    /**
     * The applicant has read what compliance papers are required.
     *
     * Uploading them is not built — the pilot verifies by hand — but being told
     * up front is the part that was missing. Without this the first an agent
     * heard of any of it was four red rows on their own profile.
     */
    public bool $compliance = false;

    // Location
    public ?int $area_id = null;
    public string $address = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public ?string $locateMessage = null;
    public bool $locateOutside = false;

    public ?string $createdCode = null;

    public function mount(): void
    {
        $this->area_id = Area::where('is_active', true)->value('id');
    }

    public function next(): void
    {
        if ($this->step === 2) {
            $this->validate([
                'name'     => 'required|string|min:2|max:120',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'required|string|min:7|max:20',
                'password' => 'required|string|min:8',
            ]);

            if ($this->role === 'agent') {
                $this->validate([
                    // The weighbridge identifies a vehicle by its plate, so an
                    // agent without one arrives at the gate unidentifiable.
                    'plate'      => 'required|string|min:3|max:16',
                    'capacity'   => 'required|integer|min:10|max:5000',
                    'compliance' => 'accepted',
                ], [
                    'compliance.accepted' => 'Please confirm you can provide the documents listed above.',
                ], ['plate' => 'vehicle plate']);
            }
        }

        if ($this->step === 3) {
            $this->validate([
                'area_id' => 'required|exists:areas,id',
                'address' => 'required|string|min:4|max:180',
            ]);

            $this->createAccount();
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Coordinates pushed up from navigator.geolocation in the browser. */
    public function setCoords(float $lat, float $lng, ?float $accuracy = null): void
    {
        // Out of area, the account falls back to the chosen area's centre, which
        // is what createAccount() already does when no coordinates were captured.
        if (! \App\Support\Geo::withinServiceArea($lat, $lng)) {
            $this->lat = $this->lng = null;
            $this->locateOutside = true;
            $this->locateMessage = 'This device is outside the area we serve, so the centre of your chosen area will be used.';

            return;
        }

        $this->locateOutside = false;
        $this->lat = round($lat, 7);
        $this->lng = round($lng, 7);
        $this->locateMessage = $accuracy
            ? 'Coordinates captured (±' . round($accuracy) . ' m)'
            : 'Coordinates captured';
    }

    private function createAccount(): void
    {
        DB::transaction(function () {
            $role = UserRole::from($this->role);

            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'phone'    => $this->phone,
                'password' => Hash::make($this->password),
                'role'     => $role,
            ]);

            $area = Area::find($this->area_id);

            if ($role === UserRole::Agent) {
                $agent = Agent::create([
                    'user_id'        => $user->id,
                    'code'           => 'EC-A-' . str_pad((string) (Agent::count() + 43), 4, '0', STR_PAD_LEFT),
                    'vehicle_type'   => $this->vehicle,
                    'capacity_kg'    => $this->capacity,
                    'plate'          => strtoupper(trim($this->plate)) ?: null,
                    'operating_area' => $area?->name,
                    'last_lat'       => $this->lat ?? $area?->lat,
                    'last_lng'       => $this->lng ?? $area?->lng,
                    // Awaiting review, not missing. `kyc_verified_at` stays null
                    // because only the facility may set it -- an applicant does
                    // not get to verify themselves.
                    'documents'      => $this->declaredDocuments(),
                ]);
                $this->createdCode = $agent->code;
            } else {
                $household = Household::create([
                    'user_id'   => $user->id,
                    'code'      => 'EC-H-' . str_pad((string) (Household::count() + 184), 4, '0', STR_PAD_LEFT),
                    'area_id'   => $area?->id,
                    'address'   => $this->address,
                    'lat'       => $this->lat ?? $area?->lat,
                    'lng'       => $this->lng ?? $area?->lng,
                    'size'      => $this->size,
                    'frequency' => $this->frequency,
                    'default_category_id' => WasteCategory::where('key', $this->category)->value('id'),
                ]);
                $this->createdCode = $household->code;
            }

            Auth::login($user);
        });
    }

    /**
     * The four papers, recorded as declared-but-unverified.
     *
     * @return array<string, array{status: string, note: string}>
     */
    private function declaredDocuments(): array
    {
        return collect(Agent::DOCUMENT_TYPES)
            ->map(fn () => [
                'status' => 'pending',
                'note'   => 'Declared at sign-up · send to ops@ecocycle.ng for verification',
            ])
            ->all();
    }

    public function finish()
    {
        // The account is created on the step 3 -> 4 transition, so reaching this
        // without one means the flow was re-entered out of order -- a stale tab,
        // a back button, an expired session. Send them back to the step that
        // still needs doing rather than dying on a null user.
        if (! Auth::check()) {
            $this->step = 3;

            return null;
        }

        return redirect(Auth::user()->role->home());
    }

    public function render()
    {
        return view('livewire.auth.register', [
            // The pin the account will be matched on, once coordinates exist.
            'pins'       => $this->lat
                ? [\App\Support\Geo::pin((float) $this->lat, (float) $this->lng,
                    $this->role === 'agent' ? 'agent' : 'house', live: true, label: 'You')]
                : [],
            'areas'      => Area::where('is_active', true)->orderBy('name')->get(),
            'categories' => WasteCategory::active()->get(),
        ])->layoutData([
            'aside' => 'register',
            'back' => route('login'),
            'backLabel' => 'Sign in',
        ]);
    }
}
