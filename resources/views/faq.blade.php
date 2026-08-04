@extends('layouts.app')

@section('title', __('meta.faq_title'))
@section('description', __('meta.faq_description'))

@section('content')
    {{--
        Outer section matches the home page's max-w-7xl - the actual
        reading content (heading, intro, every Q&A) stays inside a
        narrower inner wrapper, the same pattern the home page's own
        sections use for their body copy (e.g. trust-section's paragraph
        sits at max-w-xl inside its own max-w-7xl section). This is all
        single-column prose top to bottom with no grid to spread wider -
        widening it to 1280px directly would just stretch every line of
        text uncomfortably long, not make it easier to read.
    --}}
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-3xl">
            <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('faq.heading') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-muted">{{ __('faq.intro') }}</p>

            <div class="mt-10 divide-y divide-placeholder border-t border-b border-placeholder">
                @foreach (__('faq.questions') as $item)
                    <div x-data="{ open: false }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="flex w-full items-center justify-between gap-4 py-5 text-left"
                            :aria-expanded="open"
                        >
                            <span class="font-medium text-ink">{{ $item['question'] }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 shrink-0 fill-none stroke-current text-subtle" :class="{ 'rotate-180': open }">
                                <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="pb-5 text-sm leading-relaxed text-body-text">
                            {{ $item['answer'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
