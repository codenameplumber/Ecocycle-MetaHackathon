<div>
    <div class="page-head">
        <div>
            <h1>{{ config('ecocycle.facility.name') }}</h1>
            <p class="muted">{{ config('ecocycle.facility.address') }} · {{ now()->format('j M Y') }}</p>
        </div>
        <div class="row g-10 no-print">
            <button class="btn btn-ghost" onclick="window.print()"><i data-icon="download" class="i i-sm"></i> Export</button>
        </div>
    </div>

    {{-- Plant readings. Colour only where something is out of range. --}}
    <section class="ops-bar mb-16">
        @php
            $digester = $lines->firstWhere('name', 'Anaerobic digester A');
            $sorting  = $lines->firstWhere('name', 'Sorting line B');
        @endphp
        <div><div class="ops-k">Gate queue</div><div class="ops-v">{{ $inTransit }}<small>loads</small></div></div>
        <div><div class="ops-k">Intake today</div><div class="ops-v">{{ number_format($intakeToday, 0) }}<small>kg</small></div></div>
        <div><div class="ops-k">Processed today</div><div class="ops-v">{{ number_format($processedToday, 0) }}<small>kg</small></div></div>
        <div class="{{ ($digester?->utilisation ?? 0) > 85 ? 'warn' : '' }}">
            <div class="ops-k">Digester A</div><div class="ops-v">{{ $digester?->utilisation ?? 0 }}<small>%</small></div>
        </div>
        <div><div class="ops-k">Sorting line B</div><div class="ops-v">{{ $sorting?->utilisation ?? 0 }}<small>%</small></div></div>
        <div><div class="ops-k">Agents online</div><div class="ops-v">{{ $agentsOnline }}</div></div>
        <div class="{{ $awaitingCount > 0 ? '' : '' }}">
            <div class="ops-k">Awaiting inspection</div><div class="ops-v">{{ $awaitingCount }}</div>
        </div>
        <div class="{{ $openAlerts > 0 ? 'alert' : '' }}">
            <div class="ops-k">Open alerts</div><div class="ops-v">{{ $openAlerts }}</div>
        </div>
    </section>

    {{-- KPIs --}}
    <section class="grid grid-4">
        <div class="card stat tone-green">
            <div class="stat-top"><span class="stat-label">Received today</span>
                <span class="stat-icon"><i data-icon="package" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($intakeToday, 0) }}<small>kg</small></div>
            <div class="stat-foot">across {{ $receivedTodayCount }} {{ Str::plural('batch', $receivedTodayCount) }} through the gate</div>
        </div>
        <div class="card stat tone-cyan">
            <div class="stat-top"><span class="stat-label">Processed today</span>
                <span class="stat-icon"><i data-icon="factory" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($processedToday, 0) }}<small>kg</small></div>
            <div class="stat-foot">{{ $counts['PROCESSING'] ?? 0 }} on the lines now</div>
        </div>
        <div class="card stat tone-amber">
            <div class="stat-top"><span class="stat-label">Awaiting inspection</span>
                <span class="stat-icon"><i data-icon="inspect" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">{{ number_format($awaitingKg, 0) }}<small>kg</small></div>
            <div class="stat-foot">{{ $awaitingCount }} batches queued</div>
        </div>
        <div class="card stat tone-violet">
            <div class="stat-top"><span class="stat-label">Settled this month</span>
                <span class="stat-icon"><i data-icon="wallet" class="i i-sm"></i></span></div>
            <div class="stat-value figure-num">₦{{ number_format($settledMonth, 0) }}</div>
            <div class="stat-foot">credited to households and agents</div>
        </div>
    </section>

    {{-- Intake vs processing + mix --}}
    <section class="grid mt-16" style="grid-template-columns:1.55fr 1fr">
        <div class="card">
            <div class="card-head">
                <div><h3>Intake against processing</h3>
                    <p class="tiny dim mt-4">Solid is received, dashed is processed</p></div>
            </div>
            <div class="card-body">
                <svg class="chart" data-chart="area" data-height="230"
                     data-values="{{ $chartReceived->implode(',') }}"
                     data-compare="{{ $chartProcessed->implode(',') }}"
                     data-labels="{{ $chartLabels->implode(',') }}"></svg>
            </div>
            <div class="card-foot">
                <div class="row g-24 wrap">
                    <span class="row g-6 tiny"><span style="width:10px;height:2px;background:var(--primary);display:inline-block"></span>Received</span>
                    <span class="row g-6 tiny"><span style="width:10px;height:2px;background:var(--c2);display:inline-block"></span>Processed</span>
                    <span class="grow"></span>
                    <span class="tiny dim">Backlog: {{ number_format($awaitingKg, 0) }} kg</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Waste mix</h3><span class="badge">all time</span></div>
            <div class="card-body">
                <div class="mixbar">
                    @foreach ($mix as $m)
                        <span style="width:{{ $m->kg / $mixTotal * 100 }}%;background:{{ $m->color }}"></span>
                    @endforeach
                </div>
                <div class="mt-16">
                    @foreach ($mix as $m)
                        <div class="between" style="padding:5px 0">
                            <span class="row g-8 small">
                                <span style="width:9px;height:9px;border-radius:2px;background:{{ $m->color }}"></span>
                                {{ $m->label }}
                            </span>
                            <span class="small strong figure-num">{{ number_format($m->kg / $mixTotal * 100, 1) }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Pipeline counters --}}
    <section class="card mt-16">
        <div class="card-head">
            <h3>Pipeline right now</h3>
            <a class="btn btn-sm btn-ghost" href="{{ route('console.pipeline') }}" wire:navigate>
                Open the board <i data-icon="chevR" class="i i-sm"></i>
            </a>
        </div>
        <div class="card-body">
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(104px,1fr));gap:8px">
                @foreach (\App\Enums\BatchStatus::pipeline() as $s)
                    <a class="card card-flat" href="{{ route('console.pipeline') }}" wire:navigate style="padding:13px">
                        <div class="row g-6 mb-8">
                            <i data-icon="{{ $s->icon() }}" class="i i-sm"></i>
                            <span class="tiny dim truncate">{{ $s->label() }}</span>
                        </div>
                        <div class="strong figure-num" style="font-size:21px">{{ $counts[$s->value] ?? 0 }}</div>
                        <div class="tiny dim">batches</div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Capacity + alerts --}}
    <section class="grid mt-16" style="grid-template-columns:1fr 1fr">
        <div class="card">
            <div class="card-head">
                <h3>Capacity today</h3>
                <span class="badge {{ $capacityUsed > 85 ? 'badge-amber' : '' }}">{{ number_format($capacityUsed, 1) }}% used</span>
            </div>
            <div class="card-body col g-14">
                <div class="row g-20">
                    <svg style="width:120px;flex:none" data-chart="ring"
                         data-value="{{ round($capacityUsed) }}"
                         data-label="of {{ number_format($dailyCapacity) }} kg"></svg>
                    <div class="grow col g-12">
                        @foreach ($lines as $l)
                            <div>
                                <div class="between mb-4">
                                    <span class="tiny dim">{{ $l->name }}</span>
                                    <span class="small strong figure-num">{{ $l->utilisation }}%</span>
                                </div>
                                <div class="bar {{ $l->utilisation > 85 ? 'amber' : '' }}">
                                    <span style="width:{{ $l->utilisation }}%"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($strainedLine)
                    <div class="callout callout-warn">
                        <i data-icon="alert" class="i i-sm"></i>
                        <div>
                            {{ $strainedLine->name }} is at {{ $strainedLine->utilisation }}% of its
                            {{ number_format($strainedLine->capacity_kg_day) }} kg/day ceiling. Divert roughly
                            {{ number_format($strainedLine->capacity_kg_day * ($strainedLine->utilisation - 80) / 100, 0) }} kg
                            or stage it overnight.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col g-16">
            <div class="card">
                <div class="card-head">
                    <h3>Needs attention</h3>
                <a class="small strong" style="color:var(--primary)" href="{{ route('console.integrity') }}" wire:navigate>All alerts</a>
            </div>
            <ul class="list">
                @forelse ($alerts as $a)
                    <li>
                        <a class="item" href="{{ route('console.integrity') }}" wire:navigate>
                            <span class="qa-ic" style="width:32px;height:32px;background:var(--{{ $a->severity->value === 'high' ? 'danger' : 'warn' }}-soft);color:var(--{{ $a->severity->value === 'high' ? 'danger' : 'warn' }})">
                                <i data-icon="alert" class="i i-sm"></i>
                            </span>
                            <span class="grow" style="min-width:0">
                                <div class="item-title truncate">{{ $a->kindLabel() }} · {{ $a->agent?->name() ?? '—' }}</div>
                                <div class="item-sub truncate">{{ $a->detail }}</div>
                            </span>
                            <span class="item-end">
                                <span class="badge {{ $a->severity->badgeClass() }}">{{ $a->severity->value }}</span>
                                <div class="tiny dim mt-4">{{ $a->created_at->diffForHumans(short: true) }}</div>
                            </span>
                        </a>
                    </li>
                @empty
                    <li><div class="empty">
                        <span class="empty-icon"><i data-icon="ok" class="i i-lg"></i></span>
                        <div class="strong">Nothing flagged</div>
                        <p class="small muted">Every batch is inside the variance tolerance.</p>
                    </div></li>
                @endforelse
                </ul>
            </div>

            {{-- The gate, as the operator on shift sees it. --}}
            <div class="cam">
                <div class="media media-16x9">
                    <img src="{{ asset('assets/img/facility-scale.jpg') }}"
                         alt="The inbound weighbridge at the facility gate" loading="lazy" width="1000" height="700">
                    <span class="media-tag">GATE 1 · INBOUND</span>
                </div>
                <div class="cam-bar">
                    <span class="cam-live">LIVE</span>
                    <span class="sep">·</span>
                    <span>Weighbridge 1</span>
                    <span class="grow"></span>
                    <span class="mono">
                        @if ($atGate)
                            {{ $atGate->agent?->code ?? '—' }} · {{ number_format((float) ($atGate->net_kg ?? $atGate->gross_kg ?? 0), 1) }} kg
                        @else
                            Gate clear
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- Live network --}}
    <section class="card mt-16">
        <div class="card-head">
            <h3>Live network</h3>
            <div class="row g-8">
                <span class="badge badge-green"><span class="dot"></span>{{ $agentsOnline }} agents online</span>
                <span class="badge badge-blue">{{ $inTransit }} batches in transit</span>
            </div>
        </div>
        <x-map :pins="$pins" :height="360" />
    </section>

    {{-- Latest batches --}}
    <section class="card mt-16">
        <div class="card-head">
            <h3>Latest batches</h3>
            <a class="btn btn-sm btn-ghost" href="{{ route('console.batches') }}" wire:navigate>
                All batches <i data-icon="chevR" class="i i-sm"></i>
            </a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Batch</th><th>Household</th><th>Agent</th><th>Category</th>
                    <th class="num">Weight</th><th>Status</th><th class="num">Value</th>
                </tr></thead>
                <tbody>
                @foreach ($latest as $b)
                    <tr>
                        <td class="mono">{{ $b->shortCode() }}</td>
                        <td>{{ $b->household->name() }}</td>
                        <td>{{ $b->agent?->name() ?? '—' }}</td>
                        <td class="small">{{ $b->effectiveCategory()?->label }}</td>
                        <td class="num figure-num">{{ $b->bestKnownKg() ? number_format($b->bestKnownKg(), 1) . ' kg' : '—' }}</td>
                        <td><span class="st {{ $b->status->cssClass() }}">{{ $b->status->label() }}</span></td>
                        <td class="num figure-num">{{ $b->value_naira ? '₦' . number_format((float) $b->value_naira, 0) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
