<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
| Read-only API. Authenticate with a personal token from Settings → API tokens:
|   curl -H "Authorization: Bearer <token>" -H "Accept: application/json" https://…/api/products
| See docs/API.md.
*/

Route::middleware(['auth:sanctum', 'abilities:read', 'throttle:api'])->name('api.')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/history', [ProductController::class, 'history'])->name('products.history');
    Route::get('products/{product}/forecast', [ProductController::class, 'forecast'])->name('products.forecast');
});
