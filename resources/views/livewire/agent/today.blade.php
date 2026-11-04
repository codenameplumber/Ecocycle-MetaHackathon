<div class="col g-16" data-ec-position>

    {{-- Shift status --}}
    <section class="hero">
        <div class="between">
            <div>
                <div class="hero-label">Earned today</div>
                <div class="hero-value figure-num">₦{{ number_format($earnedToday, 2) }}</div>
            </div>
            <button wire:click="toggleOnline" class="col g-6" style="align-items:flex-end;background:none">
                <span class="tiny" style="opacity:.85;color:#fff">{{ $agent->is_online ? 'Online' : 'Offline' }}</span>
                <span class="track" style="background:{{ $agent->is_online ? 'rgba(255,255,255,.9)' : 'rgba(255,255,255,.32)' }};
                       width:40px;height:23px;border-radius:999px;position:relative;display:block">
                    <span style="position:absolute;top:2.5px;{{ $agent->is_online ? 'right:2.5px' : 'left:2.5px' }};
                          width:18px;height:18px;border-radius:50%;background:{{ $agent->is_online ? 'var(--primary)' : '#fff' }}"></span>
                </span>
            </button>
        </div>
        <div class="hero-foot">
            <div><div class="k">Jobs today</div><div class="v figure-num">{{ $jobsToday }}</div></div>
            <div><div class="k">This week</div><div class="v figure-num">{{ number_format($weekKg, 1) }} kg</div></div>
            <div><div class="k">Balance</div><div class="v figure-num">₦{{ number_format($balance, 0) }}</div></div>
        </div>
    </section>

    {{-- Load on board --}}
    <section class="card card-flat">
        <div class="card-head">
            <h3>Load on board</h3>
            <span class="badge {{ $onBoard / max(1, $agent->capacity_kg) > 0.85 ? 'badge-red' : 'badge-amber' }}">
                {{ (int) round($onBoard / max(1, $agent->capacity_kg) * 100) }}% full
            </span>
        </div>
        <div class="card-body">
            <div class="row g-16">
                <svg style="width:104px;flex:none" data-chart="ring"
                     data-value="{{ (int) round($onBoard / max(1, $agent->capacity_kg) * 100) }}"
                     data-label="of {{ $agent->capacity_kg }} kg"></svg>
                <div class="grow col g-8">
                    <div class="between small"><span class="muted">On board</span>
                        <span class="strong figure-num">{{ number_format($onBoard, 1) }} kg</span></div>
                    <div class="between small"><span class="muted">Remaining</span>
                        <span class="strong figure-num">{{ number_format($remaining, 1) }} kg</span></div>
                    <div class="between small"><span class="muted">Last hour</span>
                        <span class="strong figure-num">{{ number_format($lastHourKg, 1) }} kg collected</span></div>
                    <div class="between small"><span class="muted">Committed</span>
                        <span class="strong figure-num">{{ number_format($capacity['committed'], 1) }} kg · {{ $capacity['pct'] }}%</span></div>
                    <div class="between small"><span class="muted">Batches held</span>
                        <span class="strong figure-num">{{ $held }}</span></div>
                    <a class="btn btn-soft btn-sm mt-4" href="{{ route('agent.route') }}" wire:navigate>Head to facility</a>
                </div>
            </div>
        </div>
        <div class="media media-21x9 media-flat media-scrim">
            <img src="{{ asset('assets/img/vehicle-tricycle.jpg') }}" alt="Registered vehicle" loading="lazy">
            <div class="media-cap">
                <div class="tiny">Registered vehicle · {{ $agent->vehicle_type }} · {{ $agent->capacity_kg }} kg
                    @if ($agent->plate) · {{ $agent->plate }} @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Current job --}}
    @if ($current)
        <section class="card">
            <div class="card-head">
                <div class="row g-8">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--primary)"></span>
                    <h3>Current job</h3>
                </div>
                <span class="st {{ $current->status->cssClass() }}">{{ $current->status->label() }}</span>
            </div>
            <div class="card-body">
                <div class="row g-12">
                    <span class="avatar {{ $current->household->user->avatarVariant() }}">
                        {{ $current->household->user->initials() }}
                    </span>
                    <span class="grow">
                        <div class="item-title">{{ $current->household->name() }}</div>
                        <div class="item-sub">
                            {{ $current->household->area?->name }} ·
                            {{ $current->collected_kg ? 'declared ' . number_format($current->collected_kg, 1) . ' kg'
                               : 'est. ' . number_format($current->expected_kg, 1) . ' kg' }}
                        </div>
                    </span>
                    @if ($current->household->user->phone)
                        <a class="btn btn-icon btn-ghost" href="tel:{{ preg_replace('/\s/', '', $current->household->user->phone) }}">
                            <i data-icon="phone" class="i i-sm"></i>
                        </a>
                    @endif
                </div>
                <a class="btn btn-primary btn-block mt-14" href="{{ route('agent.pickup', $current) }}" wire:navigate>
                    Continue pickup
                </a>
            </div>
        </section>
    @endif

    {{-- Nearby jobs --}}
    {{-- Availability --}}
    <section class="card card-pad">
        <div class="between">
            <div>
                <div class="small strong">{{ $agent->is_online ? 'On shift' : 'Off shift' }}</div>
                <div class="tiny dim">
                    {{ $agent->is_online
                        ? 'You are being offered jobs near you.'
                        : 'No new offers while you are off shift.' }}
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" wire:click="toggleOnline" @checked($agent->is_online)>
                <span class="track"></span>
            </label>
        </div>
        @if ($remaining < 10)
            <div class="callout callout-warn mt-14">
                <i data-icon="alert" class="i i-sm"></i>
                <div>
                    Only {{ number_format($remaining, 1) }} kg of capacity left. Deliver what is on
                    board before taking another job.
                </div>
            </div>
        @endif
    </section>

    {{-- Best match right now --}}
    @if ($nearby->isNotEmpty())
        @php($best = $nearby->first())
        <div class="ai-card">
            <div class="between mb-8">
                <span class="ai-tag"><i data-icon="sparkles" class="i i-sm"></i> Recommended for you</span>
                <span class="confidence">{{ $best->match_score }}%<span class="bar bar-sm" style="width:52px"><span style="width:{{ $best->match_score }}%;background:var(--violet)"></span></span></span>
            </div>
            <div class="strong" style="font-size:14.5px;color:var(--text)">
                {{ $best->household->name() }} · {{ number_format((float) ($best->expected_kg ?? 0), 1) }} kg
                {{ $best->effectiveCategory()?->label }}
            </div>
            <p class="small muted mt-4">
                {{ number_format($best->distance_km, 1) }} km away, inside your remaining
                {{ number_format($remaining, 1) }} kg. Ranked on distance, capacity, stream value and
                how soon the window closes — not simply proximity.
            </p>
        </div>
    @endif

    {{-- This week --}}
    <section class="card card-pad">
        <div class="block-head">
            <h3>This week</h3>
            @if ($weekDelta !== null)
                <span class="badge {{ $weekDelta >= 0 ? 'badge-green' : 'badge-amber' }}">
                    {{ $weekDelta >= 0 ? '+' : '' }}{{ number_format($weekDelta, 1) }}%
                </span>
            @else
                <span class="badge">first week</span>
            @endif
        </div>
        <svg class="chart" data-chart="bars" data-height="130" data-color="var(--primary)"
             data-values="{{ $week->pluck('kg')->implode(',') }}"
             data-labels="{{ $week->pluck('label')->implode(',') }}"></svg>
        <div class="grid grid-keep-3 mt-14" style="gap:12px">
            <div>
                <div class="strong figure-num">₦{{ number_format($weekEarned, 0) }}</div>
                <div class="tiny dim">Earned</div>
            </div>
            <div>
                <div class="strong figure-num">{{ number_format($weekCollected, 0) }} kg</div>
                <div class="tiny dim">Collected</div>
            </div>
            <div>
                <div class="strong figure-num">{{ number_format($agent->rating, 1) }} ★</div>
                <div class="tiny dim">Rating</div>
            </div>
        </div>
    </section>

    <section>
        <div class="sec-head mb-12">
            <h3>Jobs near you</h3>
            <a href="{{ route('agent.jobs') }}" wire:navigate>See all</a>
        </div>
        <div class="col g-10">
            @forelse ($nearby as $j)
                @include('livewire.agent.partials.job-card', ['j' => $j, 'remaining' => $remaining])
            @empty
                <div class="empty">
                    <span class="empty-icon"><i data-icon="boxes" class="i i-lg"></i></span>
                    <div class="strong">No open jobs</div>
                    <p class="small muted">Nothing on the marketplace right now.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
