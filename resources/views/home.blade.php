@extends('layouts.app')

@section('title', __('meta.home_title'))
@section('description', __('meta.home_description'))

@section('content')
    <x-hero-carousel />
    <x-services-grid />
    @feature('rates')
        <x-rates-table />
    @endfeature
    <x-trust-section />
    @feature('organizations')
        <x-top-rated-organizations />
    @endfeature
    @feature('articles')
        <x-news-section />
    @endfeature
@endsection
