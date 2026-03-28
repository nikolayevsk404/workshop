<?php

use App\Models\DifficultyTier;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\QuotationCardTrade;
use App\Models\User;
use App\Services\MatchInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();
    $this->match = (new MatchInitializationService)->createMatch($this->user, $quotationCardIds, $tier->id);

    $this->playerType = ParticipantType::where('slug', 'player')->first();
    $this->aiType = ParticipantType::where('slug', 'ai')->first();

    $quotationCard = $this->match->quotationCards()->first();
    $this->trade = $quotationCard->trades()->with(['items.tokenColor', 'items.tradeSide'])->first();
});

function givePlayerTokensForTradeAction($match, $participantTypeId, QuotationCardTrade $trade, string $side = 'left'): void
{
    $items = $trade->items()->whereHas('tradeSide', fn ($q) => $q->where('slug', $side))->get();

    foreach ($items as $item) {
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $item->token_color_id)
            ->update(['quantity' => $item->quantity + 2]);
    }
}

it('player can execute a valid trade via Livewire', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTradeAction($this->match, $this->playerType->id, $this->trade, 'left');

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('executeTrade', $this->trade->id, 'left_to_right')
        ->assertSet('flashType', null);

    $this->match->refresh();
    expect($this->match->has_acted_this_turn)->toBeTrue();
});

it('trade updates token inventory in the UI', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTradeAction($this->match, $this->playerType->id, $this->trade, 'left');

    $leftItems = $this->trade->items()->whereHas('tradeSide', fn ($q) => $q->where('slug', 'left'))->get();

    $before = [];
    foreach ($leftItems as $item) {
        $before[$item->token_color_id] = MatchTokenInventory::where('match_id', $this->match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
    }

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('executeTrade', $this->trade->id, 'left_to_right');

    foreach ($leftItems as $item) {
        $after = MatchTokenInventory::where('match_id', $this->match->id)
            ->where('participant_type_id', $this->playerType->id)
            ->where('token_color_id', $item->token_color_id)
            ->value('quantity');
        expect($after)->toBeLessThan($before[$item->token_color_id]);
    }
});

it('trading disables further actions this turn', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTradeAction($this->match, $this->playerType->id, $this->trade, 'left');

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('executeTrade', $this->trade->id, 'left_to_right');

    $this->match->refresh();
    expect($this->match->has_acted_this_turn)->toBeTrue();
});

it('invalid trade shows error message', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('executeTrade', $this->trade->id, 'left_to_right')
        ->assertSet('flashType', 'error')
        ->assertNotSet('flashMessage', null);
});

it('player cannot trade on AI turn', function () {
    $this->match->update([
        'current_participant_type_id' => $this->aiType->id,
        'has_acted_this_turn' => false,
    ]);

    givePlayerTokensForTradeAction($this->match, $this->playerType->id, $this->trade, 'left');

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('executeTrade', $this->trade->id, 'left_to_right')
        ->assertSet('flashType', 'error')
        ->assertSet('flashMessage', 'Não é a vez deste participante.');
});
