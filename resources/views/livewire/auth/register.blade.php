<div>
    {{-- Step rail --}}
    <div class="steps mt-24">
        @foreach (['Role' => 'user', 'Details' => 'phone', 'Location' => 'pin', 'Done' => 'check'] as $label => $icon)
            @php $i = $loop->iteration; @endphp
            <div class="step {{ $step > $i ? 'done' : '' }} {{ $step === $i ? 'active' : '' }}">
                <span class="step-dot"><i data-icon="{{ $icon }}" class="i i-sm"></i></span>
                <span class="step-label">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    {{-- 1 · Role --}}
    @if ($step === 1)
        <section class="mt-24">
            <h1>What are you registering as?</h1>
            <p class="muted small mt-4">This decides which fields we ask for.</p>
            <div class="opt-grid mt-20" style="grid-template-columns:1fr">
                @foreach ([['household','Household','I generate waste and want it collected'],['agent','Collection agent','I collect and deliver to a facility']] as [$val,$title,$desc])
                    <label class="opt">
                        <input type="radio" wire:model.live="role" value="{{ $val }}">
                        <span class="opt-name">{{ $title }}</span>
                        <span class="opt-desc">{{ $desc }}</span>
                    </label>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 2 · Details --}}
    @if ($step === 2)
        <section class="mt-24">
            <h1>Your details</h1>
            <p class="muted small mt-4">Email signs you in. Your phone is how an agent reaches you at the gate.</p>

            <div class="col g-14 mt-20">
                <div class="field">
                    <label class="label">Full name</label>
                    <input class="input" wire:model="name" autocomplete="name">
                    @error('name') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label">Email address</label>
                    <input class="input" type="email" wire:model="email" autocomplete="email">
                    @error('email') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label">Phone number</label>
                    <input class="input" type="tel" wire:model="phone" autocomplete="tel" placeholder="0803 000 0000">
                    @error('phone') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>
                <x-password-field model="password" autocomplete="new-password"
                                  hint="At least 8 characters." />

                @if ($role === 'household')
                    <div class="grid grid-keep-2" style="gap:12px">
                        <div class="field">
                            <label class="label">Household size</label>
                            <select class="select" wire:model="size">
                                @foreach ([1,2,3,4,5,6,7,8,9,10] as $n)
                                    <option value="{{ $n }}">{{ $n }} {{ $n === 1 ? 'person' : 'people' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label">Collection frequency</label>
                            <select class="select" wire:model="frequency">
                                <option>Weekly</option><option>Twice/week</option><option>Bi-weekly</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Primary waste category</label>
                        <div class="opt-grid">
                            @foreach ($categories as $c)
                                <label class="opt">
                                    <input type="radio" wire:model="category" value="{{ $c->key }}">
                                    <span class="row g-6">
                                        <span style="width:8px;height:8px;border-radius:2px;background:{{ $c->color }}"></span>
                                        <span class="opt-name">{{ $c->label }}</span>
                                    </span>
                                    <span class="opt-desc">₦{{ (int) $c->rate_per_kg }} / kg</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="grid grid-keep-2" style="gap:12px">
                        <div class="field">
                            <label class="label">Vehicle</label>
                            <select class="select" wire:model="vehicle">
                                <option>Tricycle</option><option>Motorcycle</option><option>Pickup van</option><option>On foot</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label">Load capacity</label>
                            <div class="input-group">
                                <input class="input" type="number" wire:model="capacity">
                                <span class="input-suffix">kg</span>
                            </div>
                            @error('capacity') <span class="hint t-neg">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="plate">Vehicle plate</label>
                        <input class="input mono" id="plate" wire:model="plate"
                               placeholder="LAG-123-XY" maxlength="16" style="text-transform:uppercase">
                        <span class="hint">The weighbridge identifies your vehicle by this when you arrive.</span>
                        @error('plate') <span class="hint t-neg">{{ $message }}</span> @enderror
                    </div>

                    {{-- Compliance. Uploading is not built during the pilot, but an
                         applicant should meet this list here rather than as four red
                         rows on their profile after they have signed up. --}}
                    <div class="field">
                        <span class="label">Documents you will need</span>
                        <div class="foot-note">
                            <ul style="margin:0;padding-left:18px;line-height:1.9">
                                @foreach (\App\Models\Agent::DOCUMENT_TYPES as $doc)
                                    <li class="small">{{ $doc }}</li>
                                @endforeach
                            </ul>
                            <p class="tiny dim mt-10" style="margin-bottom:0">
                                The facility team verifies these by hand during the pilot. Email them to
                                <a href="mailto:ops@ecocycle.ng" style="color:var(--primary)">ops@ecocycle.ng</a>
                                after signing up. You can accept jobs while they are pending; settlements
                                are paid out once your KYC is verified.
                            </p>
                        </div>
                    </div>

                    <label class="switch">
                        <input type="checkbox" wire:model="compliance">
                        <span class="track"></span>
                        <span class="small muted">I can provide these documents for verification</span>
                    </label>
                    @error('compliance') <span class="hint t-neg">{{ $message }}</span> @enderror
                @endif
            </div>
        </section>
    @endif

    {{-- 3 · Location --}}
    @if ($step === 3)
        <section class="mt-24" x-data>
            <h1>Where are you?</h1>
            <p class="muted small mt-4">
                Coordinates decide which agents see your jobs, so accuracy matters more than the address.
            </p>

            <div class="col g-14 mt-20">
                <div class="field">
                    <label class="label">Area</label>
                    <select class="select" wire:model="area_id">
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    @error('area_id') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="label">Address</label>
                    <input class="input" wire:model="address" placeholder="House number and street">
                    @error('address') <span class="hint t-neg">{{ $message }}</span> @enderror
                </div>

                <button type="button" class="btn btn-ghost btn-block" onclick="ecLocate(this)">
                    <i data-icon="nav" class="i i-sm"></i> Use my current location
                </button>

                <div class="grid grid-keep-2" style="gap:12px">
                    <div class="field">
                        <label class="label">Latitude</label>
                        <input class="input mono" wire:model="lat" readonly>
                    </div>
                    <div class="field">
                        <label class="label">Longitude</label>
                        <input class="input mono" wire:model="lng" readonly>
                    </div>
                </div>

                @if ($locateMessage)
                    <div class="callout {{ $locateOutside ? 'callout-warn' : 'callout-ok' }}"><i data-icon="{{ $locateOutside ? 'alert' : 'ok' }}" class="i i-sm"></i><div>{{ $locateMessage }}</div></div>
                @endif

                @if ($pins)
                    <div class="card card-flat mt-16" style="overflow:hidden">
                        <x-map :pins="$pins" :height="170" :legend="false" />
                        <div class="card-body">
                            <p class="tiny dim">
                                This is the pin you will be matched on. Set it while you are at the
                                address — agents are routed to these coordinates, not to the text above.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- 4 · Done --}}
    @if ($step === 4)
        <section class="mt-24">
            <div class="center" style="padding:22px 0">
                <span class="qa-ic" style="width:64px;height:64px;margin:0 auto;border-radius:20px">
                    <i data-icon="check" class="i i-xl"></i>
                </span>
                <h1 class="mt-20">You're registered</h1>
                <p class="muted mt-8">
                    {{ ucfirst($role) }} profile <span class="mono strong">{{ $createdCode }}</span> created.
                </p>
            </div>
            <dl class="mt-8">
                <div class="kv"><dt>Name</dt><dd>{{ $name }}</dd></div>
                <div class="kv"><dt>Sign in with</dt><dd class="mono">{{ $email }}</dd></div>
                <div class="kv"><dt>Area</dt><dd>{{ $areas->firstWhere('id', $area_id)?->name ?? '—' }}</dd></div>
                @if ($role === 'agent')
                    <div class="kv"><dt>Vehicle</dt><dd>{{ $vehicle }} · {{ number_format($capacity) }} kg</dd></div>
                @else
                    <div class="kv"><dt>Usual stream</dt><dd>{{ ucfirst($category) }}</dd></div>
                @endif
                <div class="kv">
                    <dt>Coordinates</dt>
                    <dd class="mono">
                        {{ $lat ? number_format($lat, 5) : '—' }}, {{ $lng ? number_format($lng, 5) : '—' }}
                    </dd>
                </div>
            </dl>

            <button wire:click="finish" class="btn btn-primary btn-lg btn-block mt-20">
                Open my dashboard
            </button>
        </section>
    @endif

    {{-- Controls --}}
    @if ($step < 4)
        <div class="row g-10 mt-24">
            @if ($step > 1)
                <button class="btn btn-ghost" wire:click="back">Back</button>
            @endif
            <button class="btn btn-primary grow" wire:click="next">
                <span wire:loading.remove wire:target="next">{{ $step === 3 ? 'Create account' : 'Continue' }}</span>
                <span wire:loading wire:target="next">Working…</span>
            </button>
        </div>
        <p class="tiny dim center mt-16">
            By continuing you agree to EcoCycle's collection terms and payout schedule.
        </p>
    @endif
</div>

@push('scripts')
<script>
// Real geolocation, pushed into the Livewire component.
function ecLocate(btn) {
    if (!navigator.geolocation) { EC.toast('Geolocation unavailable on this device', 'warn'); return; }
    var original = btn.innerHTML;
    btn.innerHTML = EC.icons.svg('refresh', 'i i-sm') + ' Locating…';
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            window.Livewire.find(btn.closest('[wire\\:id]').getAttribute('wire:id'))
                .call('setCoords', pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
        },
        function () {
            btn.innerHTML = original;
            EC.toast('Permission denied — enter the address instead', 'warn');
        },
        { enableHighAccuracy: true, timeout: 8000 }
    );
}
</script>
@endpush
