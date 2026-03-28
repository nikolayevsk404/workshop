<?php

use App\Models\GameMatch;
use App\Models\MatchTokenInventory;
use App\Models\ParticipantType;
use App\Services\TokenLimitService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public GameMatch $match;

    public array $tokensToReturn = [];

    public function mount(GameMatch $match): void
    {
        $this->match = $match;
        $this->tokensToReturn = [];
    }

    #[Computed]
    public function playerInventories()
    {
        $playerType = ParticipantType::where('slug', 'player')->first();

        return $this->match->tokenInventories()
            ->where('participant_type_id', $playerType?->id)
            ->with('tokenColor')
            ->get()
            ->sortBy(fn ($inv) => array_search($inv->tokenColor->slug, ['red', 'green', 'white', 'yellow', 'blue']));
    }

    #[Computed]
    public function totalTokens(): int
    {
        return $this->playerInventories->sum('quantity');
    }

    #[Computed]
    public function tokensOver(): int
    {
        return max(0, $this->totalTokens - 10);
    }

    #[Computed]
    public function totalMarkedForReturn(): int
    {
        return array_sum($this->tokensToReturn);
    }

    #[Computed]
    public function canConfirm(): bool
    {
        return $this->totalMarkedForReturn === $this->tokensOver;
    }

    public function handleSort(string $id, int $position, ?string $groupId = null): void
    {
        if ($groupId === 'return-zone') {
            $this->addTokenToReturn($id);
        }
    }

    public function addTokenToReturn(string $colorSlug): void
    {
        $inventory = $this->playerInventories->first(fn ($inv) => $inv->tokenColor->slug === $colorSlug);
        if (! $inventory) {
            return;
        }

        $currentReturn = $this->tokensToReturn[$colorSlug] ?? 0;
        if ($currentReturn < $inventory->quantity) {
            $this->tokensToReturn[$colorSlug] = $currentReturn + 1;
        }
    }

    public function removeTokenFromReturn(string $colorSlug): void
    {
        $currentReturn = $this->tokensToReturn[$colorSlug] ?? 0;
        if ($currentReturn > 0) {
            $this->tokensToReturn[$colorSlug] = $currentReturn - 1;
        }
    }

    public function confirmReturn(TokenLimitService $tokenLimitService): void
    {
        if (! $this->canConfirm) {
            return;
        }

        $playerType = ParticipantType::where('slug', 'player')->first();

        try {
            $tokenLimitService->returnTokens($this->match, $playerType->id, $this->tokensToReturn);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $this->tokensToReturn = [];
        $this->match->refresh();

        $this->dispatch('tokens-returned');
    }
};
?>

<div class="p-6 rounded-2xl border-2 border-danger/30 bg-danger/5 space-y-4">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-danger text-2xl">warning</span>
        <div>
            <h3 class="text-sm font-bold font-display text-danger uppercase">Limite de Tokens Excedido</h3>
            <p class="text-xs text-on-surface-variant">
                Você precisa devolver <strong class="text-danger">{{ $this->tokensOver }}</strong> token(s).
                Selecionados: <strong>{{ $this->totalMarkedForReturn }}</strong> / {{ $this->tokensOver }}
            </p>
        </div>
    </div>

    {{-- Token selection area with wire:sort --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Player tokens (draggable source) --}}
        <div class="space-y-2">
            <label class="text-[10px] uppercase tracking-widest text-on-surface-variant font-bold">Seus Tokens</label>
            <div wire:sort="handleSort" wire:sort:group="token-return" wire:sort:group-id="inventory" class="space-y-2 min-h-[60px] p-3 bg-surface-container-low rounded-xl border border-outline-variant/10">
                @foreach ($this->playerInventories as $inventory)
                    @if ($inventory->quantity > 0)
                        @php
                            $returnQty = $this->tokensToReturn[$inventory->tokenColor->slug] ?? 0;
                            $remaining = $inventory->quantity - $returnQty;
                        @endphp
                        @if ($remaining > 0)
                            <div wire:key="token-{{ $inventory->tokenColor->slug }}" wire:sort:item="{{ $inventory->tokenColor->slug }}" class="flex items-center justify-between p-2 bg-surface-container rounded-lg cursor-grab active:cursor-grabbing">
                                <div class="flex items-center gap-2">
                                    <x-token-dot :color="$inventory->tokenColor->slug" size="md" />
                                    <span class="text-xs font-bold text-on-surface">{{ ucfirst($inventory->tokenColor->name) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-display font-bold">{{ $remaining }}</span>
                                    <button wire:click="addTokenToReturn('{{ $inventory->tokenColor->slug }}')" class="text-danger hover:text-danger/80 transition-colors">
                                        <span class="material-symbols-outlined text-sm">remove_circle</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Return zone (drop target) --}}
        <div class="space-y-2">
            <label class="text-[10px] uppercase tracking-widest text-danger font-bold">Zona de Devolução</label>
            <div wire:sort="handleSort" wire:sort:group="token-return" wire:sort:group-id="return-zone" class="space-y-2 min-h-[60px] p-3 bg-danger/5 rounded-xl border-2 border-dashed border-danger/30">
                @forelse (collect($this->tokensToReturn)->filter(fn ($qty) => $qty > 0) as $colorSlug => $qty)
                    <div wire:key="return-{{ $colorSlug }}" wire:sort:item="return-{{ $colorSlug }}" class="flex items-center justify-between p-2 bg-danger/10 rounded-lg">
                        <div class="flex items-center gap-2">
                            <x-token-dot :color="$colorSlug" size="md" />
                            <span class="text-xs font-bold text-on-surface">{{ $qty }}x</span>
                        </div>
                        <button wire:click="removeTokenFromReturn('{{ $colorSlug }}')" class="text-on-surface-variant hover:text-on-surface transition-colors">
                            <span class="material-symbols-outlined text-sm">undo</span>
                        </button>
                    </div>
                @empty
                    <div class="flex items-center justify-center py-4 text-on-surface-variant/50">
                        <p class="text-xs">Arraste tokens aqui ou clique no botão</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Confirm Button --}}
    <button
        wire:click="confirmReturn"
        @class([
            'w-full py-3 rounded-xl font-bold font-display uppercase tracking-widest text-sm transition-all',
            'bg-danger text-on-danger hover:brightness-110' => $this->canConfirm,
            'bg-surface-variant text-on-surface-variant/50 cursor-not-allowed' => !$this->canConfirm,
        ])
        @if (!$this->canConfirm) disabled @endif
    >
        Confirmar Devolução ({{ $this->totalMarkedForReturn }} / {{ $this->tokensOver }})
    </button>
</div>
