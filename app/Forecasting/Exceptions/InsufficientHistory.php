<?php

namespace App\Forecasting\Exceptions;

use RuntimeException;

final class InsufficientHistory extends RuntimeException
{
    public static function days(int $have, int $need): self
    {
        return new self("Not enough price history to forecast: {$have} day(s), {$need} needed.");
    }
}
