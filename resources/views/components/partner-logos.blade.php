<section class="overflow-hidden border-y border-placeholder bg-white py-10">
    <div class="mx-auto max-w-7xl px-6 lg:px-10">
        <div class="group overflow-hidden">
            <div class="flex w-max animate-[partner-scroll_35s_linear_infinite] items-center group-hover:[animation-play-state:paused]">
                @for ($i = 0; $i < 2; $i++)
                    <img
                        src="{{ asset('images/partners-tight.png') }}"
                        alt="{{ $i === 0 ? __('partners.logos_alt') : '' }}"
                        @if ($i > 0) aria-hidden="true" @endif
                        width="1521"
                        height="133"
                        loading="lazy"
                        class="h-24 w-auto max-w-none shrink-0 object-contain px-3"
                    >
                @endfor
            </div>
        </div>
    </div>
</section>
