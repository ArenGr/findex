@extends('layouts.app')

@section('title', __('offers.categories.'.$category.'.title') . ' — Findex')
@section('description', __('bank_products.'.$category.'.intro'))

@php
    // Columns/field list are translated (lang/*/bank_products.php); the row
    // values are language-neutral and live in config/bank-products.php.
    $columns = __('bank_products.'.$category.'.columns');
    $rows = config('bank-products.'.$category.'.rows', []);
    $fields = __('bank_products.'.$category.'.fields');
@endphp

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <a href="{{ route('banks.index') }}" class="text-sm font-medium text-primary hover:underline">
            &larr; {{ __('offers.back_to_all') }}
        </a>

        <h1 class="mt-4 font-heading text-2xl font-bold break-words text-ink lg:text-3xl">
            {{ __('offers.categories.'.$category.'.title') }}
        </h1>
        <p class="mt-3 max-w-2xl text-base leading-relaxed text-muted">
            {{ __('bank_products.'.$category.'.intro') }}
        </p>

        {{-- Stated plainly and above the table, not in a footnote: these
        figures are invented, and a partner reading this page has to be able
        to tell that at a glance. --}}
        <div class="mt-8 flex flex-wrap items-start gap-3 rounded-2xl border border-accent-yellow/40 bg-accent-yellow/10 px-5 py-4">
            <span class="max-w-full rounded-full bg-accent-yellow/30 px-2.5 py-1 text-[10px] font-semibold tracking-wide break-words text-ink uppercase">
                {{ __('bank_products.sample_badge') }}
            </span>
            {{-- min-w + flex-wrap drops this onto its own line instead of being
            squeezed beside the badge; break-words because a single long
            Armenian word otherwise overflows the narrow column. --}}
            <p class="min-w-[12rem] flex-1 text-sm leading-relaxed break-words text-ink">{{ __('bank_products.sample_notice') }}</p>
        </div>

        {{-- Horizontally scrollable rather than collapsed to a card list:
        the point of this page is to show the full column set a bank has to
        supply, so nothing is hidden at narrow widths. --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-placeholder">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-placeholder bg-placeholder/20 text-xs font-semibold text-subtle uppercase">
                        @foreach ($columns as $i => $column)
                            <th class="px-4 py-3 whitespace-nowrap {{ $i === 0 ? 'text-left' : 'text-right' }}">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-placeholder last:border-b-0">
                            @foreach ($row as $i => $cell)
                                <td class="px-4 py-4 whitespace-nowrap {{ $i === 0 ? 'font-medium text-ink' : 'text-right text-subtle' }}">
                                    {{ $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-12 rounded-2xl border border-placeholder p-6 sm:p-8">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('bank_products.needed_heading') }}</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">{{ __('bank_products.needed_intro') }}</p>

            <ul class="mt-5 space-y-2.5">
                @foreach ($fields as $field)
                    <li class="flex items-start gap-3 text-sm leading-relaxed text-body-text">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="mt-0.5 h-4 w-4 shrink-0 fill-primary">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0z" clip-rule="evenodd" />
                        </svg>
                        {{ $field }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-8 rounded-2xl border border-primary/30 bg-primary/5 p-6 text-center sm:p-8">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('bank_products.cta_heading') }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-muted">{{ __('bank_products.cta_body') }}</p>
            <a href="{{ route('contact') }}" class="mt-5 inline-block bg-primary px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark">
                {{ __('bank_products.cta_button') }}
            </a>
        </div>
    </section>
@endsection
