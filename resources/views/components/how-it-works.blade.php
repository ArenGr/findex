@props(['heading', 'sub' => null, 'steps'])

{{-- Four numbered steps in a row, divided by hairlines. Shared by rates, insurance and travel.
     `steps` is a list of ['icon' => lucide name, 'title' => ..., 'body' => ...]. --}}
<section class="site-container py-16">
    <div class="max-w-2xl">
        <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $heading }}</h2>
        @if ($sub)
            <p class="mt-2 text-base text-muted">{{ $sub }}</p>
        @endif
    </div>

    <ol class="mt-10 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0 lg:divide-x lg:divide-border">
        @foreach ($steps as $i => $step)
            <li class="flex flex-col gap-3 lg:px-8 lg:first:pl-0 lg:last:pr-0">
                <span class="relative w-fit">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-alt text-primary">
                        <x-lucide :name="$step['icon']" :size="26" />
                    </span>
                    <span class="absolute -right-2 -bottom-2 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-white ring-2 ring-surface">{{ $i + 1 }}</span>
                </span>
                <h3 class="mt-2 text-base font-bold text-ink">{{ $step['title'] }}</h3>
                <p class="text-sm leading-relaxed text-muted">{{ $step['body'] }}</p>
            </li>
        @endforeach
    </ol>
</section>
