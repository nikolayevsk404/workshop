<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\MatchTurn;
use App\Models\ParticipantType;
use App\Models\TokenColor;
use App\Models\TurnActionType;
use InvalidArgumentException;

class DiceService
{
    private const DICE_FACES = ['red', 'green', 'white', 'yellow', 'blue', 'free'];

    public function roll(): string
    {
        return self::DICE_FACES[array_rand(self::DICE_FACES)];
    }

    public function applyRoll(GameMatch $match, int $participantTypeId, string $colorSlug): void
    {
        $participantType = ParticipantType::findOrFail($participantTypeId);

        if ($match->current_participant_type_id !== $participantTypeId) {
            throw new InvalidArgumentException('Não é a vez deste participante.');
        }

        if ($match->has_acted_this_turn) {
            throw new InvalidArgumentException('O participante já realizou uma ação neste turno.');
        }

        $validColors = ['red', 'green', 'white', 'yellow', 'blue'];
        if (! in_array($colorSlug, $validColors)) {
            throw new InvalidArgumentException('Cor inválida para aplicar o dado.');
        }

        $tokenColor = TokenColor::where('slug', $colorSlug)->firstOrFail();

        $inventory = MatchTokenInventory::where('match_id', $match->id)
            ->where('participant_type_id', $participantTypeId)
            ->where('token_color_id', $tokenColor->id)
            ->firstOrFail();

        $inventory->increment('quantity');

        $rollDiceAction = TurnActionType::where('slug', 'roll_dice')->firstOrFail();

        MatchTurn::create([
            'match_id' => $match->id,
            'turn_number' => $match->current_turn_number,
            'participant_type_id' => $participantTypeId,
            'turn_action_type_id' => $rollDiceAction->id,
            'action_data' => [
                'color' => $colorSlug,
                'participant' => $participantType->slug,
            ],
        ]);

        $match->update(['has_acted_this_turn' => true]);
    }
}
