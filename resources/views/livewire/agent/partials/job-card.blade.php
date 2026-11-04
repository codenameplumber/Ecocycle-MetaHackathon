{{-- One marketplace job. The material photograph comes first: an agent wants to
     know what they are lifting before any of the numbers. --}}
@php
    $fits = ($j->expected_kg ?? 0) <= $remaining;
    $share = round(($j->expected_kg ?? 0) * (float) ($j->category?->rate_per_kg ?? 0) * (float) config('ecocycle.agent_share'), 2);
@endphp
<article class="card card-flat" style="{{ $fits ? '' : 'opacity:.55' }}">
    <div class="card-body" style="padding:14px">
        <div class="row-top g-12">
            <span class="thumb thumb-lg">
                <img src="{{ $j->photo_path ? asset('storage/' . $j->photo_path) : asset('assets/img/cat-' . $j->category?->key . '.jpg') }}" alt="" loading="lazy">
            </span>
            <div class="grow" style="min-width:0">
                <div class="between" style="align-items:flex-start">
                    <div style="min-width:0">
                        <div class="item-title truncate">{{ $j->household->name() }}</div>
                        <div class="item-sub">
                            {{ $j->household->area?->name }} · {{ number_format($j->distance_km, 1) }} km away
                        </div>
                    </div>
                    @if (! $fits)
                        <span class="badge badge-red">Over capacity</span>
                    @elseif ($j->window_end && $j->window_end->isToday())
                        <span class="badge badge-amber">Today</span>
                    @else
                        <span class="badge">Flexible</span>
                    @endif
                </div>
                <div class="row g-14 mt-10 wrap">
                    <span class="row g-6 small">
                        <i data-icon="scale" class="i i-sm"></i>
                        <span class="strong figure-num">{{ number_format($j->expected_kg ?? 0, 1) }} kg</span>
                        <span class="dim">est.</span>
                    </span>
                    <span class="small dim">{{ $j->category?->label }}</span>
                    @if ($j->photo_path)
                        <span class="badge badge-green">Photo evidence</span>
                    @endif
                    @if ($j->classification)
                        <span class="tiny dim">{{ $j->classification['confidence'] ?? 0 }}% read</span>
                    @endif
                    @if ($j->window_end)
                        <span class="row g-6 small dim">
                            <i data-icon="clock" class="i i-sm"></i>{{ $j->window_end->format('D g A') }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="divider mt-12 mb-12"></div>

        <div class="between">
            <div>
                <div class="tiny dim">Your share (40%)</div>
                <div class="strong figure-num" style="font-size:17px">₦{{ number_format($share, 2) }}</div>
            </div>
            <div class="row g-10">
                <div class="right">
                    <div class="tiny dim">Match</div>
                    <div class="small strong" style="color:var(--violet)">{{ $j->match_score }}%</div>
                </div>
                {{-- On the jobs board accepting opens a confirmation; on the
                     Today screen there is no sheet, so it claims directly. --}}
                <button class="btn btn-primary btn-sm" @disabled(! $fits)
                        wire:click="{{ ($confirmable ?? false) ? 'ask' : 'accept' }}({{ $j->id }})"
                        wire:loading.attr="disabled" wire:target="accept({{ $j->id }})">
                    <span wire:loading.remove wire:target="accept({{ $j->id }})">Accept</span>
                    <span wire:loading wire:target="accept({{ $j->id }})">…</span>
                </button>
            </div>
        </div>
    </div>
</article>
