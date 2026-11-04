<div class="col g-16" data-ec-position>
    {{-- Every distance below is measured from this point, so the screen says
         how old it is rather than presenting a stale fix as a live one. --}}
    <section class="card card-pad">
        <div class="row g-10">
            <i data-icon="nav" class="i i-sm" style="color:var(--primary)"></i>
            <span class="grow" style="min-width:0">
                <div class="small strong">Ranked from your position</div>
                <div class="tiny dim">
                    @if ($agent->last_lat === null)
                        No position on file — jobs are ranked from the facility
                    @elseif ($posAge === null)
                        <span class="mono">{{ number_format($agent->last_lat, 4) }}, {{ number_format($agent->last_lng, 4) }}</span>
                    @elseif ($posAge < 2)
                        Updated just now ·
                        <span class="mono">{{ number_format($agent->last_lat, 4) }}, {{ number_format($agent->last_lng, 4) }}</span>
                    @else
                        Updated {{ $posAge }} min ago ·
                        <span class="mono">{{ number_format($agent->last_lat, 4) }}, {{ number_format($agent->last_lng, 4) }}</span>
                    @endif
                </div>
            </span>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-ec-locate data-target="pingPosition">
                <i data-icon="refresh" class="i i-sm"></i>
                <span data-ec-locate-label>Update</span>
            </button>
        </div>
    </section>

    <section class="capacity-banner {{ $capacity['level'] }}">
        <div class="between">
            <span class="row g-8"><i data-icon="truck" class="i i-sm"></i><strong>Vehicle capacity</strong></span>
            <span class="badge {{ $capacity['level'] === 'full' ? 'badge-red' : ($capacity['level'] === 'warn' ? 'badge-amber' : 'badge-green') }}">{{ $capacity['pct'] }}% committed</span>
        </div>
        <div class="meter {{ $capacity['level'] }} mt-10"><span style="width:{{ $capacity['pct'] }}%"></span></div>
        <div class="between tiny dim mt-8">
            <span>{{ number_format($capacity['committed'], 1) }} kg committed · {{ number_format($lastHourKg, 1) }} kg collected in the last hour</span>
            <span class="figure-num">{{ number_format($remaining, 1) }} kg left</span>
        </div>
        @if ($capacity['level'] === 'full')
            <div class="callout callout-warn mt-10"><i data-icon="alert" class="i i-sm"></i><div><strong>Vehicle full.</strong> New jobs are locked — head to the facility to deliver.</div></div>
            <a class="btn btn-primary btn-block mt-10" href="{{ route('agent.route') }}" wire:navigate>Head to facility</a>
        @elseif ($capacity['level'] === 'warn')
            <p class="tiny dim mt-8">Nearly full — only small jobs will still fit.</p>
        @endif
    </section>

    @if (false)
    <div class="callout callout-info">
        <i data-icon="scale" class="i i-sm"></i>
        <div>
            <strong>{{ number_format($remaining, 1) }} kg of capacity left.</strong>
            Jobs above that are dimmed — deliver your current load first.
        </div>
    </div>

    @endif

    @if (count($pins) > 1)
        <section class="card card-flat" style="overflow:hidden">
            <div class="card-head"><h3>Closest first</h3><span class="badge">{{ $jobs->count() }} on offer</span></div>
            <x-map :pins="$pins" :height="220" :legend="false" />
            <ul class="list">
                @foreach ($jobs->sortBy('distance_km')->take(4) as $j)
                    <li wire:key="near-{{ $j->id }}">
                        <button class="item" wire:click="ask({{ $j->id }})">
                            <span class="qa-ic" style="width:30px;height:30px;background:var(--surface-3);color:var(--text-2)">
                                <i data-icon="pin" class="i i-sm"></i>
                            </span>
                            <span class="grow" style="min-width:0">
                                <div class="item-title truncate">{{ $j->household->name() }}</div>
                                <div class="item-sub truncate">
                                    {{ $j->household->area?->name }} · {{ $j->effectiveCategory()?->label }}
                                </div>
                            </span>
                            <span class="item-end">
                                <div class="small strong figure-num">{{ number_format($j->distance_km, 1) }} km</div>
                                <div class="tiny dim figure-num">{{ number_format((float) ($j->expected_kg ?? 0), 1) }} kg</div>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="col g-10">
        @forelse ($jobs as $j)
            @include('livewire.agent.partials.job-card', ['j' => $j, 'remaining' => $remaining, 'confirmable' => true])
        @empty
            <div class="empty">
                <span class="empty-icon"><i data-icon="boxes" class="i i-lg"></i></span>
                <div class="strong">No open jobs</div>
                <p class="small muted">Nothing on the marketplace right now. Check back shortly.</p>
            </div>
        @endforelse
    </div>

    <p class="tiny dim center">
        Jobs are ranked by distance, your remaining capacity, the value of the stream and how soon the
        collection window closes — not simply by proximity.
    </p>
    {{-- Accept confirmation. Accepting is a commitment to turn up, so it asks. --}}
    @if ($this->confirming)
        @php
            $c = $this->confirming;
            $value = ($c->expected_kg ?? 0) * (float) ($c->category?->rate_per_kg ?? 0);
            $mine = $value * (float) config('ecocycle.agent_share');
        @endphp
        <div class="scrim" wire:click.self="dismiss">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="card-head">
                    <h3>Accept this job?</h3>
                    <button class="btn btn-icon btn-quiet" wire:click="dismiss" aria-label="Close"><i data-icon="close"></i></button>
                </div>
                <div class="card-body">
                    <div class="row g-12 mb-16">
                        <span class="avatar {{ $c->household->user?->avatarVariant() }}">{{ $c->household->user?->initials() }}</span>
                        <span>
                            <div class="strong">{{ $c->household->name() }}</div>
                            <div class="small muted">
                                {{ $c->household->area?->name }} · {{ number_format($c->distance_km ?? 0, 1) }} km away
                            </div>
                        </span>
                    </div>
                    <dl>
                        <div class="kv"><dt>Estimated weight</dt><dd class="figure-num">{{ number_format($c->expected_kg ?? 0, 1) }} kg</dd></div>
                        <div class="kv"><dt>Household evidence</dt><dd>{{ $c->photo_path ? 'Photo snapped ✓' : 'No photo' }}@if ($c->classification) · {{ $c->classification['confidence'] ?? 0 }}% read @endif</dd></div>
                        <div class="kv"><dt>Fits your vehicle</dt><dd>{{ ($c->expected_kg ?? 0) <= $remaining ? 'Yes — ' . number_format($remaining - ($c->expected_kg ?? 0), 1) . ' kg to spare' : 'No — over capacity' }}</dd></div>
                        <div class="kv"><dt>Category</dt><dd>{{ $c->category?->label }}</dd></div>
                        <div class="kv"><dt>Window</dt>
                            <dd>{{ $c->window_end ? $c->window_end->format('D, g A') : 'Flexible' }}</dd></div>
                        <div class="kv"><dt>Batch value (est.)</dt><dd class="figure-num">₦{{ number_format($value, 2) }}</dd></div>
                        <div class="kv">
                            <dt><span class="strong">Your share (40%)</span></dt>
                            <dd class="strong figure-num" style="color:var(--primary)">₦{{ number_format($mine, 2) }}</dd>
                        </div>
                    </dl>
                    <div class="callout mt-16">
                        <div class="tiny">
                            Final payment follows the verified weight at the facility. Declaring more than
                            you deliver counts against your integrity score.
                        </div>
                    </div>
                </div>
                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" wire:click="dismiss">Not now</button>
                    <button class="btn btn-primary" wire:click="accept({{ $c->id }})" @disabled(($c->expected_kg ?? 0) > $remaining)>
                        <span wire:loading.remove wire:target="accept">Accept the job</span>
                        <span wire:loading wire:target="accept">Claiming…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
