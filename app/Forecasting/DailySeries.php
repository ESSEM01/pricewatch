<?php

namespace App\Forecasting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One price per calendar day, built from sparse price changes by carrying
 * the last known price forward (the shop did not change it, so it held).
 */
final readonly class DailySeries
{
    /**
     * @param  list<float>  $values
     */
    private function __construct(
        private CarbonImmutable $start,
        private array $values,
    ) {}

    /**
     * @param  iterable<array{0: CarbonInterface, 1: int}>  $points  [recorded_at, price], oldest first.
     */
    public static function fromPoints(iterable $points, CarbonInterface $until): self
    {
        $lastPerDay = [];
        foreach ($points as [$recordedAt, $price]) {
            $lastPerDay[$recordedAt->toDateString()] = $price; // later points overwrite earlier ones
        }

        $end = CarbonImmutable::instance($until)->startOfDay();

        if ($lastPerDay === []) {
            return new self($end, []);
        }

        $start = CarbonImmutable::parse(array_key_first($lastPerDay))->startOfDay();
        $values = [];
        $current = null;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $current = $lastPerDay[$day->toDateString()] ?? $current;
            $values[] = (float) $current;
        }

        return new self($start, $values);
    }

    /** @return list<float> */
    public function values(): array
    {
        return $this->values;
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(max(0, count($this->values) - 1));
    }

    public function count(): int
    {
        return count($this->values);
    }

    /**
     * The last $days values, as a new series.
     */
    public function lastDays(int $days): self
    {
        $skip = max(0, count($this->values) - $days);

        return new self($this->start->addDays($skip), array_slice($this->values, $skip));
    }
}
