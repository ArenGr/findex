@props([
    'variant' => 'primary',   // primary | secondary | ghost
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-70';

    $variants = [
        'primary' => 'bg-primary text-white shadow-sm hover:bg-primary-dark active:scale-[0.98]',
        'secondary' => 'border border-border-muted text-ink hover:bg-placeholder/30',
        'ghost' => 'text-primary hover:underline px-0 py-0',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class($classes) }}>
    {{ $slot }}
</{{ $tag }}>
