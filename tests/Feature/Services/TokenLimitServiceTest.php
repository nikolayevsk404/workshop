<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\TokenColor;
use App\Models\TurnActionType;
use App\Models\User;
use App\Services\MatchInitializationService;
use App\Services\TokenLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->tokenLimitService = new TokenLimitService;
    $this->playerType = ParticipantType::where('slug', 'player')->first();
});

function createMatchForTokenLimit(): GameMatch
{
    $user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    return (new MatchInitializationService)->createMatch($user, $quotationCardIds, $tier->id);
}

function setPlayerTokenTotal(GameMatch $match, int $participantTypeId, int $total): void
{
    $colors = TokenColor::all();
    $perColor = intdiv($total, $colors->count());
    $remainder = $total % $colors->count();

    foreach ($colors as $index => $color) {
        $qty = $perColor + ($index < $remainder ? 1 : 0);
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $color->id)
            ->update(['quantity' => $qty]);
    }
}

it('isOverLimit returns true when total tokens exceed 10', function () {
    $match = createMatchForTokenLimit();
    setPlayerTokenTotal($match, $this->playerType->id, 11);

    expect($this->tokenLimitService->isOverLimit($match, $this->playerType->id))->toBeTrue();
});

it('isOverLimit returns false when total tokens are 10 or less', function () {
    $match = createMatchForTokenLimit();
    setPlayerTokenTotal($match, $this->playerType->id, 10);

    expect($this->tokenLimitService->isOverLimit($match, $this->playerType->id))->toBeFalse();

    setPlayerTokenTotal($match, $this->playerType->id, 5);
    expect($this->tokenLimitService->isOverLimit($match, $this->playerType->id))->toBeFalse();
});

it('getExcessCount returns correct count', function () {
    $match = createMatchForTokenLimit();

    setPlayerTokenTotal($match, $this->playerType->id, 12);
    expect($this->tokenLimitService->getExcessCount($match, $this->playerType->id))->toBe(2);

    setPlayerTokenTotal($match, $this->playerType->id, 10);
    expect($this->tokenLimitService->getExcessCount($match, $this->playerType->id))->toBe(0);

    setPlayerTokenTotal($match, $this->playerType->id, 8);
    expect($this->tokenLimitService->getExcessCount($match, $this->playerType->id))->toBe(0);
});

it('returnTokens decreases correct token inventories', function () {
    $match = createMatchForTokenLimit();

    $redColor = TokenColor::where('slug', 'red')->first();
    $blueColor = TokenColor::where('slug', 'blue')->first();

    MatchTokenInventory::where('match_id', $match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $redColor->id)
        ->update(['quantity' => 5]);

    MatchTokenInventory::where('match_id', $match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $blueColor->id)
        ->update(['quantity' => 7]);

    $this->tokenLimitService->returnTokens($match, $this->playerType->id, ['red' => 1, 'blue' => 1]);

    expect(
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $redColor->id)
            ->value('quantity')
    )->toBe(4);

    expect(
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $blueColor->id)
            ->value('quantity')
    )->toBe(6);
});

it('returnTokens fails if returned amount does not match excess', function () {
    $match = createMatchForTokenLimit();
    setPlayerTokenTotal($match, $this->playerType->id, 12);

    $firstColor = TokenColor::first();

    expect(fn () => $this->tokenLimitService->returnTokens($match, $this->playerType->id, [$firstColor->slug => 1]))
        ->toThrow(InvalidArgumentException::class, 'É necessário devolver exatamente 2 token(s).');
});

it('returnTokens creates turn record with correct data', function () {
    $match = createMatchForTokenLimit();

    $redColor = TokenColor::where('slug', 'red')->first();
    MatchTokenInventory::where('match_id', $match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $redColor->id)
        ->update(['quantity' => 11]);

    $this->tokenLimitService->returnTokens($match, $this->playerType->id, ['red' => 1]);

    $returnAction = TurnActionType::where('slug', 'return_tokens')->first();
    $turn = MatchTurn::where('match_id', $match->id)
        ->where('turn_action_type_id', $returnAction->id)
        ->latest('id')
        ->first();

    expect($turn)->not->toBeNull()
        ->and($turn->action_data['tokens_returned'])->toBe(['red' => 1])
        ->and($turn->action_data['participant'])->toBe('player');
});

it('returnTokens fails if participant does not have the tokens specified', function () {
    $match = createMatchForTokenLimit();

    $redColor = TokenColor::where('slug', 'red')->first();
    MatchTokenInventory::where('match_id', $match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $redColor->id)
        ->update(['quantity' => 11]);

    expect(fn () => $this->tokenLimitService->returnTokens($match, $this->playerType->id, ['blue' => 1]))
        ->toThrow(InvalidArgumentException::class, 'Tokens insuficientes da cor blue.');
});
