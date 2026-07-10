@extends('layouts.app')

@section('title', __('meta.cookies_title'))

@section('content')
    <x-legal-page
        :title="__('legal.cookies.title')"
        :updated="\Illuminate\Support\Carbon::create(2026, 7, 10)"
        :sections="__('legal.cookies.sections')"
    />
@endsection
