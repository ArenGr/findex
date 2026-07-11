@extends('layouts.app')

@section('title', __('meta.careers_title'))
@section('description', __('meta.careers_description'))

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('careers.heading') }}</h1>
        <p class="mt-4 text-base leading-relaxed text-body-text">{{ __('careers.intro') }}</p>

        <div class="mt-10 space-y-8">
            <div class="rounded-2xl border border-placeholder p-6">
                <h2 class="font-heading text-base font-semibold text-ink">{{ __('careers.open_roles_heading') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-body-text">{{ __('careers.open_roles_body') }}</p>
            </div>

            <div class="rounded-2xl border border-placeholder p-6">
                <h2 class="font-heading text-base font-semibold text-ink">{{ __('careers.stay_in_touch_heading') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-body-text">
                    {!! __('careers.stay_in_touch_body', ['email' => '<a href="mailto:careers@findex.am" class="text-primary hover:underline">careers@findex.am</a>']) !!}
                </p>
            </div>
        </div>
    </section>
@endsection
