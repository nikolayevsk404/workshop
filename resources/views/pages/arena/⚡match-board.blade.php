<?php

use App\Models\GameMatch;
use App\Models\MatchStatus;
use App\Models\ParticipantType;
use App\Services\AiOpponentService;
use App\Services\CardPurchaseService;
use App\Services\DiceService;
use App\Services\MatchFinalizationService;
use App\Services\TokenLimitService;
use App\Services\TradeService;
use App\Services\TurnService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::app')]
#[\Livewire\Attributes\Title('Partida')]
class extends Component {
    public GameMatch $match;

    public ?string $lastRollResult = null;

    public bool $showFreeColorModal = false;

    public ?string $flashMessage = null;

    public ?string $flashType = null;

    public ?int $lastPointsScored = null;

    public bool $aiThinking = false;

    public function mount(GameMatch $match): void
    {
        abort_unless($match->user_id === auth()->id(), 403);

        $completedStatus = MatchStatus::completed()->first();
        if ($completedStatus && $match->match_status_id === $completedStatus->id) {
            $this->redirect(route('arena.match.results', $match), navigate: true);
            return;
        }

        $this->loadMatch($match);

        if (!$this->isPlayerTurn) {
            $this->aiThinking = true;
            $this->dispatch('init-ai-turn');
        }
    }

