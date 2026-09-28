<div class="col g-16">

    {{-- Wallet --}}
    <section class="hero">
        <div class="hero-label">Wallet balance</div>
        <div class="hero-value figure-num">₦{{ number_format($balance, 2) }}</div>
        <div class="hero-foot">
            <div><div class="k">This month</div><div class="v figure-num">{{ number_format($monthKg, 1) }} kg</div></div>
            <div><div class="k">Batches</div><div class="v figure-num">{{ $monthBatches }}</div></div>
            <div><div class="k">Lifetime</div><div class="v figure-num">{{ number_format($lifetimeKg, 0) }} kg</div></div>
        </div>
    </section>

    {{-- Quick actions --}}
    <nav class="qa-grid">
        <a class="qa" href="{{ route('app.request') }}" wire:navigate><span class="qa-ic"><i data-icon="plus" class="i i-sm"></i></span>Request</a>
        <a class="qa v2" href="{{ route('app.track') }}" wire:navigate><span class="qa-ic"><i data-icon="pin" class="i i-sm"></i></span>Track</a>
        <a class="qa v3" href="{{ route('app.wallet') }}" wire:navigate><span class="qa-ic"><i data-icon="history" class="i i-sm"></i></span>History</a>
        <a class="qa v4" href="{{ route('app.wallet') }}" wire:navigate><span class="qa-ic"><i data-icon="wallet" class="i i-sm"></i></span>Wallet</a>
    </nav>

    {{-- Active collection --}}
    @if ($active)
        <section class="card">
            <div class="card-head">
                <div class="row g-8">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--primary)"></span>
                    <h3>Collection in progress</h3>
                </div>
                <span class="st {{ $active->status->cssClass() }}">{{ $active->status->label() }}</span>
            </div>
            <div class="card-body">
                {{-- Progress rail --}}
                <div class="steps">
                    @foreach ([\App\Enums\BatchStatus::Requested, \App\Enums\BatchStatus::Accepted, \App\Enums\BatchStatus::Collected, \App\Enums\BatchStatus::Received, \App\Enums\BatchStatus::Verified] as $s)
                        @php $done = $active->status->step() > $s->step(); $now = $active->status->step() === $s->step(); @endphp
                        <div class="step {{ $done ? 'done' : '' }} {{ $now ? 'active' : '' }}">
                            <span class="step-dot"><i data-icon="{{ $s->icon() }}" class="i i-sm"></i></span>
                            <span class="step-label">{{ $s->label() }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="divider mt-16 mb-16"></div>

                @if ($active->agent)
                    <div class="row g-12">
                        <span class="avatar presence on {{ $active->agent->user->avatarVariant() }}">{{ $active->agent->user->initials() }}</span>
                        <span class="grow">
                            <div class="item-title">{{ $active->agent->name() }}</div>
                            <div class="item-sub">{{ $active->agent->vehicle_type }} · {{ $active->agent->rating }} ★</div>
                        </span>
                        @if ($active->agent->user->phone)
                            <a class="btn btn-icon btn-ghost" href="tel:{{ preg_replace('/\s/', '', $active->agent->user->phone) }}" aria-label="Call agent">
                                <i data-icon="phone" class="i i-sm"></i>
                            </a>
                        @endif
                    </div>
                @else
                    <p class="small muted">Waiting for an agent to accept. Usually under 20 minutes.</p>
                @endif

                <dl class="mt-16">
                    <div class="kv"><dt>Batch tag</dt><dd class="mono">{{ $active->code }}</dd></div>
                    <div class="kv"><dt>Declared weight</dt>
                        <dd class="figure-num">{{ $active->collected_kg ? number_format($active->collected_kg, 1) . ' kg' : 'pending' }}</dd></div>
                    <div class="kv"><dt>Estimated share</dt>
                        <dd class="figure-num">{{ $active->household_share ? '₦' . number_format((float) $active->household_share, 2) : '—' }}</dd></div>
                </dl>

                <a class="btn btn-primary btn-block mt-16" href="{{ route('app.track') }}" wire:navigate>Track this collection</a>
            </div>
        </section>
    @else
        <section class="card">
            <div class="card-body">
                <div class="empty">
                    <span class="empty-icon"><i data-icon="package" class="i i-lg"></i></span>
                    <div class="strong">No active collection</div>
                    <p class="small muted">Request a pickup and a nearby agent will take it.</p>
                    <a class="btn btn-primary mt-8" href="{{ route('app.request') }}" wire:navigate>Request a pickup</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Prediction --}}
    <section class="card-ruled">
        <div class="block-head">
            <h3>Your next pickup</h3>
            <span class="badge badge-violet">Predicted</span>
        </div>
        <div class="between">
            <div>
                <div class="stat-value figure-num" style="font-size:30px">{{ number_format($predicted, 1) }}<small>kg</small></div>
                <div class="small muted mt-4">based on your own history</div>
            </div>
        </div>
        <p class="tiny dim mt-12">
            The mean of your last eight verified weights. It plans the round — it never decides your payment.
        </p>
        <a class="btn btn-soft btn-block mt-14" href="{{ route('app.request') }}" wire:navigate>Request a pickup now</a>
    </section>

    {{-- Recent --}}
    <section class="card card-flat">
        <div class="card-head">
            <h3>Recent collections</h3>
            <a class="small strong" style="color:var(--primary)" href="{{ route('app.wallet') }}" wire:navigate>See all</a>
        </div>
        <ul class="list">
            @forelse ($recent as $b)
                <li>
                    <div class="item">
                        <span class="thumb">
                            <img src="{{ asset('assets/img/cat-' . $b->effectiveCategory()?->key . '.jpg') }}" alt="" loading="lazy">
                        </span>
                        <span class="grow" style="min-width:0">
                            <div class="item-title">
                                {{ $b->bestKnownKg() ? number_format($b->bestKnownKg(), 1) . ' kg' : '—' }}
                                · {{ $b->effectiveCategory()?->label }}
                            </div>
                            <div class="item-sub truncate">
                                {{ optional($b->requested_at)->diffForHumans(short: true) }} · {{ $b->shortCode() }}
                            </div>
                        </span>
                        <span class="item-end">
                            <div class="strong figure-num">
                                {{ $b->household_share ? '₦' . number_format((float) $b->household_share, 0) : '—' }}
                            </div>
                            <span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span>
                        </span>
                    </div>
                </li>
            @empty
                <li><div class="empty">
                    <span class="empty-icon"><i data-icon="history" class="i i-lg"></i></span>
                    <div class="strong">Nothing yet</div>
                    <p class="small muted">Your collections will appear here.</p>
                </div></li>
            @endforelse
        </ul>
    </section>

    {{-- Your impact --}}
    <section class="card card-pad">
        <div class="block-head"><h3>Your impact</h3><span class="badge badge-green">All time</span></div>

        @if (count($trend) > 1)
            <div class="between mb-8">
                <span class="tiny dim">Verified weight per collection</span>
                <span class="tiny dim">Last {{ count($trend) }}</span>
            </div>
            <svg class="chart mb-16" data-chart="bars" data-height="110"
                 data-values="{{ implode(',', $trend) }}"
                 data-labels="{{ implode(',', range(1, count($trend))) }}"
                 data-color="var(--primary)"></svg>
        @endif

        <div class="grid grid-keep-3" style="gap:12px">
            <div>
                <div class="stat-value figure-num" style="font-size:20px">{{ number_format($lifetimeKg, 0) }}<small>kg</small></div>
                <div class="tiny dim">Diverted from landfill</div>
            </div>
            <div>
                <div class="stat-value figure-num" style="font-size:20px">{{ number_format($biogasM3, 0) }}<small>m³</small></div>
                <div class="tiny dim">Biogas generated</div>
            </div>
            <div>
                {{-- One household is kilograms, not tonnes: 29 kg would otherwise read "0.0 t". --}}
                <div class="stat-value figure-num" style="font-size:20px">@if ($co2eKg >= 1000){{ number_format($co2eKg / 1000, 1) }}<small>t</small>@else{{ number_format($co2eKg, 0) }}<small>kg</small>@endif</div>
                <div class="tiny dim">CO₂e avoided</div>
            </div>
        </div>

        @if ($mix->isNotEmpty())
            <div class="mt-16">
                <div class="between mb-8">
                    <span class="tiny dim">Your waste mix</span>
                    <span class="tiny dim">Last 90 days</span>
                </div>
                <div class="mixbar">
                    @foreach ($mix as $m)
                        <span style="width:{{ $m->kg / $mixTotal * 100 }}%;background:{{ $m->color }}"
                              title="{{ $m->label }}"></span>
                    @endforeach
                </div>
                <div class="row wrap g-12 mt-10">
                    @foreach ($mix as $m)
                        <span class="row g-6 tiny">
                            <span style="width:9px;height:9px;border-radius:2px;background:{{ $m->color }}"></span>
                            {{ $m->label }}
                            <span class="dim figure-num">{{ number_format($m->kg / $mixTotal * 100, 1) }}%</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- Where it goes --}}
    <section class="card card-flat" style="overflow:hidden">
        <div class="media media-16x9 media-flat media-scrim">
            <img src="{{ asset('assets/img/facility-digester.jpg') }}" alt="The digester at the Lagos facility" loading="lazy">
            <div class="media-cap">
                <div class="tiny">{{ config('ecocycle.facility.name') }}</div>
                <div class="strong" style="font-size:15px">Where your organic waste ends up</div>
            </div>
        </div>
        <div class="card-body">
            <p class="small muted">
                Your food and garden waste goes into digester A and comes out as biogas.
                Everything you have sent so far totals <strong>{{ number_format($lifetimeKg, 1) }} kg</strong>.
            </p>
        </div>
    </section>
</div>
