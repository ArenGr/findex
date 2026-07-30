@extends('layouts.app')

@section('title', __('meta.home_title'))
@section('description', __('meta.home_description'))

@section('content')
    <x-hero-carousel />
    <x-services-grid />
    <x-rates-table />
    <x-trust-section />
    <x-top-rated-organizations />
    <x-news-section />
@endsection
