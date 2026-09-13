@props(['heading', 'subheading', 'steps', 'badge' => null])

<section class="mx-auto max-w-6xl px-6 pt-12 lg:px-10 lg:pt-16">
    <div class="mb-8 lg:mb-10">
        @if ($badge)
            <span class="mb-4 inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                {{ $badge }}
            </span>
        @endif

        <h1 class="font-heading text-3xl font-bold text-ink lg:text-4xl">{{ $heading }}</h1>
        <p class="mt-3 max-w-2xl text-base text-muted lg:text-lg">{{ $subheading }}</p>

        {{ $slot }}
    </div>

    <ol class="mb-2 flex flex-col gap-4 md:flex-row md:items-start md:gap-2">
        @foreach ($steps as $i => $step)
            <li class="flex items-start gap-3 md:flex-1 md:flex-col md:items-center md:text-center">
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-heading text-sm font-semibold',
                    'bg-primary text-white' => $i === 0,
                    'bg-placeholder text-muted' => $i !== 0,
                ])>{{ $i + 1 }}</span>
                <div class="md:mt-2">
                    <p @class(['text-sm font-semibold', 'text-primary' => $i === 0, 'text-ink' => $i !== 0])>{{ $step['title'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-muted">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>
