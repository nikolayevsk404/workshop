<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchStatus;
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

it('creates a match and redirects to game board on start', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->call('toggleQuotationCard', $cards[1])
        ->call('selectTier', $tier->id)
        ->call('startMatch');

    $match = GameMatch::where('user_id', $user->id)->first();
    expect($match)->not->toBeNull();

    $component->assertRedirect(route('arena.match.show', $match));
});

it('prevents starting a new match while another is in progress', function () {
    $user = User::factory()->create();
    $cards = QuotationCard::orderBy('number')->limit(2)->pluck('id')->toArray();
    $tier = DifficultyTier::first();

    $component = Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->call('toggleQuotationCard', $cards[0])
        ->call('toggleQuotationCard', $cards[1])
        ->call('selectTier', $tier->id);

    $inProgressStatus = MatchStatus::inProgress()->first();
    $existingMatch = GameMatch::factory()->create([
        'user_id' => $user->id,
        'match_status_id' => $inProgressStatus->id,
    ]);

    $component->call('startMatch')
        ->assertRedirect(route('arena.match.show', $existingMatch));

    expect(GameMatch::where('user_id', $user->id)->count())->toBe(1);
});

it('redirects to existing match if one is in progress on page load', function () {
    $user = User::factory()->create();
    $inProgressStatus = MatchStatus::inProgress()->first();

    $existingMatch = GameMatch::factory()->create([
        'user_id' => $user->id,
        'match_status_id' => $inProgressStatus->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::arena.match-setup')
        ->assertRedirect(route('arena.match.show', $existingMatch));
});
