<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A listing's price moved. Alert rules (week 3) listen to this.
 */
final class PriceChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Listing $listing,
        public readonly int $previousPrice,
        public readonly int $newPrice,
    ) {}

    /** Signed change in basis points (100 = 1%). */
    public function changeBasisPoints(): int
    {
        return $this->previousPrice === 0
            ? 0
            : (int) round(($this->newPrice - $this->previousPrice) * 10_000 / $this->previousPrice);
    }
}
