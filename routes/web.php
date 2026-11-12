<?php

use App\Http\Controllers\DemoShopController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('products', 'pages::products.index')->name('products.index');
    Route::livewire('products/{product}', 'pages::products.show')->name('products.show');
    Route::livewire('alerts', 'pages::alerts.index')->name('alerts.index');
});

if (config('demo_shop.enabled')) {
    Route::prefix('demo-shop')->name('demo-shop.')->controller(DemoShopController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{store}/{product}', 'show')->name('product');
    });
}

require __DIR__.'/settings.php';
