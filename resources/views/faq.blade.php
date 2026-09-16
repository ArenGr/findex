@extends('layouts.app')

@section('title', __('meta.faq_title'))
@section('description', __('meta.faq_description'))

@section('content')
    <x-vertical-hero
        icon="info"
        :eyebrow="__('footer.columns.help.title')"
        :title="__('faq.heading')"
        :subtitle="__('faq.intro')"
    />

    <section class="site-container py-16">
        <div class="mx-auto max-w-3xl">
<div class="divide-y divide-border border-t border-b border-border">
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
