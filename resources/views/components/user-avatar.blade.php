@props(['user', 'size' => 8])

{{--
    The signed-in user, as a face or as their initials.

    OAuth sign-ins (Google, Apple) arrive with an avatar URL; everyone else
    gets a disc with their initials. aria-hidden either way - the name is
    always rendered next to this, so a screen reader announcing "AG" before it
    would just be noise.
--}}
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
