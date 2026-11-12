<?php

use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\DemoShopController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::view('bot', 'bot')->name('bot');
Route::post('demo/login', DemoLoginController::class)->middleware(['guest', 'throttle:10,1'])->name('demo.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

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
