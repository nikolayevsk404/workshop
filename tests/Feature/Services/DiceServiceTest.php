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
use App\Services\DiceService;
use App\Services\MatchInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->diceService = new DiceService;
});

function createInProgressMatch(): GameMatch
{
    $user = User::factory()->create();
    $quotationCardIds = QuotationCard::query()->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    $service = new MatchInitializationService;

    return $service->createMatch($user, $quotationCardIds, $tier->id);
}

it('roll() returns a valid color slug or free', function () {
    $validResults = ['red', 'green', 'white', 'yellow', 'blue', 'free'];

    for ($i = 0; $i < 100; $i++) {
        $result = $this->diceService->roll();
        expect($validResults)->toContain($result);
    }
});

it('applyRoll() increases token quantity by 1 for the correct color', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();

    $match->update([
        'current_participant_type_id' => $playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $blueColor = TokenColor::where('slug', 'blue')->first();
    $inventoryBefore = MatchTokenInventory::where('match_id', $match->id)
        ->where('participant_type_id', $playerType->id)
        ->where('token_color_id', $blueColor->id)
        ->first();

    $quantityBefore = $inventoryBefore->quantity;

    $this->diceService->applyRoll($match, $playerType->id, 'blue');

    $inventoryBefore->refresh();
    expect($inventoryBefore->quantity)->toBe($quantityBefore + 1);
});

it('applyRoll() creates a turn record with correct action type and data', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();

    $match->update([
        'current_participant_type_id' => $playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $this->diceService->applyRoll($match, $playerType->id, 'red');

    $rollDiceAction = TurnActionType::where('slug', 'roll_dice')->first();

    $turn = MatchTurn::where('match_id', $match->id)->latest('id')->first();

    expect($turn)->not->toBeNull()
        ->and($turn->turn_action_type_id)->toBe($rollDiceAction->id)
        ->and($turn->participant_type_id)->toBe($playerType->id)
        ->and($turn->action_data['color'])->toBe('red');
});

it('applyRoll() sets has_acted_this_turn to true', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();

    $match->update([
        'current_participant_type_id' => $playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $this->diceService->applyRoll($match, $playerType->id, 'green');

    $match->refresh();
    expect($match->has_acted_this_turn)->toBeTrue();
});

it('applyRoll() fails if participant has already acted this turn', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();

    $match->update([
        'current_participant_type_id' => $playerType->id,
        'has_acted_this_turn' => true,
    ]);

    $this->diceService->applyRoll($match, $playerType->id, 'blue');
})->throws(InvalidArgumentException::class, 'O participante já realizou uma ação neste turno.');

it('applyRoll() fails if it is not the participant turn', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();
    $aiType = ParticipantType::where('slug', 'ai')->first();

    $match->update([
        'current_participant_type_id' => $aiType->id,
        'has_acted_this_turn' => false,
    ]);

    $this->diceService->applyRoll($match, $playerType->id, 'red');
})->throws(InvalidArgumentException::class, 'Não é a vez deste participante.');

it('rolling free requires a separate color choice before applying', function () {
    $match = createInProgressMatch();
    $playerType = ParticipantType::where('slug', 'player')->first();

    $match->update([
        'current_participant_type_id' => $playerType->id,
        'has_acted_this_turn' => false,
    ]);

    $this->diceService->applyRoll($match, $playerType->id, 'free');
})->throws(InvalidArgumentException::class, 'Cor inválida para aplicar o dado.');
