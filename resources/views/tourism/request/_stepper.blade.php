{{-- Where you are in the request: numbered steps, each over its own progress rule - see insurance/auto/_wizard-stepper. --}}
@php
    $steps = [
        1 => __('tourism.request.fstep_1_title'),
        2 => __('tourism.request.fstep_2_title'),
        3 => __('tourism.request.fstep_3_title'),
    ];
@endphp

<nav aria-label="{{ __('tourism.request.heading') }}">
    <ol class="grid grid-cols-3 gap-2 sm:gap-4">
        @foreach ($steps as $n => $label)
            @php
                // A step is done once it holds what it asked for; only then is it clickable.
                $done = "(step > {$n} || stepDone({$n}))";
                $current = "step === {$n}";
                $reached = $initialStep >= $n;
            @endphp
            <li class="min-w-0" @if ($n === $initialStep) aria-current="step" @endif>
                <button
                    type="button"
                    @click="({{ $done }}) && goToStep({{ $n }})"
                    :class="{{ $done }} ? 'cursor-pointer' : 'cursor-default'"
                    class="flex w-full min-w-0 items-center justify-center gap-2 rounded-lg py-1 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition {{ $reached ? 'bg-primary text-white' : 'border border-placeholder bg-white text-subtle' }}"
                        :class="{ 'bg-primary text-white': {{ $done }} || {{ $current }}, 'border border-placeholder bg-white text-subtle': !({{ $done }}) && !({{ $current }}) }"
                    >
                        <span x-show="{{ $done }}" @if ($initialStep <= $n) x-cloak @endif><x-lucide name="check" :size="16" :stroke="2.5" /></span>
                        <span x-show="!({{ $done }})" @if ($initialStep > $n) x-cloak @endif>{{ sprintf('%02d', $n) }}</span>
                    </span>
                    <span
                        class="hidden truncate text-[15px] font-semibold sm:block {{ $reached ? 'text-ink' : 'text-subtle' }}"
                        :class="{ 'text-ink': {{ $done }} || {{ $current }}, 'text-subtle': !({{ $done }}) && !({{ $current }}) }"
                    >{{ $label }}</span>
                </button>

                <span
                    class="mt-2.5 block h-1 rounded-full transition-colors {{ $reached ? 'bg-primary' : 'bg-border' }}"
                    :class="{ 'bg-primary': {{ $done }} || {{ $current }}, 'bg-border': !({{ $done }}) && !({{ $current }}) }"
                ></span>
            </li>
        @endforeach
    </ol>
</nav>
