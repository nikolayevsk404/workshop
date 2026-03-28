<?php

namespace App\Services;

use App\Models\Card;
use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchCompartment;
use App\Models\MatchCompartmentCard;
use App\Models\MatchStatus;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Models\QuotationCard;
use App\Models\TokenColor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchInitializationService
{
    public function createMatch(User $user, array $quotationCardIds, int $difficultyTierId): GameMatch
    {
        if (count($quotationCardIds) !== 2) {
            throw new InvalidArgumentException('Exactly 2 quotation card IDs are required.');
        }

        $validCards = QuotationCard::whereIn('id', $quotationCardIds)->count();
        if ($validCards !== 2) {
            throw new InvalidArgumentException('Invalid quotation card IDs provided.');
        }

        $tier = DifficultyTier::find($difficultyTierId);
        if (! $tier) {
            throw new InvalidArgumentException('Invalid difficulty tier ID provided.');
        }

        return DB::transaction(function () use ($user, $quotationCardIds, $tier) {
            $pendingStatus = MatchStatus::pending()->firstOrFail();
            $inProgressStatus = MatchStatus::inProgress()->firstOrFail();

            $match = GameMatch::create([
                'user_id' => $user->id,
                'difficulty_tier_id' => $tier->id,
                'match_status_id' => $pendingStatus->id,
            ]);

            $match->quotationCards()->attach($quotationCardIds);

            $this->distributeCards($match);

            $this->initializeTokenInventories($match);

            $this->assignFirstTurn($match);

            $match->update([
                'match_status_id' => $inProgressStatus->id,
                'started_at' => now(),
            ]);

            return $match->fresh();
        });
    }

    private function distributeCards(GameMatch $match): void
    {
        $cards = Card::all()->shuffle()->take(20);

        $chunks = $cards->chunk(5);

        foreach ($chunks as $position => $cardChunk) {
            $compartment = MatchCompartment::create([
                'match_id' => $match->id,
                'position' => $position + 1,
                'is_star_bonus_active' => false,
            ]);

            $cardPosition = 1;
            foreach ($cardChunk as $card) {
                MatchCompartmentCard::create([
                    'match_compartment_id' => $compartment->id,
                    'card_id' => $card->id,
                    'position' => $cardPosition,
                    'is_purchased' => false,
                ]);
                $cardPosition++;
            }
        }
    }

    private function initializeTokenInventories(GameMatch $match): void
    {
        $participantTypes = ParticipantType::all();
        $tokenColors = TokenColor::all();

        foreach ($participantTypes as $participantType) {
            foreach ($tokenColors as $tokenColor) {
                MatchTokenInventory::create([
                    'match_id' => $match->id,
                    'participant_type_id' => $participantType->id,
                    'token_color_id' => $tokenColor->id,
                    'quantity' => 0,
                ]);
            }
        }
    }

    private function assignFirstTurn(GameMatch $match): void
    {
        $participantTypes = ParticipantType::all();
        $firstTurn = $participantTypes->random();

        $match->update([
            'current_turn_number' => 1,
            'current_participant_type_id' => $firstTurn->id,
            'has_acted_this_turn' => false,
        ]);
    }
}
