{{--
    The agencies a visitor's request actually goes out to.

    Driven by real partner organizations rather than the design pack's mockup
    logos, which are invented brands. Rendered only when there are enough to
    read as a row - a "trusted by" strip listing one agency argues against
    itself - so this section can be absent on a fresh database and appear on
    its own once partners exist.
--}}
@props(['partners'])

@if ($partners->count() >= \App\Support\TravelPartners::MIN_TO_SHOW)
    <section class="bg-white pb-14 lg:pb-16">
        <div class="travel-container">
            <div class="rounded-[20px] border border-travel-border/70 bg-travel-cream px-6 py-5 lg:px-8">
                <p class="flex items-center gap-2.5 text-[13px] font-semibold text-travel-ink">
                    <svg class="h-4 w-4 shrink-0 text-travel-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 14c1.5-1.5 3-3.4 3-5.5A4.5 4.5 0 0 0 12 5.5 4.5 4.5 0 0 0 2 8.5C2 13 12 21 12 21s3.5-2.8 7-7z" />
                    </svg>
                    {{ __('tourism.request.partners_heading') }}
                </p>

                <ul class="mt-4 flex flex-wrap items-center gap-x-9 gap-y-4">
                    @foreach ($partners as $partner)
                        <li class="flex items-center gap-2.5">
                            @if ($partner->logo)
                                {{-- Sized rather than intrinsic: partner logos
                                     arrive at whatever dimensions they were
                                     uploaded at, and an unsized one would
                                     reflow the row as it decodes. --}}
                                <img
                                    src="{{ $partner->logo }}"
                                    alt="{{ $partner->name }}"
                                    width="28"
                                    height="28"
                                    loading="lazy"
                                    decoding="async"
                                    referrerpolicy="no-referrer"
                                    class="h-7 w-7 shrink-0 rounded object-contain"
                                >
                            @else
                                {{-- No logo on file: an initial disc, never a
                                     stand-in mark that could read as theirs. --}}
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-travel-sage text-[12px] font-bold text-travel-green" aria-hidden="true">{{ $partner->initial }}</span>
                            @endif
                            <span class="text-[14px] font-semibold text-travel-ink">{{ $partner->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endif
