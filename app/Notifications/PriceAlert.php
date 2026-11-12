<?php

namespace App\Notifications;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Listing;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AlertRule $rule,
        public readonly Listing $listing,
        public readonly int $previousPrice,
        public readonly int $newPrice,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Price alert: :product', ['product' => $this->listing->product->name]))
            ->greeting(__('Price alert'))
            ->line($this->message())
            ->action(__('Open product'), route('products.show', $this->listing->product))
            ->line(__('You get at most one alert per rule every :hours hours.', ['hours' => 24]));
    }

    /**
     * Stored in the notifications table and shown on the alerts page.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'product_id' => $this->listing->product_id,
            'product_name' => $this->listing->product->name,
            'listing_id' => $this->listing->id,
            'store_name' => $this->listing->store->name,
            'rule_type' => $this->rule->type->value,
            'previous_price' => $this->previousPrice,
            'new_price' => $this->newPrice,
            'currency' => $this->listing->currency,
            'message' => $this->message(),
        ];
    }

    public function message(): string
    {
        $store = $this->listing->store->name;
        $currency = $this->listing->currency;
        $new = Money::format($this->newPrice, $currency);
        $previous = Money::format($this->previousPrice, $currency);

        return match ($this->rule->type) {
            AlertRuleType::BelowThreshold => __(':store: :new is below :threshold (was :previous).', [
                'store' => $store, 'new' => $new, 'previous' => $previous,
                'threshold' => Money::format($this->rule->threshold, $currency),
            ]),
            AlertRuleType::CompetitorUndercut => __(':store now sells at :new, below your price of :own.', [
                'store' => $store, 'new' => $new,
                'own' => Money::format($this->listing->product->ownListing?->current_price, $currency),
            ]),
            AlertRuleType::ChangePercent => __(':store moved :change: :previous → :new.', [
                'store' => $store, 'previous' => $previous, 'new' => $new,
                'change' => sprintf('%+.1f%%', ($this->newPrice - $this->previousPrice) / max(1, $this->previousPrice) * 100),
            ]),
        };
    }
}
