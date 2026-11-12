<?php

namespace App\DemoShop;

final readonly class DemoStore
{
    public function __construct(
        public string $slug,
        public string $name,
        public PricingStrategy $strategy,
        /** Multiplier on the product's base price, e.g. 1.04 = 4% dearer. */
        public float $markup,
    ) {}
}
