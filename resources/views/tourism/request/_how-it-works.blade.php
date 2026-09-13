@php
    // Illustrations, not glyphs - see x-travel-step-art.
    $steps = [
        1 => 'brief',
        2 => 'replies',
        3 => 'compare',
        4 => 'depart',
    ];
@endphp

<section class="travel-container py-16">
    <div class="space-y-12">
        <div class="space-y-1">
            <h2 class="text-3xl font-extrabold tracking-tight text-travel-ink sm:text-4xl">
                {{ __('tourism.request.works_heading') }}
            </h2>
            <p class="text-sm text-gray-500 sm:text-base">{{ __('tourism.request.works_sub') }}</p>
        </div>

        <ol class="relative grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0 lg:divide-x lg:divide-gray-200">
            @foreach ($steps as $n => $icon)
                <li class="relative flex flex-col items-center space-y-3 text-center lg:px-7 lg:first:pl-0 lg:last:pr-0">
                    <span class="relative">
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl border border-travel-200/80 bg-travel-50 shadow-sm">
                            <x-travel-step-art :name="$icon" class="h-10 w-10" />
                        </span>
                        <span class="absolute -right-2 -bottom-2 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-travel-600 text-xs font-bold text-white shadow">{{ $n }}</span>
                    </span>
                    <h3 class="pt-2 text-base font-bold text-gray-900">{{ __('tourism.request.step_' . $n . '_title') }}</h3>
                    <p class="text-xs leading-relaxed text-gray-500">{{ __('tourism.request.step_' . $n . '_body') }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
