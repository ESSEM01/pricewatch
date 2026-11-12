<?php

namespace App\Http\Controllers\Api;

use App\Forecasting\Forecaster;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only API over the authenticated user's products.
 */
class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $request->user()->products()
            ->with('listings.store')
            ->orderBy('name')
            ->paginate(50);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['listings' => $this->ownFirst(...), 'listings.store']));
    }

    public function history(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);
        $days = (int) ($request->validate(['days' => ['sometimes', 'integer', 'between:1,365']])['days'] ?? 90);
        $since = now()->subDays($days);

        $product->load(['listings' => $this->ownFirst(...), 'listings.store']);
        $points = PricePoint::query()
            ->whereIn('listing_id', $product->listings->modelKeys())
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('listing_id');

        return response()->json(['data' => $product->listings->map(fn (Listing $listing): array => [
            'listing_id' => $listing->id,
            'store' => $listing->store->name,
            'currency' => $listing->currency,
            'points' => ($points[$listing->id] ?? collect())->map(fn (PricePoint $point): array => [
                'recorded_at' => $point->recorded_at->toIso8601String(),
                'price' => $point->price,
            ])->values(),
        ])->values()]);
    }

    public function forecast(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);
        $product->load(['listings' => $this->ownFirst(...), 'listings.store', 'listings.forecast']);

        if ($product->listings->every(fn (Listing $listing): bool => $listing->forecast === null)) {
            return response()->json([
                'message' => 'Not enough price history to forecast yet: at least '.Forecaster::MINIMUM_DAYS.' days are needed.',
            ], 422);
        }

        return response()->json(['data' => $product->listings->map(fn (Listing $listing): array => [
            'listing_id' => $listing->id,
            'store' => $listing->store->name,
            'currency' => $listing->currency,
            'forecast' => $listing->forecast === null ? null : [
                'model' => $listing->forecast->model,
                'backtest_mape' => $listing->forecast->mape,
                'generated_at' => $listing->forecast->generated_at->toIso8601String(),
                'points' => $listing->forecast->points,
            ],
        ])->values()]);
    }

    /**
     * @param  HasMany<Listing, Product>  $query
     */
    private function ownFirst($query): void
    {
        $query->orderByDesc('is_own')->orderBy('id');
    }
}
