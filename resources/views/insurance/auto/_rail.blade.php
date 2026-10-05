{{-- Beside the form: what to have to hand, ticked off as it arrives, and why this is safe to send. --}}
@php
    $needed = [
        ['label' => __('auto_insurance.request.vehicle_plate'), 'done' => "plate.trim()"],
        ['label' => __('auto_insurance.request.owner_id_number'), 'done' => "idNumber.trim()"],
        ['label' => __('auto_insurance.request.wizard.need_contact'), 'done' => "email.trim() && phone.trim()"],
        ['label' => __('auto_insurance.request.market_bank_account'), 'done' => "bankAccount.trim()"],
    ];

    $reassurances = [
        ['icon' => 'building-2', 'text' => trans_choice('compare_ui.insurers', $insurerCount, ['count' => $insurerCount])],
        ['icon' => 'hand-coins', 'text' => __('auto_insurance.request.signal_free')],
        ['icon' => 'clock', 'text' => __('auto_insurance.request.wizard.footnote_fast')],
        ['icon' => 'lock', 'text' => __('auto_insurance.request.wizard.reassure_private')],
    ];
@endphp

<aside class="border-t border-border bg-surface-alt p-5 sm:p-8 lg:border-t-0 lg:border-l lg:p-8">
    <h2 class="text-sm font-bold tracking-[0.14em] text-muted uppercase">{{ __('auto_insurance.request.wizard.need_heading') }}</h2>

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
            <li class="flex items-start gap-3 text-sm text-muted">
                <x-lucide :name="$item['icon']" :size="18" class="mt-0.5 text-primary" />
                {{ $item['text'] }}
            </li>
        @endforeach
    </ul>
</aside>
