{{--
    Where you are in the request, shown on every step.

    In its own file because it appears twice: inside the hero on step 1, and in
    the compact header on the steps after it. It lived in the hero alone for a
    moment, and since that hero is x-show="step === 1" the progress indicator
    vanished exactly when it starts being useful.

    Alpine-bound rather than the shared x-hero-steps: this one is clickable and
    its state changes without a page load. Same classes, so the two request
    pages read as the same indicator.
--}}
<nav aria-label="{{ __('tourism.request.heading') }}">
    <ol class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-0">
        @foreach ([1, 2, 3] as $n)
            @php
                $done = "(step > {$n} || stepDone({$n}))";
                $current = "step === {$n}";
            @endphp
            <li class="flex items-center gap-3 {{ $n < 3 ? 'sm:flex-1' : '' }}">
                <button
                    type="button"
                    @click="({{ $done }}) && goToStep({{ $n }})"
                    :class="{{ $done }} ? 'cursor-pointer' : ({{ $current }} ? '' : 'cursor-default')"
                    class="flex items-center gap-3 rounded-lg text-left focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none"
                >
                    {{-- Filled green for the step you are on and every
                         step you have finished; a light outline for the
                         ones still ahead. One glance, one answer. --}}
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold transition {{ $initialStep >= $n ? 'bg-primary text-white' : 'border border-placeholder bg-white text-muted' }}"
                        :class="{
                            'bg-primary text-white': {{ $done }} || {{ $current }},
                            'border border-placeholder bg-white text-muted': !({{ $done }}) && !({{ $current }}),
                        }"
                    >
                        {{-- x-show, not x-if: a template renders nothing
                             until Alpine runs, which left every circle
                             blank on first paint. --}}
                        <span x-show="{{ $done }}" @if ($initialStep <= $n) x-cloak @endif><x-travel-icon name="check" class="h-[18px] w-[18px]" /></span>
                        <span x-show="!({{ $done }})" @if ($initialStep > $n) x-cloak @endif>{{ $n }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[13px] leading-4 font-semibold {{ $initialStep >= $n ? 'text-ink' : 'text-muted' }}" :class="{ 'text-ink': {{ $current }} || {{ $done }}, 'text-muted': !({{ $current }}) && !({{ $done }}) }">{{ __('tourism.request.fstep_' . $n . '_title') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-4 text-muted">{{ __('tourism.request.fstep_' . $n . '_body') }}</span>
                    </span>
                </button>
                @if ($n < 3)
                    <span class="mx-3 hidden h-px flex-1 transition-colors sm:block {{ $initialStep > $n ? 'bg-primary' : 'bg-placeholder' }}" :class="{ 'bg-primary': step > {{ $n }}, 'bg-placeholder': step <= {{ $n }} }"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
