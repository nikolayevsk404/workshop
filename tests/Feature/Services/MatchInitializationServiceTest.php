<?php

use App\Models\DifficultyTier;
use App\Models\MatchCompartmentCard;
use App\Models\MatchStatus;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\User;
use App\Services\MatchInitializationService;
use Database\Seeders\CardSeeder;
use Database\Seeders\DifficultyTierSeeder;
use Database\Seeders\MatchStatusSeeder;
use Database\Seeders\ParticipantTypeSeeder;
use Database\Seeders\PlayerRankSeeder;
use Database\Seeders\QuotationCardSeeder;
use Database\Seeders\TokenColorSeeder;
use Database\Seeders\TradeSideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        PlayerRankSeeder::class,
        TokenColorSeeder::class,
        TradeSideSeeder::class,
        DifficultyTierSeeder::class,
        MatchStatusSeeder::class,
        ParticipantTypeSeeder::class,
        QuotationCardSeeder::class,
        CardSeeder::class,
    ]);

    $this->service = app(MatchInitializationService::class);
    $this->user = User::factory()->create();
    $this->cardIds = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();
    $this->tierId = DifficultyTier::first()->id;
});

it('creates match with correct status (in_progress)', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    $inProgressStatus = MatchStatus::inProgress()->first();
    expect($match->match_status_id)->toBe($inProgressStatus->id);
});

it('attaches exactly 2 quotation cards to the match', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    expect($match->quotationCards)->toHaveCount(2);
    expect($match->quotationCards->pluck('id')->sort()->values()->toArray())
        ->toBe(collect($this->cardIds)->sort()->values()->toArray());
});

it('creates exactly 4 compartments', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    expect($match->compartments)->toHaveCount(4);
});

it('puts exactly 5 cards in each compartment', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    foreach ($match->compartments as $compartment) {
        expect($compartment->cards)->toHaveCount(5);
    }
});

it('distributes 20 unique cards with no duplicates', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    $allCardIds = MatchCompartmentCard::whereIn(
        'match_compartment_id',
        $match->compartments->pluck('id')
    )->pluck('card_id');

    expect($allCardIds)->toHaveCount(20);
    expect($allCardIds->unique())->toHaveCount(20);
});

it('assigns card positions 1 through 5 within compartments', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    foreach ($match->compartments as $compartment) {
        $positions = $compartment->cards->pluck('position')->sort()->values()->toArray();
        expect($positions)->toBe([1, 2, 3, 4, 5]);
    }
});

it('creates 10 token inventory rows (5 colors x 2 participants)', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    $inventories = MatchTokenInventory::where('match_id', $match->id)->get();
    expect($inventories)->toHaveCount(10);
});

it('initializes all token inventories at quantity 0', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    $inventories = MatchTokenInventory::where('match_id', $match->id)->get();

    foreach ($inventories as $inventory) {
        expect($inventory->quantity)->toBe(0);
    }
});

it('randomly assigns first turn to player or AI', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    $participantTypeIds = ParticipantType::pluck('id')->toArray();
    expect($match->current_participant_type_id)->toBeIn($participantTypeIds);
    expect($match->current_turn_number)->toBe(1);
});

it('sets started_at timestamp', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    expect($match->started_at)->not->toBeNull();
});

it('associates match with the correct user', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    expect($match->user_id)->toBe($this->user->id);
});

it('associates match with the correct difficulty tier', function () {
    $match = $this->service->createMatch($this->user, $this->cardIds, $this->tierId);

    expect($match->difficulty_tier_id)->toBe($this->tierId);
});

it('rejects fewer than 2 quotation card IDs', function () {
    $this->service->createMatch($this->user, [$this->cardIds[0]], $this->tierId);
})->throws(InvalidArgumentException::class, 'Exactly 2 quotation card IDs are required.');

it('rejects more than 2 quotation card IDs', function () {
    $threeCards = QuotationCard::orderBy('number')->limit(3)->pluck('id')->toArray();

    $this->service->createMatch($this->user, $threeCards, $this->tierId);
})->throws(InvalidArgumentException::class, 'Exactly 2 quotation card IDs are required.');

it('rejects invalid difficulty tier ID', function () {
    $this->service->createMatch($this->user, $this->cardIds, 99999);
})->throws(InvalidArgumentException::class, 'Invalid difficulty tier ID provided.');
