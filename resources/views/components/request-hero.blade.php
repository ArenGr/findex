@props(['heading', 'subheading', 'steps', 'badge' => null])

@php
    // Assigned here rather than per page so the three request forms cannot
    // drift into three different colour orders.
    $stepColors = ['slide-green', 'slide-blue', 'accent-yellow'];
@endphp

{{--
    Shared by every "tell us once, we ask our partners" request page - exchange
    quotes, auto insurance, tourism. They make the same offer in the same three
    steps, so they get the same hero.

    Heading beside the steps rather than above them: what this is and how it
    works are one thought, and stacking them centred pushed the form - the
    thing each page exists for - a full screen down.
--}}
<section class="border-b border-placeholder bg-primary/5">
    <div class="mx-auto grid max-w-6xl gap-x-12 gap-y-10 px-6 py-14 lg:grid-cols-[minmax(0,20rem)_1fr] lg:px-10">
        <div class="min-w-0">
            @if ($badge)
                <span class="inline-flex rounded-full bg-slide-green/20 px-4 py-2 text-sm font-medium break-words text-ink">
                    {{ $badge }}
                </span>
            @endif

            <h1 @class(['font-heading text-3xl leading-tight font-bold break-words text-ink', 'mt-6' => $badge])>
                {{ $heading }}
            </h1>
            <p class="mt-4 text-base leading-relaxed break-words text-muted">{{ $subheading }}</p>

            {{-- Anything a single page needs under its subheading, such as
            tourism's link out to the agency directory. --}}
            {{ $slot }}
        </div>

        <ol class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($steps as $i => $step)
                <li class="min-w-0 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-placeholder/60">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/20 font-heading text-xs font-bold" style="color: var(--color-{{ $stepColors[$i % 3] }})">
                        {{ $i + 1 }}
                    </span>
                    <p class="mt-3 text-sm font-semibold break-words text-ink">{{ $step['title'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed break-words text-muted">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
