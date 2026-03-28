<?php

use App\Models\DifficultyTier;
use App\Models\QuotationCard;
use App\Models\User;
use Database\Seeders\CardSeeder;
use Database\Seeders\DifficultyTierSeeder;
use Database\Seeders\MatchStatusSeeder;
use Database\Seeders\ParticipantTypeSeeder;
use Database\Seeders\PlayerRankSeeder;
use Database\Seeders\QuotationCardSeeder;
use Database\Seeders\TokenColorSeeder;
use Database\Seeders\TradeSideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
});

it('renders match setup page for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/arena/new-match')
        ->assertStatus(200);
});

it('displays all 10 quotation cards', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup');

    $quotationCards = QuotationCard::all();
    expect($quotationCards)->toHaveCount(10);

    foreach ($quotationCards as $card) {
        $component->assertSee($card->name);
    }
});

it('displays all 3 difficulty tiers', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup');

    $tiers = DifficultyTier::all();
    expect($tiers)->toHaveCount(3);

    foreach ($tiers as $tier) {
        $component->assertSee($tier->name);
    }
});

it('allows player to select exactly 2 quotation cards', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();

    Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->assertSet('selectedQuotationCards', [$cards[0]])
        ->call('toggleQuotationCard', $cards[1])
        ->assertSet('selectedQuotationCards', [$cards[0], $cards[1]]);
});

it('deselects first card when selecting a 3rd quotation card', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(3)->pluck('id')->toArray();

    Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->call('toggleQuotationCard', $cards[1])
        ->call('toggleQuotationCard', $cards[2])
        ->assertSet('selectedQuotationCards', [$cards[1], $cards[2]]);
});

it('allows player to select 1 difficulty tier', function () {
    $user = User::factory()->create();
    $tier = DifficultyTier::first();

    Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('selectTier', $tier->id)
        ->assertSet('selectedTierId', $tier->id);
});

it('disables start match with 0 quotation cards selected', function () {
    $user = User::factory()->create();
    $tier = DifficultyTier::first();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('selectTier', $tier->id);

    expect($component->get('canStart'))->toBeFalse();
});

it('disables start match with 1 quotation card selected', function () {
    $user = User::factory()->create();
    $card = QuotationCard::first();
    $tier = DifficultyTier::first();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $card->id)
        ->call('selectTier', $tier->id);

    expect($component->get('canStart'))->toBeFalse();
});

it('disables start match with no difficulty tier selected', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->call('toggleQuotationCard', $cards[1]);

    expect($component->get('canStart'))->toBeFalse();
});

it('enables start match with 2 quotation cards and 1 tier selected', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->call('toggleQuotationCard', $cards[1])
        ->call('selectTier', $tier->id);

    expect($component->get('canStart'))->toBeTrue();
});
