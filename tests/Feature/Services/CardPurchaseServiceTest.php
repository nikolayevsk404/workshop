<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchCompartmentCard;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\TurnActionType;
use App\Models\User;
use App\Services\CardPurchaseService;
use App\Services\MatchInitializationService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->purchaseService = new CardPurchaseService(new ScoringService);
    $this->playerType = ParticipantType::where('slug', 'player')->first();
    $this->aiType = ParticipantType::where('slug', 'ai')->first();
});

function createMatchForPurchase(): GameMatch
{
    $user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    $match = (new MatchInitializationService)->createMatch($user, $quotationCardIds, $tier->id);
    $match->update([
        'current_participant_type_id' => ParticipantType::where('slug', 'player')->first()->id,
        'has_acted_this_turn' => true,
    ]);

    return $match;
}

function givePlayerTokensForCard(GameMatch $match, int $participantTypeId, MatchCompartmentCard $compartmentCard): void
{
    $cardTokens = $compartmentCard->card->tokens;

    foreach ($cardTokens as $cardToken) {
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $cardToken->token_color_id)
            ->update(['quantity' => $cardToken->quantity]);
    }
}

function getFaceUpCard(GameMatch $match): MatchCompartmentCard
{
    $compartment = $match->compartments()->first();

    return $compartment->faceUpCard();
}

it('successful purchase removes correct tokens from inventory', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $cardTokens = $faceUpCard->card->tokens;
    foreach ($cardTokens as $cardToken) {
        $remaining = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $cardToken->token_color_id)
            ->value('quantity');
        expect($remaining)->toBe(0);
    }
});

it('successful purchase marks card as purchased with correct participant', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $faceUpCard->refresh();
    expect($faceUpCard->is_purchased)->toBeTrue()
        ->and($faceUpCard->purchased_by_participant_type_id)->toBe($this->playerType->id)
        ->and($faceUpCard->purchased_at)->not->toBeNull();
});

it('points are calculated correctly and stored on the card record', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $points = $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $faceUpCard->refresh();
    expect($faceUpCard->points_scored)->toBe($points)
        ->and($points)->toBeGreaterThan(0);
});

it('match score is updated for the correct participant', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $scoreBefore = $match->player_score;

    $points = $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $match->refresh();
    expect($match->player_score)->toBe($scoreBefore + $points);
});

it('match player_cards_purchased counter increments', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $countBefore = $match->player_cards_purchased;

    $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $match->refresh();
    expect($match->player_cards_purchased)->toBe($countBefore + 1);
});

it('purchase fails if participant has not acted this turn', function () {
    $match = createMatchForPurchase();
    $match->update(['has_acted_this_turn' => false]);

    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    expect(fn () => $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id))
        ->toThrow(InvalidArgumentException::class, 'É necessário lançar o dado ou trocar tokens antes de comprar uma carta.');
});

it('purchase fails if card is not face up', function () {
    $match = createMatchForPurchase();
    $compartment = $match->compartments()->first();

    $secondCard = $compartment->cards()->where('position', 2)->first();
    if (! $secondCard) {
        $this->markTestSkipped('No second card in compartment.');
    }

    givePlayerTokensForCard($match, $this->playerType->id, $secondCard);

    expect(fn () => $this->purchaseService->purchaseCard($match, $this->playerType->id, $secondCard->id))
        ->toThrow(InvalidArgumentException::class, 'Esta carta não está virada para cima.');
});

it('purchase fails if participant lacks required tokens', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);

    expect(fn () => $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id))
        ->toThrow(InvalidArgumentException::class, 'Tokens insuficientes para comprar esta carta.');
});

it('compartment star bonus activates when last card in compartment is purchased', function () {
    $match = createMatchForPurchase();
    $compartment = $match->compartments()->first();

    $cards = $compartment->cards()->orderBy('position')->get();
    foreach ($cards as $card) {
        $card->update([
            'is_purchased' => true,
            'purchased_by_participant_type_id' => $this->playerType->id,
            'purchased_at' => now(),
        ]);
    }

    $lastCard = $cards->last();
    $lastCard->update(['is_purchased' => false]);

    givePlayerTokensForCard($match, $this->playerType->id, $lastCard);

    $this->purchaseService->purchaseCard($match, $this->playerType->id, $lastCard->id);

    $compartment->refresh();
    expect($compartment->is_star_bonus_active)->toBeTrue();
});

it('compartments_emptied increments when a compartment becomes empty', function () {
    $match = createMatchForPurchase();
    $compartment = $match->compartments()->first();

    $emptiedBefore = $match->compartments_emptied;

    $cards = $compartment->cards()->orderBy('position')->get();
    foreach ($cards as $card) {
        $card->update([
            'is_purchased' => true,
            'purchased_by_participant_type_id' => $this->playerType->id,
            'purchased_at' => now(),
        ]);
    }

    $lastCard = $cards->last();
    $lastCard->update(['is_purchased' => false]);

    givePlayerTokensForCard($match, $this->playerType->id, $lastCard);

    $this->purchaseService->purchaseCard($match, $this->playerType->id, $lastCard->id);

    $match->refresh();
    expect($match->compartments_emptied)->toBe($emptiedBefore + 1);
});

it('star bonus correctly affects scoring of subsequent purchases', function () {
    $match = createMatchForPurchase();

    $compartment1 = $match->compartments()->first();
    $compartment1->update(['is_star_bonus_active' => true]);

    $compartment2 = $match->compartments()->where('id', '!=', $compartment1->id)->first();
    $faceUpCard = $compartment2->faceUpCard();
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $scoringService = new ScoringService;
    $starBonuses = $scoringService->getActiveStarBonuses($match);

    $expectedPoints = $scoringService->calculatePoints(0, $faceUpCard->card->star_count, $starBonuses);

    $actualPoints = $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    expect($actualPoints)->toBe($expectedPoints);
});

it('purchase creates turn record with correct action data', function () {
    $match = createMatchForPurchase();
    $faceUpCard = getFaceUpCard($match);
    givePlayerTokensForCard($match, $this->playerType->id, $faceUpCard);

    $points = $this->purchaseService->purchaseCard($match, $this->playerType->id, $faceUpCard->id);

    $purchaseAction = TurnActionType::where('slug', 'purchase_card')->first();
    $turn = MatchTurn::where('match_id', $match->id)
        ->where('turn_action_type_id', $purchaseAction->id)
        ->latest('id')
        ->first();

    expect($turn)->not->toBeNull()
        ->and($turn->action_data['card_id'])->toBe($faceUpCard->card_id)
        ->and($turn->action_data['points_scored'])->toBe($points)
        ->and($turn->action_data['participant'])->toBe('player');
});
