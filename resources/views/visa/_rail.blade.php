{{-- Beside the form: what to have to hand, ticked off as it arrives, and why this is safe to send. --}}
@php
    $needed = array_values(array_filter([
        ['label' => __('visa.request.need_destination'), 'done' => 'destination'],
        ['label' => __('visa.request.need_dates'), 'done' => 'from && to'],
        auth()->guest() ? ['label' => __('visa.request.need_contact'), 'done' => 'name.trim() && email.trim()'] : null,
    ]));

    $reassurances = [
        ['icon' => 'badge-check', 'text' => __('visa.request.signal_agencies')],
        ['icon' => 'hand-coins', 'text' => __('visa.request.signal_free')],
        ['icon' => 'clock', 'text' => __('visa.request.signal_fast')],
        ['icon' => 'lock', 'text' => __('visa.request.secure_note')],
    ];
@endphp

<aside class="border-t border-border bg-surface-alt p-5 sm:p-8 lg:border-t-0 lg:border-l lg:p-8">
    <h2 class="text-sm font-bold tracking-[0.14em] text-muted uppercase">{{ __('visa.request.need_heading') }}</h2>

    <ul class="mt-5 space-y-4">
        @foreach ($needed as $item)
            <li class="flex items-center gap-3">
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition"
                    :class="{ 'border-primary bg-primary text-white': {{ $item['done'] }}, 'border-placeholder text-transparent': !({{ $item['done'] }}) }"
                >
                    <x-lucide name="check" :size="14" :stroke="3" />
                </span>
                <span class="text-sm" :class="{ 'text-ink': {{ $item['done'] }}, 'text-muted': !({{ $item['done'] }}) }">{{ $item['label'] }}</span>
            </li>
        @endforeach
    </ul>

    <ul class="mt-8 space-y-4 border-t border-border pt-6">
        @foreach ($reassurances as $item)
            <li class="flex items-start gap-3 text-sm leading-relaxed text-muted">
                <x-lucide :name="$item['icon']" :size="18" class="mt-0.5 shrink-0 text-primary" />
                {{ $item['text'] }}
            </li>
        @endforeach
    </ul>
</aside>
