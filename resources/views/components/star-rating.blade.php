@props(['rating' => 0, 'size' => 'h-4 w-4'])

@php $rounded = round($rating); @endphp

<div class="flex items-center gap-0.5" role="img" aria-label="{{ number_format($rating, 1) }} / 5">
    @for ($i = 1; $i <= 5; $i++)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="{{ $size }} {{ $i <= $rounded ? 'fill-accent-yellow' : 'fill-placeholder' }}">
            <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
        </svg>
    @endfor
</div>
