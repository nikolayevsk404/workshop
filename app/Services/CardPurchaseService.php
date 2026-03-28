<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchCompartmentCard;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\TurnActionType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CardPurchaseService
{
    public function __construct(private ScoringService $scoringService) {}

    public function purchaseCard(GameMatch $match, int $participantTypeId, int $matchCompartmentCardId): int
    {
        if ($match->current_participant_type_id !== $participantTypeId) {
            throw new InvalidArgumentException('Não é a vez deste participante.');
        }

        if (! $match->has_acted_this_turn) {
            throw new InvalidArgumentException('É necessário lançar o dado ou trocar tokens antes de comprar uma carta.');
        }

        $compartmentCard = MatchCompartmentCard::with(['compartment', 'card.tokens.tokenColor'])->find($matchCompartmentCardId);
        if (! $compartmentCard) {
            throw new InvalidArgumentException('Carta não encontrada.');
        }

        if ($compartmentCard->compartment->match_id !== $match->id) {
            throw new InvalidArgumentException('Esta carta não pertence a esta partida.');
        }

        if ($compartmentCard->is_purchased) {
            throw new InvalidArgumentException('Esta carta já foi comprada.');
        }

        $faceUpCard = $compartmentCard->compartment->faceUpCard();
        if (! $faceUpCard || $faceUpCard->id !== $compartmentCard->id) {
            throw new InvalidArgumentException('Esta carta não está virada para cima.');
        }

        $cardTokens = $compartmentCard->card->tokens;
        foreach ($cardTokens as $cardToken) {
            $inventory = MatchTokenInventory::where('match_id', $match->id)
                ->where('participant_type_id', $participantTypeId)
                ->where('token_color_id', $cardToken->token_color_id)
                ->first();

            if (! $inventory || $inventory->quantity < $cardToken->quantity) {
                throw new InvalidArgumentException('Tokens insuficientes para comprar esta carta.');
            }
        }

        return DB::transaction(function () use ($match, $participantTypeId, $compartmentCard, $cardTokens) {
            foreach ($cardTokens as $cardToken) {
                MatchTokenInventory::where('match_id', $match->id)
                    ->where('participant_type_id', $participantTypeId)
                    ->where('token_color_id', $cardToken->token_color_id)
                    ->decrement('quantity', $cardToken->quantity);
            }

            $remainingTokens = MatchTokenInventory::where('match_id', $match->id)
                ->where('participant_type_id', $participantTypeId)
                ->sum('quantity');

            $starBonuses = $this->scoringService->getActiveStarBonuses($match);
            $points = $this->scoringService->calculatePoints(
                $remainingTokens,
                $compartmentCard->card->star_count,
                $starBonuses
            );

            $compartmentCard->update([
                'is_purchased' => true,
                'purchased_by_participant_type_id' => $participantTypeId,
                'points_scored' => $points,
                'purchased_at' => now(),
            ]);

            $participantType = ParticipantType::findOrFail($participantTypeId);
            $scoreField = $participantType->slug === 'player' ? 'player_score' : 'ai_score';
            $cardsField = $participantType->slug === 'player' ? 'player_cards_purchased' : 'ai_cards_purchased';

            $match->increment($scoreField, $points);
            $match->increment($cardsField);

            $compartment = $compartmentCard->compartment;
            $remainingInCompartment = $compartment->cards()->where('is_purchased', false)->count();

            if ($remainingInCompartment === 0) {
                $compartment->update(['is_star_bonus_active' => true]);
                $match->increment('compartments_emptied');
            }

            $purchaseAction = TurnActionType::where('slug', 'purchase_card')->firstOrFail();

            MatchTurn::create([
                'match_id' => $match->id,
                'turn_number' => $match->current_turn_number,
                'participant_type_id' => $participantTypeId,
                'turn_action_type_id' => $purchaseAction->id,
                'action_data' => [
                    'card_id' => $compartmentCard->card_id,
                    'card_number' => $compartmentCard->card->number,
                    'compartment_position' => $compartment->position,
                    'points_scored' => $points,
                    'participant' => $participantType->slug,
                ],
            ]);

            return $points;
        });
    }
}
