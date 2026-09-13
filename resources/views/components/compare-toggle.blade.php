@props(['organization'])

<button
    type="button"
    x-data
    @click.prevent="$store.compare.toggle({ slug: @js($organization->slug), name: @js($organization->name), logo: @js($organization->logo) })"
    :disabled="!$store.compare.has(@js($organization->slug)) && $store.compare.atLimit()"
    :class="{
        'border-primary bg-primary/5 text-primary': $store.compare.has(@js($organization->slug)),
        'border-placeholder text-muted hover:text-ink disabled:cursor-not-allowed disabled:opacity-50': !$store.compare.has(@js($organization->slug)),
    }"
    {{ $attributes->merge(['class' => 'rounded-full border border-placeholder px-4 py-2 text-xs font-medium text-muted transition hover:text-ink disabled:cursor-not-allowed disabled:opacity-50']) }}
>
    <span x-show="!$store.compare.has(@js($organization->slug))">{{ __('organizations.compare_add') }}</span>
    {{-- x-cloak, or both labels paint until Alpine boots and the button visibly shrinks. --}}
    <span x-show="$store.compare.has(@js($organization->slug))" x-cloak>✓ {{ __('organizations.compare_added') }}</span>
</button>
