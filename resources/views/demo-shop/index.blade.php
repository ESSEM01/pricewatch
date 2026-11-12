@extends('demo-shop.layout')

@section('title', 'Catalogo')

@section('content')
    <h1>Catalogo</h1>

    <table>
        <thead>
            <tr>
                <th scope="col">Prodotto</th>
                @foreach ($stores as $store)
                    <th scope="col">{{ $store->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                <tr>
                    <th scope="row">{{ $product->name }}</th>
                    @foreach ($stores as $store)
                        <td><a href="{{ route('demo-shop.product', [$store->slug, $product->slug]) }}">Vedi</a></td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
