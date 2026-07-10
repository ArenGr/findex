@extends('layouts.app')

@section('title', __('meta.terms_title'))

@section('content')
    <x-legal-page
        :title="__('legal.terms.title')"
        :updated="\Illuminate\Support\Carbon::create(2026, 7, 10)"
        :sections="__('legal.terms.sections')"
    />
@endsection