    public function rollDice(DiceService $diceService): void
    {
        $this->resetFlash();
        $playerType = ParticipantType::where('slug', 'player')->first();

        if ($this->match->current_participant_type_id !== $playerType->id) {
            $this->setFlash('Não é a sua vez.', 'error');
            return;
        }

        if ($this->match->has_acted_this_turn) {
            $this->setFlash('Você já realizou uma ação neste turno.', 'error');
            return;
        }

        $result = $diceService->roll();
        $this->lastRollResult = $result;

        if ($result === 'free') {
            $this->showFreeColorModal = true;
            return;
        }

        try {
            $diceService->applyRoll($this->match, $playerType->id, $result);
            $this->refreshMatch();
        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    public function selectFreeColor(string $colorSlug, DiceService $diceService): void
    {
        $this->showFreeColorModal = false;
        $playerType = ParticipantType::where('slug', 'player')->first();

        try {
            $diceService->applyRoll($this->match, $playerType->id, $colorSlug);
            $this->lastRollResult = $colorSlug;
            $this->refreshMatch();
        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    public function executeTrade(int $quotationCardTradeId, string $direction, TradeService $tradeService): void
    {
        $this->resetFlash();
        $playerType = ParticipantType::where('slug', 'player')->first();

        try {
            $tradeService->executeTrade($this->match, $playerType->id, $quotationCardTradeId, $direction);
            $this->refreshMatch();
        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    public function purchaseCard(int $matchCompartmentCardId, CardPurchaseService $purchaseService): void
    {
        $this->resetFlash();
        $playerType = ParticipantType::where('slug', 'player')->first();

        try {
            $points = $purchaseService->purchaseCard($this->match, $playerType->id, $matchCompartmentCardId);
            $this->lastPointsScored = $points;
            $this->setFlash("+{$points} pontos!", 'success');
            $this->refreshMatch();

            $this->match->refresh();
            if ($this->match->compartments_emptied >= 2) {
                app(MatchFinalizationService::class)->finalize($this->match);
                $this->redirect(route('arena.match.results', $this->match), navigate: true);

                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    public function returnTokens(array $tokensToReturn, TokenLimitService $tokenLimitService): void
    {
        $this->resetFlash();
        $playerType = ParticipantType::where('slug', 'player')->first();

        try {
            $tokenLimitService->returnTokens($this->match, $playerType->id, $tokensToReturn);
            $this->refreshMatch();
        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    public function endTurn(TurnService $turnService): void
    {
        $this->resetFlash();

        try {
            $turnService->endTurn($this->match);

            $this->match->refresh();

            $completedStatus = MatchStatus::completed()->first();
            if ($completedStatus && $this->match->match_status_id === $completedStatus->id) {
                $this->redirect(route('arena.match.results', $this->match), navigate: true);

                return;
            }

            $this->aiThinking = true;

            $this->dispatch('init-ai-turn');

        } catch (\InvalidArgumentException $e) {
            $this->setFlash($e->getMessage(), 'error');
        }
    }

    #[Computed]
    public function playerInventories()
    {
        $playerType = ParticipantType::where('slug', 'player')->first();

        return $this->match->tokenInventories
            ->where('participant_type_id', $playerType?->id)
            ->sortBy(fn($inv) => array_search($inv->tokenColor->slug, ['red', 'green', 'white', 'yellow', 'blue']));
    }

    #[Computed]
    public function aiInventories()
    {
        $aiType = ParticipantType::where('slug', 'ai')->first();

        return $this->match->tokenInventories
            ->where('participant_type_id', $aiType?->id)
            ->sortBy(fn($inv) => array_search($inv->tokenColor->slug, ['red', 'green', 'white', 'yellow', 'blue']));
    }

    #[Computed]
    public function totalTokens(): int
    {
        return $this->playerInventories->sum('quantity');
    }

    #[Computed]
    public function needsTokenReturn(): bool
    {
        return $this->totalTokens > 10;
    }

    #[Computed]
    public function isPlayerTurn(): bool
    {
        $playerType = ParticipantType::where('slug', 'player')->first();

        return $this->match->current_participant_type_id === $playerType?->id;
    }

    #[Computed]
    public function canRollOrTrade(): bool
    {
        return $this->isPlayerTurn && !$this->match->has_acted_this_turn;
    }

    #[Computed]
    public function canPurchase(): bool
    {
        return $this->isPlayerTurn && $this->match->has_acted_this_turn;
    }

    #[Computed]
    public function canEndTurn(): bool
    {
        return $this->isPlayerTurn && $this->match->has_acted_this_turn && !$this->needsTokenReturn;
    }

    #[Computed]
    public function currentTurnLabel(): string
    {
        $participant = $this->match->currentParticipantType;

        return match ($participant?->slug) {
            'player' => 'Sua Vez',
            'ai' => 'Vez da IA',
            default => 'Aguardando...',
        };
    }

    #[Computed]
    public function matchStats(): array
    {
        $turns = $this->match->turns;

        return [
            'total_actions' => $turns->count(),
            'dice_rolls' => $turns->filter(fn($t) => $t->turnActionType?->slug === 'roll_dice')->count(),
            'trades' => $turns->filter(fn($t) => $t->turnActionType?->slug === 'trade')->count(),
        ];
    }

    private function refreshMatch(): void
    {
        $this->loadMatch($this->match->fresh());
    }

    private function loadMatch(GameMatch $match): void
    {
        $this->match = $match->load([
            'user',
            'difficultyTier',
            'matchStatus',
            'currentParticipantType',
            'quotationCards.trades.leftItems.tokenColor',
            'quotationCards.trades.rightItems.tokenColor',
            'compartments.cards.card.tokens.tokenColor',
            'tokenInventories.tokenColor',
            'tokenInventories.participantType',
            'turns.participantType',
            'turns.turnActionType',
        ]);
    }

    private function setFlash(string $message, string $type): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
    }

    private function resetFlash(): void
    {
        $this->flashMessage = null;
        $this->flashType = null;
        $this->lastPointsScored = null;
    }

    public bool $showAbandonConfirm = false;

    public function confirmAbandon(): void
    {
        $this->showAbandonConfirm = true;
    }

    public function cancelAbandon(): void
    {
        $this->showAbandonConfirm = false;
    }

    public function abandonMatch(): void
    {
        $abandonedStatus = MatchStatus::abandoned()->first();

        $this->match->update([
            'match_status_id' => $abandonedStatus->id,
            'completed_at' => now(),
        ]);

        $this->redirect(route('arena.match-setup'), navigate: true);
    }

    #[On('init-ai-turn')]
    public function initAiTurn(AiOpponentService $aiOpponentService)
    {
        $result = $aiOpponentService->executeTurn($this->match);
        $this->aiThinking = false;
        $this->match->refresh();
    }

};
?>

<div class="flex h-[calc(100vh-8rem)] overflow-hidden -mx-6 -mb-8">
    {{-- Left Sidebar: Match Summary & History --}}
    <aside class="h-full w-80 flex flex-col p-6 border-r border-outline-variant/15 shrink-0 overflow-y-auto">
        <div class="space-y-6 flex-1">
            {{-- Match Stats --}}
            <div class="space-y-4">
                <label class="font-display text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold">Resumo
                    da Partida</label>
                <div class="grid grid-cols-1 gap-3">
                    <div class="bg-surface-container-low p-4 rounded-xl flex items-center justify-between hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary">data_usage</span>
                            <span class="text-sm font-medium text-on-surface-variant">Ações Totais</span>
                        </div>
                        <span class="text-lg font-black font-display text-primary">{{ str_pad($this->matchStats['total_actions'], 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="bg-surface-container-low p-4 rounded-xl flex items-center justify-between hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-secondary">casino</span>
                            <span class="text-sm font-medium text-on-surface-variant">Lançamentos</span>
                        </div>
                        <span class="text-lg font-black font-display text-secondary">{{ str_pad($this->matchStats['dice_rolls'], 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="bg-surface-container-low p-4 rounded-xl flex items-center justify-between hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-tertiary">swap_horiz</span>
                            <span class="text-sm font-medium text-on-surface-variant">Trocas Realizadas</span>
                        </div>
                        <span class="text-lg font-black font-display text-tertiary">{{ str_pad($this->matchStats['trades'], 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                </div>

                {{-- Scores --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-primary/10 p-3 rounded-xl border border-primary/20 text-center">
                        <span class="text-[10px] uppercase tracking-widest text-primary font-bold">Você</span>
                        <p class="text-2xl font-black font-display text-primary">{{ $this->match->player_score }}</p>
                    </div>
                    <div class="bg-tertiary/10 p-3 rounded-xl border border-tertiary/20 text-center">
                        <span class="text-[10px] uppercase tracking-widest text-tertiary font-bold">IA</span>
                        <p class="text-2xl font-black font-display text-tertiary">{{ $this->match->ai_score }}</p>
                    </div>
                </div>
            </div>

            {{-- Turn Indicator --}}
            <div class="p-4 rounded-xl border {{ $this->match->currentParticipantType?->slug === 'player' ? 'bg-primary/10 border-primary/20' : 'bg-tertiary/10 border-tertiary/20' }}">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined {{ $this->match->currentParticipantType?->slug === 'player' ? 'text-primary' : 'text-tertiary' }}">
                        {{ $this->match->currentParticipantType?->slug === 'player' ? 'person' : 'smart_toy' }}
                    </span>
                    <div>
                        <p class="text-xs font-bold text-on-surface">{{ $this->currentTurnLabel }}</p>
                        <p class="text-[10px] text-on-surface-variant">
                            Turno {{ $this->match->current_turn_number ?? 1 }}</p>
                    </div>
                </div>
            </div>

            {{-- History Log --}}
            <x-match-history-log :turns="$this->match->turns"/>

            {{-- Abandon Match --}}
            <button
                wire:click="confirmAbandon"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wide bg-danger/10 border border-danger/20 text-danger hover:bg-danger/20 transition-all mt-4"
            >
                <span class="material-symbols-outlined text-lg">flag</span>
                Abandonar Partida
            </button>
        </div>
    </aside>

    {{-- Main Content Area --}}
    <main class="flex-1 overflow-y-auto p-8 space-y-8">
        {{-- Action Bar --}}
        <div class="flex items-center gap-3 flex-wrap">
            <button
                wire:click="rollDice"
                @class([
                    'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold uppercase tracking-wide transition-all',
                    'bg-primary text-on-primary hover:brightness-110 active:scale-95' => $this->canRollOrTrade,
                    'bg-surface-variant/50 text-on-surface-variant/50 cursor-not-allowed' => !$this->canRollOrTrade,
                ])
                @if (!$this->canRollOrTrade) disabled @endif
            >
                <span class="material-symbols-outlined text-lg">casino</span>
                Lançar Dado
            </button>

            @if ($this->canEndTurn)
                <button
                    wire:click="endTurn"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold uppercase tracking-wide bg-secondary text-on-secondary hover:brightness-110 active:scale-95 transition-all"
                >
                    <span class="material-symbols-outlined text-lg">skip_next</span>
                    Encerrar Turno
                </button>
            @endif

            {{-- Flash Messages --}}
            @if ($flashMessage)
                <div @class([
                    'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold',
                    'bg-danger/10 border border-danger/20 text-danger' => $flashType === 'error',
                    'bg-secondary/10 border border-secondary/20 text-secondary' => $flashType === 'success',
                ])>
                    <span class="material-symbols-outlined text-lg">{{ $flashType === 'error' ? 'error' : 'check_circle' }}</span>
                    {{ $flashMessage }}
                </div>
            @endif
        </div>

        {{-- Token Inventories --}}
        <div class="space-y-4">
            <x-token-inventory :inventories="$this->playerInventories" label="Seu Inventário" />
            <x-token-inventory :inventories="$this->aiInventories" label="Inventário da IA" />
        </div>

        {{-- Card Compartments --}}
        <section class="space-y-4">
            <label class="font-display text-xs uppercase tracking-[0.3em] text-on-surface-variant font-bold">Compartimentos</label>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($this->match->compartments->sortBy('position') as $compartment)
                    <x-card-compartment
                            :compartment="$compartment"
                            :position="$compartment->position"
                            :canPurchase="$this->canPurchase"
                            :playerInventories="$this->playerInventories"
                    />
                @endforeach
            </div>
        </section>

        {{-- Active Quotation Cards --}}
        <section class="space-y-4">
            <label class="font-display text-xs uppercase tracking-[0.3em] text-on-surface-variant font-bold">Cotações
                Ativas (Mercado)</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($this->match->quotationCards as $quotationCard)
                    <x-quotation-card-display
                            :quotationCard="$quotationCard"
                            :playerInventories="$this->playerInventories"
                            :canTrade="$this->canRollOrTrade"
                    />
                @endforeach
            </div>
        </section>

        {{-- AI Thinking State --}}
        @if ($aiThinking)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div class="bg-surface-container p-8 rounded-3xl border border-outline-variant/10 max-w-sm w-full mx-4 text-center space-y-4">
                    <span class="material-symbols-outlined text-5xl text-tertiary animate-pulse">smart_toy</span>
                    <h2 class="text-xl font-black font-display text-on-surface uppercase">IA Pensando...</h2>
                    <p class="text-sm text-on-surface-variant">Aguarde enquanto a IA realiza sua jogada</p>
                </div>
            </div>
        @endif

        {{-- Token Return UI (shown only when over 10 tokens) --}}
        @if ($this->needsTokenReturn)
            <livewire:arena.token-return :match="$this->match" :key="'token-return-' . $this->match->id"/>
        @endif
    </main>

    {{-- Abandon Confirmation Modal --}}
    @if ($showAbandonConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-surface-container p-8 rounded-3xl border border-outline-variant/10 max-w-sm w-full mx-4 text-center space-y-6">
                <span class="material-symbols-outlined text-5xl text-danger">warning</span>
                <h2 class="text-xl font-black font-display text-on-surface uppercase">Abandonar Partida?</h2>
                <p class="text-sm text-on-surface-variant">Essa ação não pode ser desfeita. Você não receberá XP por esta partida.</p>
                <div class="flex gap-3">
                    <button
                        wire:click="cancelAbandon"
                        class="flex-1 px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wide bg-surface-container-low border border-outline-variant/20 text-on-surface hover:bg-surface-container-high transition-all"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="abandonMatch"
                        class="flex-1 px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wide bg-danger text-on-danger hover:brightness-110 transition-all"
                    >
                        Abandonar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Free Color Selection Modal --}}
    @if ($showFreeColorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div class="bg-surface-container p-8 rounded-3xl border border-outline-variant/10 max-w-md w-full mx-4 space-y-6">
                <div class="text-center">
                    <span class="material-symbols-outlined text-5xl text-secondary mb-2">stars</span>
                    <h2 class="text-2xl font-black font-display text-on-surface uppercase">Dado Livre!</h2>
                    <p class="text-sm text-on-surface-variant mt-2">Escolha a cor do token que deseja receber</p>
                </div>
                <div class="grid grid-cols-5 gap-3">
                    @foreach (['red' => 'Vermelho', 'green' => 'Verde', 'white' => 'Branco', 'yellow' => 'Amarelo', 'blue' => 'Azul'] as $slug => $label)
                        <button
                                wire:click="selectFreeColor('{{ $slug }}')"
                                class="flex flex-col items-center gap-2 p-3 rounded-xl border border-outline-variant/10 bg-surface-container-low hover:bg-surface-container transition-all hover:scale-105"
                        >
                            <x-token-dot :color="$slug" size="lg"/>
                            <span class="text-[10px] font-bold uppercase text-on-surface-variant">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
