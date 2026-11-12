<?php

namespace App\Enums;

enum AlertRuleType: string
{
    /** Any listing drops below `threshold` cents. */
    case BelowThreshold = 'below_threshold';

    /** A competitor's listing goes below the user's own listing. */
    case CompetitorUndercut = 'competitor_undercut';

    /** A listing moves by at least `threshold` basis points (100 = 1%). */
    case ChangePercent = 'change_percent';

    public function needsThreshold(): bool
    {
        return $this !== self::CompetitorUndercut;
    }
}
