<?php

namespace App\Listeners;

use App\Alerts\AlertEvaluator;
use App\Events\PriceChanged;

final class EvaluateAlertRules
{
    public function __construct(private readonly AlertEvaluator $evaluator) {}

    public function handle(PriceChanged $event): void
    {
        $this->evaluator->evaluate($event->listing, $event->previousPrice, $event->newPrice);
    }
}
