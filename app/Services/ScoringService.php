<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\ScoringRule;

class ScoringService
{
    public function calculatePoints(int $remainingTokens, int $cardStarCount, int $compartmentStarBonuses): int
    {
        $effectiveStarCount = min($cardStarCount + $compartmentStarBonuses, 2);

        return ScoringRule::calculatePoints($remainingTokens, $effectiveStarCount);
    }

    public function getActiveStarBonuses(GameMatch $match): int
    {
        return $match->compartments()->where('is_star_bonus_active', true)->count();
    }
}
