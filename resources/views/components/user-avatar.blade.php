@props(['user', 'size' => 8])

{{-- The signed-in user, as a face or as their initials. --}}
<span
    {{ $attributes->merge(['class' => "flex h-{$size} w-{$size} shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-[11px] font-semibold tracking-wide text-primary select-none"]) }}
    aria-hidden="true"
>
    @if ($user->avatar)
        <img src="{{ $user->avatar }}" alt="" width="32" height="32" class="h-full w-full object-cover" referrerpolicy="no-referrer">
    @else
        {{ $user->initials() }}
    @endif
</span>
