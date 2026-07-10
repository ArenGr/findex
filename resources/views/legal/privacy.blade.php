@extends('layouts.app')

@section('title', __('meta.privacy_title'))

@section('content')
    <x-legal-page
        :title="__('legal.privacy.title')"
        :updated="\Illuminate\Support\Carbon::create(2026, 7, 10)"
        :sections="__('legal.privacy.sections')"
    />
@endsection
