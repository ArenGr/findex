@props(['steps'])
{{--
    Compact horizontal "how it works" strip for the request-page heroes
    (insurance, travel). One white card, three columns joined by chevrons,
    step 1 active with a green underline. Each step: ['title','body','icon'],
    where icon is a raw inline SVG string.
--}}
<div class="overflow-hidden rounded-2xl border border-placeholder bg-white shadow-[0_4px_18px_rgba(24,29,18,0.05)]">
    <div class="flex flex-col divide-y divide-placeholder md:flex-row md:divide-y-0">
        @foreach ($steps as $i => $step)
            <div class="relative flex flex-1 items-center gap-3 px-5 py-3.5">
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-primary text-white' => $i === 0,
                    'bg-placeholder/50 text-muted' => $i !== 0,
                ])>{{ $i + 1 }}</span>

                <span @class(['shrink-0', 'text-primary' => $i === 0, 'text-subtle' => $i !== 0])>
                    {!! $step['icon'] !!}
                </span>

                <div class="min-w-0">
                    <h3 @class(['text-[14px] font-semibold leading-5', 'text-primary' => $i === 0, 'text-ink' => $i !== 0])>{{ $step['title'] }}</h3>
                    <p class="mt-0.5 truncate text-[12px] leading-4 text-muted">{{ $step['body'] }}</p>
                </div>

                @unless ($loop->last)
                    <span class="ml-auto hidden shrink-0 text-placeholder md:block" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </span>
                @endunless

                @if ($i === 0)
                    <span class="absolute inset-x-5 bottom-0 hidden h-[2px] bg-primary md:block"></span>
                @endif
            </div>
        @endforeach
    </div>
</div>
