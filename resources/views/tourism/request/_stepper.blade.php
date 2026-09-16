{{-- Where you are in the request: one compact row, like the rates list/map switch. --}}
<nav aria-label="{{ __('tourism.request.heading') }}" class="flex items-center gap-4">
    <ol class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
        @foreach ([1, 2, 3] as $n)
            @php
                $done = "(step > {$n} || stepDone({$n}))";
                $current = "step === {$n}";
                $reached = $initialStep >= $n;
            @endphp
            <li @class(['flex min-w-0 items-center gap-2 sm:gap-3', 'flex-1' => $n < 3]) @if ($n === $initialStep) aria-current="step" @endif>
                <button
                    type="button"
                    @click="({{ $done }}) && goToStep({{ $n }})"
                    :class="{{ $done }} ? 'cursor-pointer' : 'cursor-default'"
                    class="flex min-w-0 items-center gap-2.5 rounded-lg text-left focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition {{ $reached ? 'bg-primary text-white' : 'border border-placeholder bg-white text-muted' }}"
                        :class="{
                            'bg-primary text-white': {{ $done }} || {{ $current }},
                            'border border-placeholder bg-white text-muted': !({{ $done }}) && !({{ $current }}),
                        }"
                    >
                        <span x-show="{{ $done }}" @if ($initialStep <= $n) x-cloak @endif><x-travel-icon name="check" class="h-4 w-4" /></span>
                        <span x-show="!({{ $done }})" @if ($initialStep > $n) x-cloak @endif>{{ $n }}</span>
                    </span>
                    <span
                        class="hidden truncate text-sm font-semibold sm:block {{ $reached ? 'text-ink' : 'text-muted' }}"
                        :class="{ 'text-ink': {{ $current }} || {{ $done }}, 'text-muted': !({{ $current }}) && !({{ $done }}) }"
                    >{{ __('tourism.request.fstep_' . $n . '_title') }}</span>
                </button>

                @if ($n < 3)
                    <span class="h-px min-w-4 flex-1 transition-colors {{ $initialStep > $n ? 'bg-primary' : 'bg-border' }}" :class="{ 'bg-primary': step > {{ $n }}, 'bg-border': step <= {{ $n }} }"></span>
                @endif
            </li>
        @endforeach
    </ol>

    <p
        class="shrink-0 text-xs font-semibold tracking-wider text-muted uppercase"
        x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"
    >{{ __('tourism.request.wizard_step_of', ['current' => $initialStep, 'total' => 3]) }}</p>
</nav>
