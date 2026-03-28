@props([
    'compartment',
    'position' => 1,
    'canPurchase' => false,
    'playerInventories' => collect(),
])

@php
    $faceUpCard = $compartment->faceUpCard();
    $card = $faceUpCard?->card;
    $totalCards = $compartment->cards->count();
    $purchasedCards = $compartment->cards->where('is_purchased', true)->count();
    $remainingCards = $totalCards - $purchasedCards;
    $allPurchased = $remainingCards === 0;
    $cardTokens = $card?->tokens ?? collect();

    $colorOrder = ['red', 'green', 'white', 'yellow', 'blue'];

    $playerTokens = collect($playerInventories)->mapWithKeys(fn ($inv) => [$inv->tokenColor->slug => $inv->quantity]);
    $canAffordCard = $faceUpCard && $card && $cardTokens->every(function ($token) use ($playerTokens) {
        return ($playerTokens[$token->tokenColor->slug] ?? 0) >= $token->quantity;
    });
    $showBuyButton = $canPurchase && $canAffordCard;
@endphp

<div @class([
    'p-4 rounded-2xl border transition-all',
    'bg-surface-container-low border-outline-variant/10' => !$allPurchased,
    'bg-surface-container-low/30 border-outline-variant/5 opacity-40' => $allPurchased,
])>
    <div class="flex items-center justify-between mb-3">
        <span class="text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">Comp. {{ $position }}</span>
        <span class="text-[10px] text-outline-variant">{{ $remainingCards }} restantes</span>
    </div>

    @if ($faceUpCard && $card)
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold font-display text-on-surface">Carta #{{ $card->number }}</span>
                @if ($card->star_count > 0)
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-warning/10 border border-warning/20">
                        @for ($i = 0; $i < $card->star_count; $i++)
                            <span class="material-symbols-outlined text-warning text-xs" style="font-variation-settings: 'FILL' 1;">star</span>
                        @endfor
                    </span>
                @endif
            </div>

            <div class="space-y-1.5">
                @foreach ($cardTokens->sortBy(fn ($t) => array_search($t->tokenColor->slug, $colorOrder)) as $token)
                    @if ($token->quantity > 0)
                        <div class="flex items-center justify-between px-2 py-1 bg-surface-container-lowest/40 rounded-lg">
                            <div class="flex items-center gap-2">
                                <x-token-dot :color="$token->tokenColor->slug" size="sm" />
                                <span class="text-[10px] font-bold text-on-surface">{{ ucfirst($token->tokenColor->name) }}</span>
                            </div>
                            <span class="text-xs font-bold font-display">{{ $token->quantity }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="flex items-center justify-center py-6 text-outline-variant">
            <span class="text-xs">Vazio</span>
        </div>
    @endif

    @if ($showBuyButton)
        <button
            wire:click="purchaseCard({{ $faceUpCard->id }})"
            class="w-full mt-3 py-2 rounded-xl bg-secondary text-on-secondary font-bold font-display uppercase tracking-widest text-xs hover:brightness-110 transition-all active:scale-[0.98]"
        >
            Comprar Carta
        </button>
    @endif

    @if ($compartment->is_star_bonus_active)
        <div class="mt-3 flex items-center gap-1.5 px-2 py-1 rounded-lg bg-secondary/10 border border-secondary/20">
            <span class="material-symbols-outlined text-secondary text-sm" style="font-variation-settings: 'FILL' 1;">stars</span>
            <span class="text-[10px] font-bold text-secondary uppercase tracking-wider">Bônus Ativo</span>
        </div>
    @endif
</div>
