@props(['label', 'options', 'active' => false, 'hint' => null, 'searchable' => false])

@php
    $current = collect($options)->firstWhere('selected', true) ?? collect($options)->first();
@endphp

{{-- A filter that says what it is set to without being opened. --}}
<details
    x-data="{
        shift: 0,
        term: '',
        matches(label) {
            return this.term === '' || label.toLowerCase().includes(this.term.toLowerCase());
        },
        close() { $el.removeAttribute('open'); this.term = ''; },
        place() {
            this.shift = 0;
            this.$nextTick(() => this.$refs.search?.focus());
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
    @toggle="$el.open ? place() : (shift = 0, term = '')"
    @click.outside="close()"
    @keydown.escape.window="close()"
    @resize.window="if ($el.open) place()"
    class="group relative min-w-0"
>
    <summary
        class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl border px-3.5 py-2 text-start transition select-none marker:content-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none [&::-webkit-details-marker]:hidden {{ $active ? 'border-primary bg-primary' : 'border-placeholder bg-white hover:border-primary hover:bg-primary/10' }}"
        @if ($hint) title="{{ $hint }}" @endif
    >
        <span class="min-w-0">
            <span class="block text-[11px] font-semibold tracking-wider uppercase {{ $active ? 'text-white/75' : 'text-subtle' }}">{{ $label }}</span>
            <span class="mt-0.5 block truncate text-sm font-semibold {{ $active ? 'text-white' : 'text-muted' }}">
                {{ $current['label'] ?? '' }}
            </span>
        </span>

        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-auto h-4 w-4 shrink-0 transition-transform group-open:-rotate-180 {{ $active ? 'text-white/80' : 'text-muted' }}" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </summary>

    {{-- Two behaviours, one panel. --}}
    <div
        x-ref="menu"
        :style="shift ? `transform: translateX(${shift}px)` : ''"
        class="mt-2 w-full overflow-y-auto rounded-xl border border-placeholder bg-white p-1.5 sm:absolute sm:start-0 sm:top-full sm:z-30 sm:max-h-72 sm:w-max sm:min-w-full sm:max-w-[min(20rem,calc(100vw-3rem))] sm:shadow-lg sm:ring-1 sm:ring-ink/5"
    >
        @if ($searchable)
            {{-- Focused on open, so a menu that is worth searching can be searched without a second press. --}}
            <div class="relative mb-1.5">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute top-1/2 start-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                </svg>
                <input
                    type="text"
                    x-model="term"
                    x-ref="search"
                    placeholder="{{ __('rates.search_placeholder') }}"
                    aria-label="{{ $label }}"
                    autocomplete="off"
                    @keydown.enter.prevent="Array.from($refs.menu.querySelectorAll('a')).find((link) => link.offsetParent !== null)?.click()"
                    class="w-full rounded-lg border border-placeholder bg-white py-2 ps-8 pe-2 text-sm text-ink focus:border-primary focus:outline-none"
                >
            </div>
        @endif

        @foreach ($options as $option)
            {{-- data-filter-option is a test hook. --}}
            <a
                href="{{ $option['href'] }}"
                @if ($searchable) x-show="matches(@js($option['label']))" @endif
                data-filter-option
                @if ($option['selected']) aria-current="true" @endif
                class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm break-words transition {{ $option['selected'] ? 'bg-primary font-semibold text-white' : 'text-muted hover:bg-primary/10 hover:text-primary' }}"
            >
                <span class="min-w-0 flex-1">{{ $option['label'] }}</span>

                {{-- A tick on the chosen row. --}}
                @if ($option['selected'])
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-white" aria-hidden="true">
                        <path d="m5 13 4 4L19 7" />
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</details>
