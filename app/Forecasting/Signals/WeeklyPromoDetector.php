<?php

namespace App\Forecasting\Signals;

use App\Forecasting\DailySeries;

/**
 * Finds days of the week on which a store repeatedly cuts its price.
 *
 * Each day is compared with the median of its own week, not of the whole
 * period, so a steady decline is not mistaken for "promos every day".
 */
final class WeeklyPromoDetector
{
    public function __construct(
        private readonly int $lookbackDays = 56,
        /** A day counts as a promo when at least this much below its week's median. */
        private readonly float $minimumDrop = 0.07,
        private readonly int $minimumWeeks = 2,
        private readonly float $minimumShare = 0.25,
    ) {}

    /**
     * @return list<int> ISO weekdays (1 = Monday ... 7 = Sunday), ascending.
     */
    public function detect(DailySeries $series): array
    {
        $recent = $series->lastDays($this->lookbackDays);
        $values = $recent->values();
        $promoCount = array_fill(1, 7, 0);
        $occurrences = array_fill(1, 7, 0);

        foreach (array_chunk($values, 7, preserve_keys: true) as $week) {
            if (count($week) < 7) {
                continue;
            }

            $median = $this->median(array_values($week));

            foreach ($week as $index => $price) {
                $weekday = $recent->start()->addDays($index)->isoWeekday();
                $occurrences[$weekday]++;

                if ($median > 0 && $price <= $median * (1 - $this->minimumDrop)) {
                    $promoCount[$weekday]++;
                }
            }
        }

        $weekdays = [];
        foreach ($promoCount as $weekday => $count) {
            if ($count >= $this->minimumWeeks && $count >= $occurrences[$weekday] * $this->minimumShare) {
                $weekdays[] = $weekday;
            }
        }

        return $weekdays;
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
