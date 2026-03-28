<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'user_id',
        'difficulty_tier_id',
        'match_status_id',
        'match_result_type_id',
        'current_turn_number',
        'current_participant_type_id',
        'has_acted_this_turn',
        'player_score',
        'ai_score',
        'player_cards_purchased',
        'ai_cards_purchased',
        'compartments_emptied',
        'xp_earned',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'has_acted_this_turn' => 'boolean',
            'current_turn_number' => 'integer',
            'player_score' => 'integer',
            'ai_score' => 'integer',
            'player_cards_purchased' => 'integer',
            'ai_cards_purchased' => 'integer',
            'compartments_emptied' => 'integer',
            'xp_earned' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function difficultyTier(): BelongsTo
    {
        return $this->belongsTo(DifficultyTier::class);
    }

    public function matchStatus(): BelongsTo
    {
        return $this->belongsTo(MatchStatus::class);
    }

    public function matchResultType(): BelongsTo
    {
        return $this->belongsTo(MatchResultType::class);
    }

    public function currentParticipantType(): BelongsTo
    {
        return $this->belongsTo(ParticipantType::class, 'current_participant_type_id');
    }

    public function quotationCards(): BelongsToMany
    {
        return $this->belongsToMany(QuotationCard::class, 'match_quotation_cards', 'match_id')
            ->withTimestamps();
    }

    public function compartments(): HasMany
    {
        return $this->hasMany(MatchCompartment::class, 'match_id');
    }

    public function tokenInventories(): HasMany
    {
        return $this->hasMany(MatchTokenInventory::class, 'match_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(MatchTurn::class, 'match_id');
    }
}
