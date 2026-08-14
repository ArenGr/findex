@props(['label', 'options', 'active' => false, 'hint' => null])

@php
    // The one currently chosen, so the closed control reads as an answer
    // rather than as a prompt: "Market / Banks", not "Market".
    $current = collect($options)->firstWhere('selected', true) ?? collect($options)->first();
@endphp

{{--
    A filter that says what it is set to without being opened.

    Built on <details> rather than on a div and a click handler: the browser
    opens and closes it natively, so it still works with JavaScript off - which
    every other control on this page does too, and a filter behind a script
    would have been the one exception. Alpine only adds what the element cannot
    do alone: closing on an outside click or Escape.
--}}
<details
    x-data="{
        shift: 0,
        close() { $el.removeAttribute('open'); },
        {{--
            A panel is capped at 20rem, but a cap is not a position: one that
            opens 500px into a 768px screen still runs off the side of it.
            Measured once shown and pulled back inside, which is the only way
            to know - where a filter sits depends on how many there are and on
            how long their names run in this locale.
        --}}
        place() {
            this.shift = 0;
            this.$nextTick(() => {
                const box = this.$refs.menu?.getBoundingClientRect();
                if (!box) {
                    return;
                }
                const margin = 12;

                if (box.right > window.innerWidth - margin) {
                    this.shift = window.innerWidth - margin - box.right;
                } else if (box.left < margin) {
                    this.shift = margin - box.left;
                }
            });
        },
    }"
    {{-- <details> fires this natively however it was opened. --}}
    @toggle="$el.open ? place() : shift = 0"
    @click.outside="close()"
    @keydown.escape.window="close()"
    @resize.window="if ($el.open) place()"
    class="group relative min-w-0"
>
    <summary
        class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl border px-3.5 py-2 text-start transition select-none marker:content-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none [&::-webkit-details-marker]:hidden {{ $active ? 'border-primary/50 bg-primary/10' : 'border-placeholder bg-white hover:border-border-muted' }}"
        @if ($hint) title="{{ $hint }}" @endif
    >
        <span class="min-w-0">
            <span class="block text-[11px] font-semibold tracking-wider text-subtle uppercase">{{ $label }}</span>
            <span class="mt-0.5 block truncate text-sm font-semibold {{ $active ? 'text-ink' : 'text-muted' }}">
                {{ $current['label'] ?? '' }}
            </span>
        </span>

        {{-- Rotates when the panel is open, so the control shows its own state
        rather than relying on the panel below it being noticed. --}}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-auto h-4 w-4 shrink-0 text-muted transition-transform group-open:-rotate-180" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </summary>

    {{--
        Two behaviours, one panel. On a phone the filters live in a bottom
        sheet that scrolls, so the options expand inline and push it down -
        floating them would overlay the controls underneath and clip against
        the sheet's own scroll box. From sm up there is room to float, and
        floating keeps the row of filters from jumping about as one opens.

        Wider than the trigger when the names need it - organization names run
        long - but never wider than the screen.
    --}}
    <div
        x-ref="menu"
        :style="shift ? `transform: translateX(${shift}px)` : ''"
        class="mt-2 w-full overflow-y-auto rounded-xl border border-placeholder bg-white p-1.5 sm:absolute sm:start-0 sm:top-full sm:z-30 sm:max-h-72 sm:w-max sm:min-w-full sm:max-w-[min(20rem,calc(100vw-3rem))] sm:shadow-lg sm:ring-1 sm:ring-ink/5"
    >
        @foreach ($options as $option)
            <a
                href="{{ $option['href'] }}"
                @if ($option['selected']) aria-current="true" @endif
                class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm break-words transition {{ $option['selected'] ? 'bg-primary/10 font-semibold text-ink' : 'text-muted hover:bg-placeholder/40 hover:text-ink' }}"
            >
                <span class="min-w-0 flex-1">{{ $option['label'] }}</span>

                {{-- A tick on the chosen row. The tint alone reads as a hover
                state on a list you are already hovering over. --}}
                @if ($option['selected'])
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-primary" aria-hidden="true">
                        <path d="m5 13 4 4L19 7" />
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</details>
