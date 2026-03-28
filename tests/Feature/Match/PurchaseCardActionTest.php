<?php

use App\Models\DifficultyTier;
use App\Models\MatchCompartmentCard;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
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

    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => true,
    ]);

    $compartment = $this->match->compartments()->first();
    $this->faceUpCard = $compartment->faceUpCard();
});

function giveTokensForPurchaseAction($match, $participantTypeId, MatchCompartmentCard $card): void
{
    foreach ($card->card->tokens as $cardToken) {
        MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $cardToken->token_color_id)
            ->update(['quantity' => $cardToken->quantity]);
    }
}

it('player can purchase an eligible card via Livewire', function () {
    giveTokensForPurchaseAction($this->match, $this->playerType->id, $this->faceUpCard);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('purchaseCard', $this->faceUpCard->id)
        ->assertSet('flashType', 'success');

    $this->faceUpCard->refresh();
    expect($this->faceUpCard->is_purchased)->toBeTrue();
});

it('purchase updates score display on the board', function () {
    giveTokensForPurchaseAction($this->match, $this->playerType->id, $this->faceUpCard);

    $scoreBefore = $this->match->player_score;

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('purchaseCard', $this->faceUpCard->id);

    $this->match->refresh();
    expect($this->match->player_score)->toBeGreaterThan($scoreBefore);
});

it('purchase removes tokens from inventory display', function () {
    giveTokensForPurchaseAction($this->match, $this->playerType->id, $this->faceUpCard);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('purchaseCard', $this->faceUpCard->id);

    $totalTokens = MatchTokenInventory::where('match_id', $this->match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->sum('quantity');

    expect($totalTokens)->toBe(0);
});

it('next card in compartment becomes visible after purchase', function () {
    giveTokensForPurchaseAction($this->match, $this->playerType->id, $this->faceUpCard);
    $compartment = $this->faceUpCard->compartment;

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('purchaseCard', $this->faceUpCard->id);

    $newFaceUp = $compartment->faceUpCard();
    if ($compartment->cards()->where('is_purchased', false)->count() > 0) {
        expect($newFaceUp)->not->toBeNull()
            ->and($newFaceUp->id)->not->toBe($this->faceUpCard->id);
    } else {
        expect($newFaceUp)->toBeNull();
    }
});

it('Buy button only appears for eligible cards', function () {
    $this->match->update(['has_acted_this_turn' => false]);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->assertDontSee('Comprar Carta');
});
