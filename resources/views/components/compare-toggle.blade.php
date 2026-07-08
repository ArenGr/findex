@props(['organization'])

<button
    type="button"
    x-data
    @click.prevent="$store.compare.toggle({ slug: '{{ $organization->slug }}', name: @js($organization->name), logo: @js($organization->logo) })"
    :disabled="!$store.compare.has('{{ $organization->slug }}') && $store.compare.atLimit()"
    :class="$store.compare.has('{{ $organization->slug }}')
        ? 'border-primary bg-primary/5 text-primary'
        : 'border-placeholder text-muted hover:text-ink disabled:cursor-not-allowed disabled:opacity-50'"
    {{ $attributes->merge(['class' => 'rounded-full border px-4 py-2 text-xs font-medium transition']) }}
>
    <span x-show="!$store.compare.has('{{ $organization->slug }}')">{{ __('organizations.compare_add') }}</span>
    <span x-show="$store.compare.has('{{ $organization->slug }}')">✓ {{ __('organizations.compare_added') }}</span>
</button>
