<?php

namespace App\DemoShop;

final readonly class DemoProduct
{
    public function __construct(
        public string $slug,
        public string $name,
        /** Cents. */
        public int $basePrice,
    ) {}
}
