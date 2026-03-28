<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\QuotationCardTrade;
use App\Models\TurnActionType;
use App\Models\User;
use App\Services\MatchInitializationService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->tradeService = new TradeService;
    $this->playerType = ParticipantType::where('slug', 'player')->first();
    $this->aiType = ParticipantType::where('slug', 'ai')->first();
});

function createMatchForTrade(): GameMatch
{
    $user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    return (new MatchInitializationService)->createMatch($user, $quotationCardIds, $tier->id);
}

function getFirstTradeWithItems(GameMatch $match): QuotationCardTrade
{
    $quotationCard = $match->quotationCards()->first();

    return $quotationCard->trades()->with(['items.tokenColor', 'items.tradeSide'])->first();
}

function givePlayerTokensForTrade(GameMatch $match, int $participantTypeId, QuotationCardTrade $trade, string $side = 'left'): void
{
    $items = $trade->items()->whereHas('tradeSide', fn ($q) => $q->where('slug', $side))->get();

    foreach ($items as $item) {
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $item->token_color_id)
            ->update(['quantity' => $item->quantity + 2]);
    }
}

it('valid trade removes correct tokens and adds correct tokens', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTrade($match, $this->playerType->id, $trade, 'left');

    $leftItems = $trade->items()->whereHas('tradeSide', fn ($q) => $q->where('slug', 'left'))->get();
    $rightItems = $trade->items()->whereHas('tradeSide', fn ($q) => $q->where('slug', 'right'))->get();

    $beforeGive = [];
    foreach ($leftItems as $item) {
        $beforeGive[$item->token_color_id] = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
    }

    $beforeReceive = [];
    foreach ($rightItems as $item) {
        $beforeReceive[$item->token_color_id] = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
    }

    $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right');

    foreach ($leftItems as $item) {
        $afterQuantity = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
        expect($afterQuantity)->toBe($beforeGive[$item->token_color_id] - $item->quantity);
    }

    foreach ($rightItems as $item) {
        $afterQuantity = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
        expect($afterQuantity)->toBe($beforeReceive[$item->token_color_id] + $item->quantity);
    }
});

it('trade creates a turn record with correct action data', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTrade($match, $this->playerType->id, $trade, 'left');

    $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right');

    $tradeAction = TurnActionType::where('slug', 'trade')->first();
    $turn = MatchTurn::where('match_id', $match->id)->latest('id')->first();

    expect($turn)->not->toBeNull()
        ->and($turn->turn_action_type_id)->toBe($tradeAction->id)
        ->and($turn->action_data['quotation_card_trade_id'])->toBe($trade->id)
        ->and($turn->action_data['direction'])->toBe('left_to_right');
});

it('trade fails with insufficient tokens and no changes are made', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $turnsBefore = MatchTurn::where('match_id', $match->id)->count();

    expect(fn () => $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right'))
        ->toThrow(InvalidArgumentException::class, 'Tokens insuficientes');

    expect(MatchTurn::where('match_id', $match->id)->count())->toBe($turnsBefore);
});

it('trade fails if participant already acted this turn', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => true,
    ]);

    expect(fn () => $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right'))
        ->toThrow(InvalidArgumentException::class, 'O participante já realizou uma ação neste turno.');
});

it('trade fails if it is not the participant turn', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->aiType->id,
        'has_acted_this_turn' => false,
    ]);

    expect(fn () => $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right'))
        ->toThrow(InvalidArgumentException::class, 'Não é a vez deste participante.');
});

it('trade works in both directions left-to-right and right-to-left', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTrade($match, $this->playerType->id, $trade, 'right');

    $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'right_to_left');

    $match->refresh();
    expect($match->has_acted_this_turn)->toBeTrue();

    $turn = MatchTurn::where('match_id', $match->id)->latest('id')->first();
    expect($turn->action_data['direction'])->toBe('right_to_left');
});

it('trade fails if quotation card is not active in this match', function () {
    $match = createMatchForTrade();
    $matchQuotationCardIds = $match->quotationCards()->pluck('quotation_cards.id');

    $otherQuotationCard = QuotationCard::whereNotIn('id', $matchQuotationCardIds)->first();
    $otherTrade = $otherQuotationCard->trades()->first();

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    expect(fn () => $this->tradeService->executeTrade($match, $this->playerType->id, $otherTrade->id, 'left_to_right'))
        ->toThrow(InvalidArgumentException::class, 'Esta cotação não está ativa nesta partida.');
});

it('only 1 trade allowed per turn', function () {
    $match = createMatchForTrade();
    $trade = getFirstTradeWithItems($match);

    $match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTrade($match, $this->playerType->id, $trade, 'left');

    $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right');

    $match->refresh();
    givePlayerTokensForTrade($match, $this->playerType->id, $trade, 'left');

    expect(fn () => $this->tradeService->executeTrade($match, $this->playerType->id, $trade->id, 'left_to_right'))
        ->toThrow(InvalidArgumentException::class, 'O participante já realizou uma ação neste turno.');
});
