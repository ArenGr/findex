@extends('layouts.app')

@section('title', __('meta.offers_title'))
@section('description', __('meta.offers_description'))

@section('content')
    <x-offers-table />
@endsection
