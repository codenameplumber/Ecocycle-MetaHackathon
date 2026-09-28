<div class="col g-16" @if ($batch) wire:poll.15s @endif>
    @if ($batch)
        @if ($pins)
            <section class="card" style="overflow:hidden">
                <x-map :pins="$pins" :height="250" :legend="false" note="Updating every 15s" />
                <div class="card-body">
                    <div class="between">
                        <div class="row g-10">
                            <span class="avatar avatar-sm {{ $batch->agent?->user?->avatarVariant() }}">
                                {{ $batch->agent?->user?->initials() }}
                            </span>
                            <span>
                                <div class="small strong">{{ $batch->agent?->name() ?? 'Awaiting an agent' }}</div>
                                <div class="tiny dim">
                                    {{ $batch->agent?->vehicle_type }}
                                    @if ($batch->agent) · {{ number_format($batch->agent->rating, 1) }} ★ @endif
                                </div>
                            </span>
                        </div>
                        @if ($batch->agent?->user?->phone)
                            <a class="btn btn-sm btn-ghost" href="tel:{{ $batch->agent->user->phone }}">
                                <i data-icon="phone" class="i i-sm"></i> Call
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="card">
            <div class="card-head">
                <div class="row g-8">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--primary)"></span>
                    <h3>Progress</h3>
                    <span class="mono tiny dim">{{ $batch->shortCode() }}</span>
                </div>
                <span class="st {{ $batch->status->cssClass() }}">{{ $batch->status->label() }}</span>
            </div>
            <div class="card-body">
                <div class="between mb-6">
                    <span class="tiny dim">Step {{ $batch->status->step() }} of 10</span>
                    <span class="tiny dim figure-num">{{ round($pct) }}%</span>
                </div>
                <div class="bar mb-16"><span style="width:{{ $pct }}%"></span></div>

                <div class="steps">
                    @foreach ([\App\Enums\BatchStatus::Requested, \App\Enums\BatchStatus::Accepted, \App\Enums\BatchStatus::Collected, \App\Enums\BatchStatus::Received, \App\Enums\BatchStatus::Verified] as $s)
                        @php
                            $done = $batch->status->step() > $s->step();
                            $now  = $batch->status->step() === $s->step();
                        @endphp
                        <div class="step {{ $done ? 'done' : '' }} {{ $now ? 'active' : '' }}">
                            <span class="step-dot"><i data-icon="{{ $s->icon() }}" class="i i-sm"></i></span>
                            <span class="step-label">{{ $s->label() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($batch->agent)
            <section class="card card-flat">
                <div class="card-head"><h3>Your agent</h3><span class="badge badge-green">KYC verified</span></div>
                <div class="card-body">
                    <div class="row g-12">
                        <span class="avatar avatar-lg {{ $batch->agent->user->avatarVariant() }}">
                            {{ $batch->agent->user->initials() }}
                        </span>
                        <span class="grow">
                            <div class="item-title">{{ $batch->agent->name() }}</div>
                            <div class="item-sub">
                                {{ $batch->agent->vehicle_type }} · {{ $batch->agent->capacity_kg }} kg
                                · {{ $batch->agent->rating }} ★
                            </div>
                        </span>
                    </div>
                    @if ($batch->agent->user->phone)
                        <a class="btn btn-ghost btn-block mt-14"
                           href="tel:{{ preg_replace('/\s/', '', $batch->agent->user->phone) }}">
                            <i data-icon="phone" class="i i-sm"></i>
                            Call {{ explode(' ', $batch->agent->name())[0] }}
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section class="card card-flat">
            <div class="card-head">
                <h3>Batch details</h3>
                <span class="badge">three weights</span>
            </div>
            <div class="card-body">
                <div class="qr-tag mb-16">
                    {!! $qr !!}
                    <div>
                        <div class="tiny dim">Batch tag</div>
                        <div class="mono strong small">{{ $batch->code }}</div>
                        <p class="tiny dim mt-8">
                            The agent scanned this at pickup. It stays attached through inspection
                            and processing — anyone can scan it to see the same record.
                        </p>
                    </div>
                </div>

                <dl>
                    <div class="kv"><dt>You estimated</dt>
                        <dd class="figure-num">{{ $batch->expected_kg ? number_format($batch->expected_kg, 1) . ' kg' : '—' }}</dd>
                    </div>
                    <div class="kv"><dt>Agent declared</dt>
                        <dd class="figure-num">{{ $batch->collected_kg ? number_format($batch->collected_kg, 1) . ' kg' : 'pending' }}</dd>
                    </div>
                    <div class="kv"><dt><span class="strong">Facility verified</span></dt>
                        <dd class="strong figure-num {{ $batch->verified_kg ? 't-pos' : '' }}">
                            {{ $batch->verified_kg ? number_format($batch->verified_kg, 1) . ' kg' : 'pending' }}
                        </dd>
                    </div>
                    <div class="kv"><dt>Your payout</dt>
                        <dd class="strong figure-num">
                            {{ $batch->household_share ? '₦' . number_format((float) $batch->household_share, 2) : '—' }}
                        </dd>
                    </div>
                </dl>
                <div class="callout callout-info mt-14">
                    <i data-icon="info" class="i i-sm"></i>
                    <div>Payment is released once the facility weighs this batch. The verified figure is what you are paid on.</div>
                </div>
            </div>
        </section>

        <section class="card card-flat">
            <div class="card-head">
                <h3>Full trace</h3>
                <span class="badge">audit trail · {{ count($batch->timeline()) }} {{ Str::plural('event', count($batch->timeline())) }}</span>
            </div>
            <div class="card-body">
                <div class="timeline">
                    @foreach ($batch->timeline() as $t)
                        <div class="tl-item {{ $loop->last ? 'current' : 'done' }}">
                            <span class="tl-dot"><i data-icon="{{ $t['status']->icon() }}" class="i i-sm"></i></span>
                            <div>
                                <div class="tl-title">{{ $t['status']->label() }}</div>
                                <div class="tl-time">{{ $t['at']->format('j M, g:i A') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @else
        <div class="empty">
            <span class="empty-icon"><i data-icon="pin" class="i i-lg"></i></span>
            <div class="strong">Nothing in progress</div>
            <p class="small muted">Request a pickup and you can follow it here.</p>
            <a class="btn btn-primary mt-8" href="{{ route('app.request') }}" wire:navigate>Request a pickup</a>
        </div>
    @endif
</div>
