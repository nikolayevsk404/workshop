<?php

use App\Models\GameMatch;
use App\Models\MatchStatus;
use App\Models\ParticipantType;
use App\Services\CardPurchaseService;
use App\Services\DiceService;
use App\Services\TokenLimitService;
use App\Services\TradeService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] #[\Livewire\Attributes\Title('Partida')] class extends Component
{
    public GameMatch $match;

    public ?string $lastRollResult = null;

    public bool $showFreeColorModal = false;

    public ?string $flashMessage = null;

    public ?string $flashType = null;

    public ?int $lastPointsScored = null;

    public function mount(GameMatch $match): void
    {
        abort_unless($match->user_id === auth()->id(), 403);

        $completedStatus = MatchStatus::completed()->first();
        if ($completedStatus && $match->match_status_id === $completedStatus->id) {
            $this->redirect(route('arena.match.results', $match), navigate: true);
            return;
        }

        $this->loadMatch($match);
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

    #[Computed]
    public function playerInventories()
    {
        $playerType = ParticipantType::where('slug', 'player')->first();

        return $this->match->tokenInventories
            ->where('participant_type_id', $playerType?->id)
            ->sortBy(fn ($inv) => array_search($inv->tokenColor->slug, ['red', 'green', 'white', 'yellow', 'blue']));
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
        return $this->isPlayerTurn && ! $this->match->has_acted_this_turn;
    }

    #[Computed]
    public function canPurchase(): bool
    {
        return $this->isPlayerTurn && $this->match->has_acted_this_turn;
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
            'dice_rolls' => $turns->filter(fn ($t) => $t->turnActionType?->slug === 'roll_dice')->count(),
            'trades' => $turns->filter(fn ($t) => $t->turnActionType?->slug === 'trade')->count(),
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

};
?>

<div class="flex h-[calc(100vh-8rem)] overflow-hidden -mx-6 -mb-8">
    {{-- Left Sidebar: Match Summary & History --}}
    <aside class="h-full w-80 flex flex-col p-6 border-r border-outline-variant/15 shrink-0 overflow-y-auto">
        <div class="space-y-6 flex-1">
            {{-- Match Stats --}}
            <div class="space-y-4">
                <label class="font-display text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold">Resumo da Partida</label>
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
                        <p class="text-[10px] text-on-surface-variant">Turno {{ $this->match->current_turn_number ?? 1 }}</p>
                    </div>
                </div>
            </div>

            {{-- History Log --}}
            <x-match-history-log :turns="$this->match->turns" />
        </div>
    </aside>

    {{-- Main Content Area --}}
    <main class="flex-1 overflow-y-auto p-8 space-y-8">
        {{-- Flash Messages --}}
        @if ($flashMessage)
            <div @class([
                'p-4 rounded-xl border flex items-center gap-3',
                'bg-danger/10 border-danger/20 text-danger' => $flashType === 'error',
                'bg-secondary/10 border-secondary/20 text-secondary' => $flashType === 'success',
            ])>
                <span class="material-symbols-outlined">{{ $flashType === 'error' ? 'error' : 'check_circle' }}</span>
                <span class="text-sm font-bold">{{ $flashMessage }}</span>
            </div>
        @endif

        {{-- Token Inventory --}}
        <x-token-inventory :inventories="$this->playerInventories" />

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

        {{-- Dice Roll Action Area --}}
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            <div class="lg:col-span-12">
                <div class="bg-surface-container p-1 rounded-3xl border border-outline-variant/10">
                    <button
                        wire:click="rollDice"
                        @class([
                            'w-full rounded-[1.4rem] flex flex-col items-center justify-center gap-4 p-8 group transition-all relative overflow-hidden',
                            'bg-gradient-to-br from-primary-dim to-primary hover:brightness-110 active:scale-[0.98]' => $this->canRollOrTrade,
                            'bg-surface-variant/50 cursor-not-allowed opacity-50' => !$this->canRollOrTrade,
                        ])
                        @if (!$this->canRollOrTrade) disabled @endif
                    >
                        <div class="w-20 h-20 rounded-2xl bg-on-primary/20 flex items-center justify-center rotate-12 group-hover:rotate-0 transition-transform duration-500">
                            <span class="material-symbols-outlined text-5xl text-on-primary font-black">casino</span>
                        </div>
                        <div class="text-center relative z-10">
                            <span class="block text-4xl font-black font-display text-on-primary uppercase tracking-tighter">Lançar Dado</span>
                            <span class="text-on-primary/70 text-sm font-medium tracking-widest uppercase mt-1 block">Custo: 1 Ação</span>
                        </div>
                    </button>
                </div>
            </div>
        </section>

        {{-- Active Quotation Cards --}}
        <section class="space-y-4">
            <label class="font-display text-xs uppercase tracking-[0.3em] text-on-surface-variant font-bold">Cotações Ativas (Mercado)</label>
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

        {{-- Token Return UI (shown only when over 10 tokens) --}}
        @if ($this->needsTokenReturn)
            <livewire:arena.token-return :match="$this->match" :key="'token-return-' . $this->match->id" />
        @endif
    </main>

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
                            <x-token-dot :color="$slug" size="lg" />
                            <span class="text-[10px] font-bold uppercase text-on-surface-variant">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
