@props(['partners', 'heading', 'marquee' => false])

{{-- Logo tiles (plus short names when static). Shared by rates, insurance and travel.
     `marquee` scrolls it on its own; hover pauses it, reduced motion stops it. --}}
@php
    $linked = \App\Support\Features::enabled('organizations');
    $name = $marquee ? 'text-base' : 'text-sm';

    // Logos run from square marks (Sil 1:1) to long wordmarks (Byblos 10:1).
    // Fitting them all into one square made wordmarks tiny, so each is sized to
    // the same visual area instead, capped so no one mark dominates.
    [$area, $maxW, $maxH] = $marquee ? [3400, 170, 60] : [1500, 110, 40];
    $logoSize = function (float $ratio) use ($area, $maxW, $maxH): array {
        $w = sqrt($area * $ratio);
        $h = $w / $ratio;
        $scale = min(1, $maxW / $w, $maxH / $h);

        return [(int) round($w * $scale), (int) round($h * $scale)];
    };

    // A loop needs each half wider than the screen, or a short list (one travel
    // agency, six insurers) leaves a gap on a wide monitor. Repeat until a half holds ~24.
    $count = $partners->count();
    $row = $marquee && $count > 0
        ? collect(array_merge(...array_fill(0, (int) ceil(24 / $count), $partners->all())))
        : $partners;
@endphp

@if ($partners->isNotEmpty())
    <section class="site-container py-12">
        <p class="flex items-center justify-center gap-2 text-xs font-bold tracking-wider text-muted uppercase">
            <x-lucide name="badge-check" :size="16" class="text-primary" />
            {{ $heading }}
        </p>

        <div @class(['mt-8', 'partners-marquee overflow-hidden' => $marquee])>
            <div @class(['flex items-center', 'partners-marquee-track w-max' => $marquee, 'flex-wrap justify-center gap-x-8 gap-y-5' => ! $marquee])
                @if ($marquee) style="animation-duration: {{ $row->count() * 6 }}s" @endif>
                {{-- The marquee draws the list twice so the loop has no seam; the copy is hidden from assistive tech. --}}
                @foreach ($marquee ? [false, true] : [false] as $copy)
                    <ul @class(['flex items-center', 'gap-x-12 pr-12' => $marquee, 'flex-wrap justify-center gap-x-8 gap-y-5' => ! $marquee]) @if ($copy) aria-hidden="true" @endif>
                        @foreach ($row as $i => $partner)
                            {{-- Only the first pass of the list is read out and tabbable. --}}
                            @php $repeat = $copy || $i >= $count; @endphp
                            <li @if ($repeat && ! $copy) aria-hidden="true" @endif>
                                <a
                                    @if ($linked && ! empty($partner->slug)) href="{{ route('organizations.show', $partner->slug) }}" @endif
                                    @if ($repeat) tabindex="-1" @endif
                                    title="{{ $partner->name }}"
                                    {{-- The carousel shows logos only; the name is still read out. --}}
                                    @if ($marquee) aria-label="{{ $partner->name }}" @endif
                                    class="group flex items-center gap-3 rounded-2xl transition"
                                >
                                    @if ($partner->logo)
                                        @php [$w, $h] = $logoSize((float) ($partner->ratio ?? 1)); @endphp
                                        <span @class([
                                            'flex shrink-0 items-center justify-center transition',
                                            'h-20 opacity-90 group-hover:opacity-100' => $marquee,
                                            'h-14 rounded-2xl bg-surface-alt px-3 group-hover:bg-surface group-hover:ring-1 group-hover:ring-primary/30' => ! $marquee,
                                        ])>
                                            <img src="{{ $partner->logo }}" alt="" width="{{ $w }}" height="{{ $h }}" loading="lazy" decoding="async"
                                                style="width: {{ $w }}px; height: {{ $h }}px" class="object-contain">
                                        </span>
                                    @else
                                        {{-- No logo: the short name as a wordmark, never a lone initial. --}}
                                        @if ($marquee)
                                            <span class="flex h-20 items-center text-xl font-bold whitespace-nowrap text-muted transition group-hover:text-primary" aria-hidden="true">{{ $partner->short }}</span>
                                        @else
                                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-surface-alt text-sm font-bold text-primary" aria-hidden="true">{{ $partner->initial }}</span>
                                        @endif
                                    @endif
                                    @unless ($marquee)
                                        <span class="{{ $name }} font-semibold whitespace-nowrap text-ink transition group-hover:text-primary">{{ $partner->short }}</span>
                                    @endunless
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </section>
@endif
