{{-- The canonical Findex content card: soft ambient shadow, generous radius, white ground. --}}
<div {{ $attributes->class('rounded-3xl border border-placeholder bg-white p-6 shadow-[0px_4px_20px_rgba(0,0,0,0.05)] sm:p-8') }}>
    {{ $slot }}
</div>
