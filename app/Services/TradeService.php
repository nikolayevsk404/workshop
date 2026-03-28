<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\QuotationCardTrade;
use App\Models\TurnActionType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TradeService
{
    public function executeTrade(GameMatch $match, int $participantTypeId, int $quotationCardTradeId, string $direction): void
    {
        if ($match->current_participant_type_id !== $participantTypeId) {
            throw new InvalidArgumentException('Não é a vez deste participante.');
        }

        if ($match->has_acted_this_turn) {
            throw new InvalidArgumentException('O participante já realizou uma ação neste turno.');
        }

        $trade = QuotationCardTrade::with(['items.tokenColor', 'items.tradeSide'])->find($quotationCardTradeId);
        if (! $trade) {
            throw new InvalidArgumentException('Troca inválida.');
        }

        $matchQuotationCardIds = $match->quotationCards()->pluck('quotation_cards.id');
        if (! $matchQuotationCardIds->contains($trade->quotation_card_id)) {
            throw new InvalidArgumentException('Esta cotação não está ativa nesta partida.');
        }

        if (! in_array($direction, ['left_to_right', 'right_to_left'])) {
            throw new InvalidArgumentException('Direção de troca inválida.');
        }

        $giveSide = $direction === 'left_to_right' ? 'left' : 'right';
        $receiveSide = $direction === 'left_to_right' ? 'right' : 'left';

        $giveItems = $trade->items->filter(fn ($item) => $item->tradeSide->slug === $giveSide);
        $receiveItems = $trade->items->filter(fn ($item) => $item->tradeSide->slug === $receiveSide);

        foreach ($giveItems as $item) {
            $inventory = MatchTokenInventory::where('match_id', $match->id)
                ->where('participant_type_id', $participantTypeId)
                ->where('token_color_id', $item->token_color_id)
                ->first();

            if (! $inventory || $inventory->quantity < $item->quantity) {
                throw new InvalidArgumentException('Tokens insuficientes para realizar esta troca.');
            }
        }

        DB::transaction(function () use ($match, $participantTypeId, $giveItems, $receiveItems, $trade, $direction) {
            foreach ($giveItems as $item) {
                MatchTokenInventory::where('match_id', $match->id)
                    ->where('participant_type_id', $participantTypeId)
                    ->where('token_color_id', $item->token_color_id)
                    ->decrement('quantity', $item->quantity);
            }

            foreach ($receiveItems as $item) {
                MatchTokenInventory::where('match_id', $match->id)
                    ->where('participant_type_id', $participantTypeId)
                    ->where('token_color_id', $item->token_color_id)
                    ->increment('quantity', $item->quantity);
            }

            $tradeAction = TurnActionType::where('slug', 'trade')->firstOrFail();
            $participantType = ParticipantType::findOrFail($participantTypeId);

            MatchTurn::create([
                'match_id' => $match->id,
                'turn_number' => $match->current_turn_number,
                'participant_type_id' => $participantTypeId,
                'turn_action_type_id' => $tradeAction->id,
                'action_data' => [
                    'quotation_card_trade_id' => $trade->id,
                    'direction' => $direction,
                    'participant' => $participantType->slug,
                ],
            ]);

            $match->update(['has_acted_this_turn' => true]);
        });
    }
}
