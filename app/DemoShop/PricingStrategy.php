<?php

namespace App\DemoShop;

/**
 * How a fictional demo store moves its prices over time.
 */
enum PricingStrategy: string
{
    /** Flat price, 12% off Friday to Sunday every other week. */
    case WeekendPromo = 'weekend_promo';

    /** Slowly falls through a 90-day product cycle, then resets. */
    case TrendDown = 'trend_down';

    /** Jumps within ±5% every 3 days. */
    case Volatile = 'volatile';
}
