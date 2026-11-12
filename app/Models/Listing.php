<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Events\PriceChanged;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
 * @property string|null $last_error
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
     * Listings whose next check is due (or never planned), excluding paused ones.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function due(Builder $query): void
    {
        $query->where('status', '!=', ListingStatus::Paused)
            ->where(fn (Builder $q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()));
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

        [$point, $previous] = DB::transaction(function () use ($price, $currency, $now): array {
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
                'next_check_at' => $now->copy()->addHours((int) config('scraping.check_interval_hours')),
                'consecutive_failures' => 0,
                'last_error' => null,
                'status' => $this->status === ListingStatus::Failing ? ListingStatus::Active : $this->status,
            ])->save();

            return [$point, $last];
        });

        // Outside the transaction, so listeners never see uncommitted data.
        if ($previous !== null && $previous->price !== $price) {
            PriceChanged::dispatch($this, $previous->price, $price);
        }

        return $point;
    }

    /**
     * Record a failed scrape and plan the retry: soon at first, then a long
     * back-off once failures pile up or the failure is permanent.
     */
    public function recordFailure(string $reason, bool $permanent = false): void
    {
        $now = now();
        $failures = $this->consecutive_failures + 1;
        $backOff = $permanent || $failures >= (int) config('scraping.failures_before_backoff');

        $status = match (true) {
            $this->status === ListingStatus::Paused => ListingStatus::Paused,
            $backOff => ListingStatus::Failing,
            default => $this->status,
        };

        $this->forceFill([
            'consecutive_failures' => $failures,
            'last_error' => Str::limit($reason, 497),
            'last_checked_at' => $now,
            'status' => $status,
            'next_check_at' => $backOff
                ? $now->copy()->addHours((int) config('scraping.backoff_hours'))
                : $now->copy()->addMinutes((int) config('scraping.retry_after_minutes') * $failures),
        ])->save();
    }
}
