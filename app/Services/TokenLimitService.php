<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\TokenColor;
use App\Models\TurnActionType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TokenLimitService
{
    private const TOKEN_LIMIT = 10;

    public function isOverLimit(GameMatch $match, int $participantTypeId): bool
    {
        return $this->getTotalTokens($match, $participantTypeId) > self::TOKEN_LIMIT;
    }

    public function getExcessCount(GameMatch $match, int $participantTypeId): int
    {
        return max(0, $this->getTotalTokens($match, $participantTypeId) - self::TOKEN_LIMIT);
    }

    public function returnTokens(GameMatch $match, int $participantTypeId, array $tokensToReturn): void
    {
        $totalToReturn = array_sum($tokensToReturn);
        $excess = $this->getExcessCount($match, $participantTypeId);

        if ($totalToReturn !== $excess) {
            throw new InvalidArgumentException("É necessário devolver exatamente {$excess} token(s).");
        }

        foreach ($tokensToReturn as $colorSlug => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $tokenColor = TokenColor::where('slug', $colorSlug)->first();
            if (! $tokenColor) {
                throw new InvalidArgumentException("Cor inválida: {$colorSlug}.");
            }

            $inventory = MatchTokenInventory::where('match_id', $match->id)
                ->where('participant_type_id', $participantTypeId)
                ->where('token_color_id', $tokenColor->id)
                ->first();

            if (! $inventory || $inventory->quantity < $quantity) {
                throw new InvalidArgumentException("Tokens insuficientes da cor {$colorSlug}.");
            }
        }

        DB::transaction(function () use ($match, $participantTypeId, $tokensToReturn) {
            foreach ($tokensToReturn as $colorSlug => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $tokenColor = TokenColor::where('slug', $colorSlug)->first();

                MatchTokenInventory::where('match_id', $match->id)
                    ->where('participant_type_id', $participantTypeId)
                    ->where('token_color_id', $tokenColor->id)
                    ->decrement('quantity', $quantity);
            }

            $returnAction = TurnActionType::where('slug', 'return_tokens')->firstOrFail();
            $participantType = ParticipantType::findOrFail($participantTypeId);

            MatchTurn::create([
                'match_id' => $match->id,
                'turn_number' => $match->current_turn_number,
                'participant_type_id' => $participantTypeId,
                'turn_action_type_id' => $returnAction->id,
                'action_data' => [
                    'tokens_returned' => $tokensToReturn,
                    'participant' => $participantType->slug,
                ],
            ]);
        });
    }

    private function getTotalTokens(GameMatch $match, int $participantTypeId): int
    {
        return MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->sum('quantity');
    }
}
