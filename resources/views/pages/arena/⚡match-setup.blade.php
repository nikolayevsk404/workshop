<?php

use App\Models\DifficultyTier;
use App\Models\GameMatch;
use App\Models\MatchStatus;
use App\Models\QuotationCard;
use App\Services\MatchInitializationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Nova Partida')] class extends Component
{
    public array $selectedQuotationCards = [];

    public ?int $selectedTierId = null;

    public function mount()
    {
        $inProgressStatusId = MatchStatus::inProgress()->value('id');

        if ($inProgressStatusId) {
            $existingMatch = GameMatch::where('user_id', auth()->id())
                ->where('match_status_id', $inProgressStatusId)
                ->first();

            if ($existingMatch) {
                $this->redirect(route('arena.match.show', $existingMatch));

                return;
            }
        }
    }

    #[Computed]
    public function quotationCards()
    {
        return QuotationCard::with(['trades.items.tokenColor', 'trades.items.tradeSide'])
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function difficultyTiers()
    {
        return DifficultyTier::orderBy('sort_order')->get();
    }

    public function toggleQuotationCard(int $cardId): void
    {
        if (in_array($cardId, $this->selectedQuotationCards)) {
            $this->selectedQuotationCards = array_values(
                array_filter($this->selectedQuotationCards, fn ($id) => $id !== $cardId)
            );

            return;
        }

        if (count($this->selectedQuotationCards) >= 2) {
            array_shift($this->selectedQuotationCards);
        }

        $this->selectedQuotationCards[] = $cardId;
    }

    public function selectTier(int $tierId): void
    {
        $this->selectedTierId = $tierId;
    }

    #[Computed]
    public function canStart(): bool
    {
        return count($this->selectedQuotationCards) === 2 && $this->selectedTierId !== null;
    }

    public function startMatch(MatchInitializationService $service): void
    {
        if (! $this->canStart) {
            return;
        }

        $inProgressStatusId = MatchStatus::inProgress()->value('id');

        if ($inProgressStatusId) {
            $existingMatch = GameMatch::where('user_id', auth()->id())
                ->where('match_status_id', $inProgressStatusId)
                ->first();

            if ($existingMatch) {
                $this->redirect(route('arena.match.show', $existingMatch));

                return;
            }
        }

        $match = $service->createMatch(
            auth()->user(),
            $this->selectedQuotationCards,
            $this->selectedTierId,
        );

        $this->redirect(route('arena.match.show', $match));
    }

};
?>

<div class="py-6">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold text-on-surface">
            Nova Partida
        </h1>
        <p class="mt-1 text-on-surface-variant">
            Configure seu tabuleiro tático. Selecione as cotações e a dificuldade para iniciar.
        </p>
    </div>

    {{-- Section: Quotation Cards --}}
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-display text-lg font-bold tracking-widest uppercase text-primary flex items-center gap-3">
                <span class="w-2 h-8 bg-primary rounded-full"></span>
                Escolha 2 Cotações
            </h2>
            <span class="text-xs font-label uppercase text-on-surface-variant tracking-widest bg-surface-container px-3 py-1 rounded-full">
                {{ count($selectedQuotationCards) }}/2 Selecionadas
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach($this->quotationCards as $card)
                @php
                    $isSelected = in_array($card->id, $selectedQuotationCards);
                @endphp
                <button
                    wire:click="toggleQuotationCard({{ $card->id }})"
                    wire:key="quotation-card-{{ $card->id }}"
                    class="group relative p-4 rounded-xl transition-all flex flex-col items-center gap-3
                        {{ $isSelected
                            ? 'bg-surface-container-high border-2 border-primary shadow-[0_0_15px_rgba(151,169,255,0.2)]'
                            : 'bg-surface-container-low hover:bg-surface-container-high border border-transparent hover:border-outline-variant/30'
                        }}"
                >
                    {{-- Selection indicator --}}
                    <div class="flex justify-between w-full items-center">
                        @if($isSelected)
                            <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="text-[10px] font-label text-primary font-bold">ATIVA</span>
                        @else
                            <span class="material-symbols-outlined text-on-surface-variant/30">radio_button_unchecked</span>
                            <span></span>
                        @endif
                    </div>

                    {{-- Trade representations --}}
                    <div class="flex flex-col gap-2 w-full">
                        @foreach($card->trades->take(2) as $trade)
                            <div class="flex items-center justify-center gap-1.5">
                                @foreach($trade->items->where('tradeSide.slug', 'left') as $item)
                                    @for($i = 0; $i < $item->quantity; $i++)
                                        <x-token-dot :color="$item->tokenColor->slug" size="sm" />
                                    @endfor
                                @endforeach
                                <span class="material-symbols-outlined text-on-surface-variant text-sm">arrow_forward</span>
                                @foreach($trade->items->where('tradeSide.slug', 'right') as $item)
                                    @for($i = 0; $i < $item->quantity; $i++)
                                        <x-token-dot :color="$item->tokenColor->slug" size="sm" />
                                    @endfor
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    {{-- Card name --}}
                    <span class="text-xs font-label font-bold tracking-tighter {{ $isSelected ? 'text-on-surface' : 'text-on-surface-variant' }} uppercase">
                        {{ $card->name }}
                    </span>
                </button>
            @endforeach
        </div>
    </section>

    {{-- Section: Difficulty Tiers --}}
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-display text-lg font-bold tracking-widest uppercase text-primary flex items-center gap-3">
                <span class="w-2 h-8 bg-primary rounded-full"></span>
                Escolha a Dificuldade
            </h2>
        </div>

        <div class="flex flex-col md:flex-row gap-6">
            @foreach($this->difficultyTiers as $tier)
                @php
                    $isSelected = $selectedTierId === $tier->id;
                    $tierColor = match($tier->sort_order) {
                        1 => 'primary',
                        2 => 'secondary',
                        3 => 'tertiary',
                        default => 'primary',
                    };
                @endphp
                <button
                    wire:click="selectTier({{ $tier->id }})"
                    wire:key="tier-{{ $tier->id }}"
                    class="flex-1 p-6 rounded-2xl text-left transition-all cursor-pointer
                        {{ $isSelected
                            ? 'bg-surface-container-high border-2 border-primary shadow-[0_10px_40px_rgba(0,0,0,0.5)]'
                            : 'bg-surface-container-low border border-outline-variant/15 opacity-80 hover:opacity-100'
                        }}"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="text-[10px] font-label font-black tracking-[0.2em] {{ $isSelected ? 'text-primary' : 'text-on-surface-variant' }} uppercase">
                                Nível {{ $tier->sort_order }}
                            </span>
                            <h3 class="font-display text-xl font-bold text-on-surface">{{ $tier->name }}</h3>
                        </div>
                        <div class="flex gap-0.5">
                            @for($i = 1; $i <= 3; $i++)
                                <span class="material-symbols-outlined text-sm {{ $i <= $tier->star_count ? 'text-' . $tierColor : 'text-outline-variant' }}"
                                    @if($i <= $tier->star_count) style="font-variation-settings: 'FILL' 1;" @endif
                                >star</span>
                            @endfor
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                        <span class="text-[10px] font-label text-on-surface-variant uppercase">
                            Recompensa: {{ $tier->base_xp_reward }} XP
                        </span>
                        @if($isSelected)
                            <span class="material-symbols-outlined text-primary">check_circle</span>
                        @else
                            <span class="material-symbols-outlined text-outline-variant">radio_button_unchecked</span>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </section>

    {{-- Start Match Button --}}
    <div class="flex justify-end">
        <button
            wire:click="startMatch"
            wire:loading.attr="disabled"
            {{ $this->canStart ? '' : 'disabled' }}
            class="bg-primary text-on-primary px-8 py-4 rounded-full font-display font-black text-lg shadow-[0_8px_32px_rgba(151,169,255,0.4)] flex items-center gap-3 transition-all
                {{ $this->canStart ? 'hover:scale-105 active:scale-95 cursor-pointer' : 'opacity-50 cursor-not-allowed' }}"
        >
            <span wire:loading.remove wire:target="startMatch">Iniciar Partida</span>
            <span wire:loading wire:target="startMatch">Iniciando...</span>
            <span class="material-symbols-outlined">play_arrow</span>
        </button>
    </div>
</div>
