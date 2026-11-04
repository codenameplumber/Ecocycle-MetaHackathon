<div>
    <div class="page-head">
        <div>
            <h1>Analytics and intelligence</h1>
            <p class="muted">Plain-language answers computed from your own figures.</p>
        </div>
        <div class="row g-10 no-print">
            <span class="badge {{ $aiEnabled ? 'badge-violet' : 'badge-amber' }}">
                {{ $aiEnabled ? 'AI connected' : 'Local insights — no API key' }}
            </span>
        </div>
    </div>

    {{-- Question box --}}
    <section class="card mb-16">
        <div class="card-head">
            <div class="row g-8">
                <i data-icon="sparkles" class="i i-sm" style="color:var(--violet)"></i>
                <h3>Ask about the network</h3>
            </div>
            <span class="badge badge-violet">Natural language</span>
        </div>
        <div class="card-body">
            <form wire:submit="ask" class="row g-10">
                <div class="input-group grow">
                    <i data-icon="search" class="i i-sm"></i>
                    <input class="input" wire:model="question" placeholder="What will we receive next week?">
                </div>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="ask">Ask</span>
                    <span wire:loading wire:target="ask">Thinking…</span>
                </button>
            </form>

            <div class="row g-6 wrap mt-12">
                @foreach ($suggestions as $s)
                    <button class="chip" wire:click="useSuggestion('{{ addslashes($s) }}')">{{ $s }}</button>
                @endforeach
            </div>

            @if ($answer)
                <div class="ai-card mt-16">
                    <div class="between mb-8">
                        <span class="ai-tag">
                            <i data-icon="sparkles" class="i i-sm"></i>
                            {{ $answer['generated'] ? 'Generated answer' : 'Computed answer' }}
                        </span>
                        <span class="tiny dim">{{ $answer['source'] }}</span>
                    </div>
                    <p style="color:var(--text);line-height:1.6">{{ $answer['answer'] }}</p>
                    @unless ($answer['generated'])
                        <p class="tiny dim mt-8">
                            No AI key is configured, so this was assembled locally from the same figures.
                            Add <span class="mono">OPENAI_API_KEY</span> to <span class="mono">.env</span> for
                            full natural-language answers.
                        </p>
                    @endunless
                </div>
            @endif
        </div>
    </section>

    {{-- Forecast --}}
    <section class="grid mb-16" style="grid-template-columns:1.5fr 1fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Seven-day intake forecast</h3>
                    <p class="tiny dim mt-4">Predicted arrivals against the facility ceiling</p>
                </div>
                @if ($forecastAccuracy !== null)
                    <span class="badge badge-violet">{{ number_format($forecastAccuracy, 0) }}% within actual</span>
                @else
                    <span class="badge">building history</span>
                @endif
            </div>
            <div class="card-body">
                <svg class="chart" data-chart="area" data-height="220"
                     data-color="var(--violet)"
                     data-values="{{ collect($forecast)->pluck('kg')->implode(',') }}"
                     data-compare="{{ collect($forecast)->map(fn () => round($dailyCeiling))->implode(',') }}"
                     data-compare-color="var(--danger)"
                     data-labels="{{ collect($forecast)->pluck('label')->implode(',') }}"></svg>
            </div>
            <div class="card-foot">
                <div class="grid grid-3">
                    <div>
                        <div class="tiny dim">Predicted total</div>
                        <div class="strong figure-num">{{ number_format($forecastTotal / 1000, 1) }} t</div>
                    </div>
                    <div>
                        <div class="tiny dim">Line capacity</div>
                        <div class="strong figure-num">{{ number_format($forecastCapacity / 1000, 1) }} t</div>
                    </div>
                    <div>
                        <div class="tiny dim">{{ $forecastShortfall > 0 ? 'Shortfall' : 'Headroom' }}</div>
                        <div class="strong figure-num {{ $forecastShortfall > 0 ? 't-warn' : '' }}">
                            {{ number_format(abs($forecastCapacity - $forecastTotal) / 1000, 1) }} t
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>When waste arrives</h3><span class="badge">by hour</span></div>
            <div class="card-body">
                <svg class="chart" data-chart="bars" data-height="200" data-gap="2" data-color="var(--c2)"
                     data-values="{{ implode(',', $hours) }}"
                     data-labels="{{ collect($hours)->map(fn ($v, $i) => $i % 4 === 0 ? $i.'h' : '')->implode(',') }}"></svg>
                <p class="tiny dim mt-10">
                    @if (count($peakHours) === 2)
                        Arrivals concentrate around {{ $peakHours[0] }}:00 and {{ $peakHours[1] }}:00.
                        Staffing the inspection bay to match those two windows is where queue time is won.
                    @else
                        Not enough arrivals on record yet to find a daily pattern.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Behaviour forecast: what areas and streams will do next --}}
    <section class="grid mb-16" style="grid-template-columns:1.5fr 1fr;gap:16px;align-items:start">
        <div class="card">
            <div class="card-head"><h3>Tomorrow by area</h3><span class="badge badge-violet">behaviour forecast</span></div>
            <div class="card-body">
                <div class="ai-card mb-12">
                    <span class="ai-tag"><i data-icon="sparkles" class="i i-sm"></i> Morning briefing</span>
                    <p class="small mt-8" style="color:var(--text);line-height:1.6">{{ $briefing }}</p>
                </div>
                @forelse ($tomorrowByArea as $row)
                    <div class="area-row">
                        <span>
                            <span class="small strong">{{ $row['area'] }}</span>
                            <span class="tiny dim"> · {{ $row['households'] }} households</span>
                            <div class="meter mt-6"><span style="width:{{ min(100, $row['share'] * 3) }}%"></span></div>
                        </span>
                        <span class="right">
                            <div class="strong figure-num">{{ number_format($row['kg'], 0) }} kg</div>
                            <div class="tiny dim">≈ {{ number_format($row['batches'], 1) }} pickups · {{ $row['confidence'] }}% conf.</div>
                            @if ($row['top_stream'])<span class="stream-chip mt-6">{{ $row['top_stream'] }}</span>@endif
                        </span>
                    </div>
                @empty
                    <p class="small muted">Not enough verified history yet.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Next 7 days by stream</h3><span class="badge">expected mix</span></div>
            <div class="card-body col g-10">
                @forelse ($weekByCategory as $s)
                    <div>
                        <div class="between mb-4">
                            <span class="row g-8 small"><span style="width:9px;height:9px;border-radius:2px;background:{{ $s['color'] }}"></span>{{ $s['label'] }}</span>
                            <span class="tiny dim figure-num">{{ number_format($s['kg'], 0) }} kg · {{ number_format($s['share'], 1) }}%</span>
                        </div>
                        <div class="bar"><span style="width:{{ $s['share'] }}%;background:{{ $s['color'] }}"></span></div>
                    </div>
                @empty
                    <p class="small muted">Nothing verified yet.</p>
                @endforelse
                <p class="tiny dim mt-8">Computed from verified history and the weekday forecast. The model phrases briefings; it never invents these figures.</p>
            </div>
        </div>
    </section>

    {{-- Standing insights --}}
    <section class="mb-16">
        <div class="between mb-12">
            <h2>What the system is seeing</h2>
            <span class="tiny dim">Recomputed every 10 minutes</span>
        </div>
        <div class="grid grid-2">
            @forelse ($insights as $i)
                <x-insight :insight="$i" />
            @empty
                <div class="empty">
                    <span class="empty-icon"><i data-icon="sparkles" class="i i-lg"></i></span>
                    <div class="strong">Not enough history</div>
                    <p class="small dim">Standing insights need a couple of weeks of verified batches.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Area performance --}}
    <section class="card mb-16">
        <div class="card-head"><h3>Area performance</h3><span class="badge">30 days</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>Area</th><th class="num">Collected</th><th class="num">Growth</th>
                    <th>Share of intake</th><th class="num">Households</th>
                    <th class="num">Avg / pickup</th><th class="num">Rejection</th>
                </tr></thead>
                <tbody>
                    @foreach ($areas as $a)
                        <tr>
                            <td class="strong small">{{ $a['name'] }}</td>
                            <td class="num strong figure-num">{{ number_format($a['kg'], 1) }} kg</td>
                            <td class="num">
                                @if (abs($a['growth']) < 0.05)
                                    <span class="tiny dim">—</span>
                                @else
                                    <span class="delta {{ $a['growth'] >= 0 ? 'up' : 'down' }}">
                                        <i data-icon="{{ $a['growth'] >= 0 ? 'arrowUp' : 'arrowDn' }}" class="i i-sm"></i>{{ number_format(abs($a['growth']), 1) }}%
                                    </span>
                                @endif
                            </td>
                            <td style="width:180px">
                                <div class="row g-8">
                                    <div class="bar grow"><span style="width:{{ number_format($a['share'], 1) }}%"></span></div>
                                    <span class="tiny dim figure-num" style="width:40px">{{ number_format($a['share'], 1) }}%</span>
                                </div>
                            </td>
                            <td class="num">{{ number_format($a['households']) }}</td>
                            <td class="num figure-num">{{ number_format($a['avg'], 1) }} kg</td>
                            <td class="num">
                                <span class="{{ $a['reject_pct'] > 4 ? 't-warn strong' : '' }}">{{ number_format($a['reject_pct'], 1) }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Intelligence modules --}}
    <section class="card mb-16">
        <div class="card-head">
            <h3>Intelligence modules</h3>
            <span class="badge {{ collect($modules)->where('live', true)->count() === count($modules) ? 'badge-green' : 'badge-amber' }}">
                {{ collect($modules)->where('live', true)->count() }} of {{ count($modules) }} live
            </span>
        </div>
        <div class="card-body">
            <div class="grid grid-4">
                @foreach ($modules as $m)
                    <div class="card card-pad" style="box-shadow:none">
                        <div class="row g-8 mb-8">
                            <span class="qa-ic" style="width:30px;height:30px;background:var(--violet-soft);color:var(--violet)">
                                <i data-icon="{{ $m['icon'] }}" class="i i-sm"></i>
                            </span>
                            <span class="badge {{ $m['live'] ? 'badge-green' : 'badge-amber' }}" style="margin-left:auto">
                                <span class="dot"></span>{{ $m['live'] ? 'live' : 'off' }}
                            </span>
                        </div>
                        <div class="small strong">{{ $m['name'] }}</div>
                        <div class="tiny dim">{{ $m['sub'] }}</div>
                        <div class="tiny mt-8" style="color:var(--violet);font-weight:640">{{ $m['metric'] }}</div>
                    </div>
                @endforeach
            </div>
            <p class="tiny dim mt-14">
                Every module reads the same records. Prediction and matching are arithmetic over the
                batch history; classification is the only one that calls a model, and the inspector's
                decision always overrides it. Nothing here reports a figure it did not measure.
            </p>
        </div>
    </section>

    <section class="grid mb-16" style="grid-template-columns:1fr 1fr;gap:16px">
        <div class="card">
            <div class="card-head"><h3>Intake, last 7 days</h3><span class="badge">kg received</span></div>
            <div class="card-body">
                <svg class="chart" data-chart="bars" data-height="190"
                     data-values="{{ $intake->implode(',') }}"
                     data-labels="{{ $labels->implode(',') }}"></svg>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Where the margin comes from</h3>
                <span class="badge badge-amber">strategy</span>
            </div>
            <div class="card-body">
                @forelse ($margin as $m)
                    @php
                        $kgPct = $m->kg / $marginKg * 100;
                        $valPct = $m->value / $marginNgn * 100;
                    @endphp
                    <div style="padding:7px 0">
                        <div class="between mb-4">
                            <span class="row g-8 small">
                                <span style="width:9px;height:9px;border-radius:2px;background:{{ $m->color }}"></span>
                                {{ $m->label }}
                            </span>
                            <span class="tiny dim">
                                {{ number_format($kgPct, 1) }}% of tonnage ·
                                <span class="strong" style="color:var(--text)">{{ number_format($valPct, 1) }}% of value</span>
                            </span>
                        </div>
                        <div class="bar"><span style="width:{{ $valPct }}%;background:{{ $m->color }}"></span></div>
                    </div>
                @empty
                    <p class="small muted">Nothing settled yet.</p>
                @endforelse

                <div class="callout callout-warn mt-14">
                    <i data-icon="alert" class="i i-sm"></i>
                    <div>
                        Tonnage and margin are not the same league table. Organics fill the digester and do
                        the environmental work; the high-rate streams are what actually pay for the round.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h3>What the model is allowed to know</h3><span class="badge">transparency</span></div>
        <div class="card-body">
            <p class="small muted">
                The model never touches the database. Aggregates are computed in SQL first and handed over as
                a fixed set of figures, with an instruction to use only those numbers and to say what is
                missing rather than estimate. That is why an answer here cannot invent a figure — and why it
                still works, in reduced form, with no API key at all.
            </p>
        </div>
    </section>
</div>
