<?php

namespace App\Alerts;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Listing;
use App\Notifications\PriceAlert;

/**
 * Checks a price change against the product's alert rules and notifies the
 * owner. Rules fire when a condition is crossed (not while it stays true),
 * at most once per rule per cooldown window.
 */
final class AlertEvaluator
{
    public const COOLDOWN_HOURS = 24;

    public function evaluate(Listing $listing, int $previousPrice, int $newPrice): void
    {
        $rules = AlertRule::query()
            ->where('product_id', $listing->product_id)
            ->where('is_active', true)
            ->with('user')
            ->get();

        foreach ($rules as $rule) {
            if ($this->matches($rule, $listing, $previousPrice, $newPrice) && $this->claim($rule)) {
                $rule->user->notify(new PriceAlert($rule, $listing, $previousPrice, $newPrice));
            }
        }
    }

    private function matches(AlertRule $rule, Listing $listing, int $previous, int $new): bool
    {
        return match ($rule->type) {
            AlertRuleType::BelowThreshold => $rule->threshold !== null
                && $new < $rule->threshold
                && $previous >= $rule->threshold,

            AlertRuleType::CompetitorUndercut => $this->undercuts($listing, $previous, $new),

            // threshold is in basis points: |Δ| / previous >= threshold / 10 000
            AlertRuleType::ChangePercent => $rule->threshold !== null
                && $previous > 0
                && abs($new - $previous) * 10_000 >= $rule->threshold * $previous,
        };
    }

    private function undercuts(Listing $listing, int $previous, int $new): bool
    {
        if ($listing->is_own) {
            return false;
        }

        $ownPrice = $listing->product->ownListing?->current_price;

        return $ownPrice !== null && $new < $ownPrice && $previous >= $ownPrice;
    }

    /**
     * Atomically takes the rule's cooldown slot, so two workers handling two
     * changes at the same moment cannot both send the alert.
     */
    private function claim(AlertRule $rule): bool
    {
        $now = now();

        $claimed = AlertRule::query()
            ->whereKey($rule->id)
            ->where(fn ($q) => $q->whereNull('last_triggered_at')
                ->orWhere('last_triggered_at', '<=', $now->copy()->subHours(self::COOLDOWN_HOURS)))
            ->update(['last_triggered_at' => $now]);

        return $claimed === 1;
    }
}
