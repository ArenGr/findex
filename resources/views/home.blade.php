@extends('layouts.app')

@section('title', __('meta.home_title'))
@section('description', __('meta.home_description'))

@section('content')
    <x-hero-carousel />
    <x-partner-logos />
    <x-rates-table />
    <x-offers-table />
    <x-top-rated-organizations />
    <x-news-section />
@endsection
