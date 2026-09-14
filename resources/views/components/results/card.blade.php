@props(['badge' => null])

{{--
    One result. The same box whether it holds a bank rate, an insurance quote,
    a travel offer or an organization - see the note in components/results.
--}}
<article {{ $attributes->merge(['class' => 'relative rounded-2xl border border-border bg-surface p-5 transition-colors hover:border-primary/40 sm:p-6']) }}>
    @if ($badge)
        <span class="absolute top-0 right-5 -translate-y-1/2 rounded-full bg-accent-yellow px-3 py-1 text-xs font-semibold text-ink">
            {{ $badge }}
        </span>
    @endif

    {{ $slot }}
</article>
