@extends('demo-shop.layout')

@section('title', $product->name.' – '.$store->name)

@push('head')
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="product:price:amount" content="{{ $amount }}">
    <meta property="product:price:currency" content="EUR">
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endpush

@section('content')
    <p>{{ $store->name }}</p>
    <h1>{{ $product->name }}</h1>
    <span class="price">{{ $formatted }}</span>
    <p>Disponibile. Spedizione in 24/48 ore.</p>
@endsection
