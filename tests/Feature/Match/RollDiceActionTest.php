<?php

use App\Models\DifficultyTier;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\TokenColor;
use App\Models\User;
use App\Services\DiceService;
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
});

it('player can roll dice on their turn via Livewire', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $mock = Mockery::mock(DiceService::class);
    $mock->shouldReceive('roll')->once()->andReturn('blue');
    $mock->shouldReceive('applyRoll')->once();
    app()->instance(DiceService::class, $mock);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice')
        ->assertSet('lastRollResult', 'blue')
        ->assertSet('showFreeColorModal', false);
});

it('rolling dice updates token inventory in the UI', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $blueColor = TokenColor::where('slug', 'blue')->first();
    $inventoryBefore = MatchTokenInventory::where('match_id', $this->match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $blueColor->id)
        ->first()
        ->quantity;

    $mock = Mockery::mock(DiceService::class);
    $mock->shouldReceive('roll')->once()->andReturn('blue');
    $mock->shouldReceive('applyRoll')->once()->andReturnUsing(function ($match, $participantTypeId, $color) {
        (new DiceService)->applyRoll($match, $participantTypeId, $color);
    });
    app()->instance(DiceService::class, $mock);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice');

    $inventoryAfter = MatchTokenInventory::where('match_id', $this->match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $blueColor->id)
        ->first()
        ->quantity;

    expect($inventoryAfter)->toBe($inventoryBefore + 1);
});

it('rolling dice disables further rolling this turn', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $mock = Mockery::mock(DiceService::class);
    $mock->shouldReceive('roll')->once()->andReturn('red');
    $mock->shouldReceive('applyRoll')->once()->andReturnUsing(function ($match, $participantTypeId, $color) {
        (new DiceService)->applyRoll($match, $participantTypeId, $color);
    });
    app()->instance(DiceService::class, $mock);

    $component = Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice');

    $this->match->refresh();
    expect($this->match->has_acted_this_turn)->toBeTrue();
});

it('free roll shows color selection modal', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $mock = Mockery::mock(DiceService::class);
    $mock->shouldReceive('roll')->once()->andReturn('free');
    app()->instance(DiceService::class, $mock);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice')
        ->assertSet('showFreeColorModal', true)
        ->assertSet('lastRollResult', 'free');
});

it('selecting a color after free roll applies the token correctly', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $greenColor = TokenColor::where('slug', 'green')->first();
    $inventoryBefore = MatchTokenInventory::where('match_id', $this->match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $greenColor->id)
        ->first()
        ->quantity;

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->set('showFreeColorModal', true)
        ->call('selectFreeColor', 'green')
        ->assertSet('showFreeColorModal', false);

    $inventoryAfter = MatchTokenInventory::where('match_id', $this->match->id)
        ->where('participant_type_id', $this->playerType->id)
        ->where('token_color_id', $greenColor->id)
        ->first()
        ->quantity;

    expect($inventoryAfter)->toBe($inventoryBefore + 1);
});

it('player cannot roll dice on AI turn', function () {
    $this->match->update([
        'current_participant_type_id' => $this->aiType->id,
        'has_acted_this_turn' => false,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice')
        ->assertSet('flashMessage', 'Não é a sua vez.')
        ->assertSet('flashType', 'error');
});

it('player cannot roll dice after already acting this turn', function () {
    $this->match->update([
        'current_participant_type_id' => $this->playerType->id,
        'has_acted_this_turn' => true,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::arena.match-board', ['match' => $this->match])
        ->call('rollDice')
        ->assertSet('flashMessage', 'Você já realizou uma ação neste turno.')
        ->assertSet('flashType', 'error');
});
