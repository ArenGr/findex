{{-- Where you are in the request. --}}
<nav aria-label="{{ __('tourism.request.heading') }}">
    <p
        class="mb-4 text-xs font-semibold text-gray-500 sm:hidden"
        x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"
    >{{ __('tourism.request.wizard_step_of', ['current' => $initialStep, 'total' => 3]) }}</p>

    <ol class="relative mx-auto flex max-w-4xl flex-col gap-4 sm:flex-row sm:items-center sm:gap-0">
        @foreach ([1, 2, 3] as $n)
            @php
                $done = "(step > {$n} || stepDone({$n}))";
                $current = "step === {$n}";
                $reached = $initialStep >= $n;
            @endphp
            <li class="relative z-10 flex items-center gap-3" @if ($n === $initialStep) aria-current="step" @endif>
                <button
                    type="button"
                    @click="({{ $done }}) && goToStep({{ $n }})"
                    :class="{{ $done }} ? 'cursor-pointer' : ({{ $current }} ? '' : 'cursor-default')"
                    class="flex items-center gap-3 rounded-lg text-left focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition {{ $reached ? 'bg-travel-600 text-white ring-4 ring-travel-100' : 'border-2 border-gray-300 bg-white text-gray-400' }}"
                        :class="{
                            'bg-travel-600 text-white ring-4 ring-travel-100': {{ $done }} || {{ $current }},
                            'border-2 border-gray-300 bg-white text-gray-400': !({{ $done }}) && !({{ $current }}),
                        }"
                    >
                        <span x-show="{{ $done }}" @if ($initialStep <= $n) x-cloak @endif><x-travel-icon name="check" class="h-4 w-4" /></span>
                        <span x-show="!({{ $done }})" @if ($initialStep > $n) x-cloak @endif>{{ $n }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-bold {{ $reached ? 'text-travel-800' : 'text-gray-700' }}" :class="{ 'text-travel-800': {{ $current }} || {{ $done }}, 'text-gray-700': !({{ $current }}) && !({{ $done }}) }">{{ __('tourism.request.fstep_' . $n . '_title') }}</span>
                        <span class="mt-0.5 block text-[11px] {{ $reached ? 'font-medium text-travel-600' : 'text-gray-400' }}" :class="{ 'font-medium text-travel-600': {{ $current }} || {{ $done }}, 'text-gray-400': !({{ $current }}) && !({{ $done }}) }">{{ __('tourism.request.fstep_' . $n . '_body') }}</span>
                    </span>
                </button>
            </li>

            @if ($n < 3)
                <span class="mx-4 hidden h-[2px] flex-1 transition-colors sm:block {{ $initialStep > $n ? 'bg-travel-600' : 'bg-gray-200' }}" :class="{ 'bg-travel-600': step > {{ $n }}, 'bg-gray-200': step <= {{ $n }} }"></span>
            @endif
        @endforeach
    </ol>
</nav>
