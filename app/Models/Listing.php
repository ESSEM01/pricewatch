<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One product on one store: the URL that gets scraped.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property string $url
 * @property bool $is_own
 * @property ListingStatus $status
 * @property int $consecutive_failures
 * @property int|null $current_price
 * @property string $currency
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $next_check_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'is_own', 'store_id'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /** An unchanged price is still stored once per this many hours. */
    public const HEARTBEAT_HOURS = 24;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_own' => 'boolean',
            'status' => ListingStatus::class,
            'consecutive_failures' => 'integer',
            'current_price' => 'integer',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<PricePoint, $this> */
    public function pricePoints(): HasMany
    {
        return $this->hasMany(PricePoint::class);
    }

    /** @return HasOne<Forecast, $this> */
    public function forecast(): HasOne
    {
        return $this->hasOne(Forecast::class);
    }

    /**
     * Record the outcome of a successful scrape.
     *
     * A price point is stored only when the price (or currency) changed, or
     * when the last point is older than the heartbeat window. Returns the new
     * point, or null when nothing needed storing.
     */
    public function recordPrice(int $price, string $currency): ?PricePoint
    {
        if ($price < 0) {
            throw new InvalidArgumentException("A price cannot be negative, got {$price}.");
        }

        $now = now();

        return DB::transaction(function () use ($price, $currency, $now): ?PricePoint {
            $last = $this->pricePoints()->latest('recorded_at')->latest('id')->first();

            $changed = $last === null || $last->price !== $price || $last->currency !== $currency;
            $heartbeatDue = $last !== null
                && $last->recorded_at->lte($now->copy()->subHours(self::HEARTBEAT_HOURS));

            $point = ($changed || $heartbeatDue)
                ? $this->pricePoints()->create([
                    'price' => $price,
                    'currency' => $currency,
                    'recorded_at' => $now,
                ])
                : null;

            $this->forceFill([
                'current_price' => $price,
                'currency' => $currency,
                'last_checked_at' => $now,
                'consecutive_failures' => 0,
                'status' => $this->status === ListingStatus::Failing ? ListingStatus::Active : $this->status,
            ])->save();

            return $point;
        });
    }
}
