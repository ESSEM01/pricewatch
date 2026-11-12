<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $own = $this->listings->firstWhere('is_own', true);
        $cheapest = $this->cheapestCompetitor();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'own_price' => $own === null ? null : MoneyResource::make($own->current_price, $own->currency),
            'cheapest_competitor' => $cheapest === null ? null : [
                'store' => $cheapest->store->name,
                'price' => MoneyResource::make($cheapest->current_price, $cheapest->currency),
            ],
            'listings_count' => $this->listings->count(),
            // The list endpoint stays light; the detail endpoint includes every listing.
            'listings' => $this->when(
                $request->routeIs('api.products.show'),
                fn () => ListingResource::collection($this->listings),
            ),
        ];
    }
}
