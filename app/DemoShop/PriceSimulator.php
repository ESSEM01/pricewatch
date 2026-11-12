<?php

namespace App\DemoShop;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Deterministic price generator for the demo stores: the same store,
 * product and day always give the same price, so demos and tests are
 * reproducible while prices still move in realistic patterns.
 */
final class PriceSimulator
{
    private const PROMO_DISCOUNT = 0.12;

    private const TREND_CYCLE_DAYS = 90;

    private const TREND_DAILY_DROP = 0.002;

    private const TREND_FLOOR = 0.80;

    private const VOLATILE_AMPLITUDE = 0.05;

    private const VOLATILE_BUCKET_DAYS = 3;

    public function __construct(private readonly CarbonImmutable $anchor) {}

    /**
     * Price in cents, always ending in 90 cents ("charm pricing").
     */
    public function priceFor(DemoStore $store, DemoProduct $product, CarbonInterface $date): int
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $dayIndex = (int) $this->anchor->diffInDays($day, absolute: false);
        $seed = "{$store->slug}|{$product->slug}";

        $factor = match ($store->strategy) {
            PricingStrategy::WeekendPromo => $this->weekendPromo($seed, $day),
            PricingStrategy::TrendDown => $this->trendDown($dayIndex),
            PricingStrategy::Volatile => $this->volatile($seed, $dayIndex),
        };

        return $this->charm((int) round($product->basePrice * $store->markup * $factor));
    }

    private function weekendPromo(string $seed, CarbonImmutable $day): float
    {
        $isWeekend = $day->isoWeekday() >= 5;
        // Each product starts its fortnightly rhythm on a different week.
        $isPromoWeek = ($day->isoWeek + crc32($seed)) % 2 === 0;

        return $isWeekend && $isPromoWeek ? 1 - self::PROMO_DISCOUNT : 1.0;
    }

    private function trendDown(int $dayIndex): float
    {
        $dayInCycle = (($dayIndex % self::TREND_CYCLE_DAYS) + self::TREND_CYCLE_DAYS) % self::TREND_CYCLE_DAYS;

        return max(self::TREND_FLOOR, 1 - self::TREND_DAILY_DROP * $dayInCycle);
    }

    private function volatile(string $seed, int $dayIndex): float
    {
        $bucket = (int) floor($dayIndex / self::VOLATILE_BUCKET_DAYS);
        $unit = (crc32("{$seed}|{$bucket}") % 10_001) / 10_000; // 0..1

        return 1 + ($unit * 2 - 1) * self::VOLATILE_AMPLITUDE;
    }

    /**
     * Rounds down to the nearest price ending in ,90 (e.g. 1000,00 → 999,90).
     */
    private function charm(int $cents): int
    {
        return max(90, intdiv($cents + 10, 100) * 100 - 10);
    }
}
