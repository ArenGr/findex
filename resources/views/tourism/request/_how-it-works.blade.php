<section class="bg-white pt-10 pb-12 lg:pt-11 lg:pb-14">
    <div class="travel-container">
        <h2 class="font-heading text-[1.9rem] leading-tight font-bold tracking-[-0.02em] text-travel-ink sm:text-[2.4rem]">
            {{ __('tourism.request.works_heading') }}
        </h2>
        <p class="mt-2 text-[17px] leading-7 text-travel-muted">{{ __('tourism.request.works_sub') }}</p>
        <ol class="mt-10 grid grid-cols-1 gap-x-4 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([1, 2, 3, 4] as $n)
                <li class="relative flex flex-col px-1">
                    <span class="relative mb-5 inline-flex">
                        <img
                            src="{{ asset('images/travel/svg/step-' . $n . '.svg') }}"
                            alt=""
                            aria-hidden="true"
                            width="200"
                            height="200"
                            class="h-[72px] w-[72px]"
                        >
                        <span class="absolute -bottom-1 -left-1 flex h-6 w-6 items-center justify-center rounded-full bg-travel-green text-[12px] font-semibold text-white">{{ $n }}</span>
                    </span>
                    <h3 class="text-[15px] leading-5 font-bold text-travel-ink">{{ __('tourism.request.step_' . $n . '_title') }}</h3>
                    <p class="mt-2 max-w-[16rem] text-[13px] leading-5 text-travel-muted">{{ __('tourism.request.step_' . $n . '_body') }}</p>
                    @if ($n < 4)
                        <svg class="absolute top-8 -right-3 hidden h-4 w-4 text-travel-muted/50 lg:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14" /><path d="m13 6 6 6-6 6" />
                        </svg>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
