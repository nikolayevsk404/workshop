<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\QuotationCard;
use App\Models\User;
use App\Services\MatchInitializationService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->scoringService = new ScoringService;
});

function createMatchForScoring(): GameMatch
{
    $user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    return (new MatchInitializationService)->createMatch($user, $quotationCardIds, $tier->id);
}

it('returns correct points for all 12 scoring combinations', function (int $remainingTokens, int $starCount, int $expectedPoints) {
    $points = $this->scoringService->calculatePoints($remainingTokens, $starCount, 0);
    expect($points)->toBe($expectedPoints);
})->with([
    [3, 0, 1], [3, 1, 2], [3, 2, 3],
    [2, 0, 2], [2, 1, 3], [2, 2, 5],
    [1, 0, 3], [1, 1, 5], [1, 2, 8],
    [0, 0, 5], [0, 1, 8], [0, 2, 12],
]);

it('0 remaining tokens with 0 stars equals 5 points', function () {
    expect($this->scoringService->calculatePoints(0, 0, 0))->toBe(5);
});

it('0 remaining tokens with 2 stars equals 12 points', function () {
    expect($this->scoringService->calculatePoints(0, 2, 0))->toBe(12);
});

it('3+ remaining tokens with 0 stars equals 1 point', function () {
    expect($this->scoringService->calculatePoints(3, 0, 0))->toBe(1);
    expect($this->scoringService->calculatePoints(5, 0, 0))->toBe(1);
    expect($this->scoringService->calculatePoints(10, 0, 0))->toBe(1);
});

it('compartment star bonus adds to card star count', function () {
    expect($this->scoringService->calculatePoints(0, 0, 1))->toBe(8);
    expect($this->scoringService->calculatePoints(0, 1, 1))->toBe(12);
});

it('effective star count is capped at 2', function () {
    expect($this->scoringService->calculatePoints(0, 1, 2))->toBe(12);
    expect($this->scoringService->calculatePoints(0, 2, 1))->toBe(12);
    expect($this->scoringService->calculatePoints(0, 2, 2))->toBe(12);
});

it('getActiveStarBonuses correctly counts emptied compartments', function () {
    $match = createMatchForScoring();

    expect($this->scoringService->getActiveStarBonuses($match))->toBe(0);

    $compartment = $match->compartments()->first();
    $compartment->update(['is_star_bonus_active' => true]);

    expect($this->scoringService->getActiveStarBonuses($match))->toBe(1);

    $compartment2 = $match->compartments()->where('id', '!=', $compartment->id)->first();
    $compartment2->update(['is_star_bonus_active' => true]);

    expect($this->scoringService->getActiveStarBonuses($match))->toBe(2);
});
