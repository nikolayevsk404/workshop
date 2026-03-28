@props([
    'quotationCard',
    'playerInventories' => collect(),
    'canTrade' => false,
])

@php
    $trades = $quotationCard->trades->sortBy('sort_order');

    $playerTokens = $playerInventories->mapWithKeys(fn ($inv) => [$inv->tokenColor->slug => $inv->quantity]);
@endphp

<div class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/10 space-y-3">
    <div class="flex items-center justify-between mb-1">
        <span class="text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">{{ $quotationCard->name }}</span>
    </div>

    @foreach ($trades as $trade)
        @php
            $leftItems = $trade->leftItems;
            $rightItems = $trade->rightItems;

            $canAffordLeftToRight = $canTrade;
            foreach ($leftItems as $item) {
                $slug = $item->tokenColor->slug;
                if (($playerTokens[$slug] ?? 0) < $item->quantity) {
                    $canAffordLeftToRight = false;
                    break;
                }
            }

            $canAffordRightToLeft = $canTrade;
            foreach ($rightItems as $item) {
                $slug = $item->tokenColor->slug;
                if (($playerTokens[$slug] ?? 0) < $item->quantity) {
                    $canAffordRightToLeft = false;
                    break;
                }
            }
        @endphp

        <div @class([
            'p-3 rounded-xl border transition-all',
            'bg-surface-container-lowest/40 border-outline-variant/10' => $canAffordLeftToRight || $canAffordRightToLeft,
            'bg-surface-container-lowest/20 border-outline-variant/5 opacity-50' => !$canAffordLeftToRight && !$canAffordRightToLeft,
        ])>
            <div class="flex items-center justify-around gap-2">
                <div class="flex items-center gap-2">
                    @foreach ($leftItems as $item)
                        <div class="flex items-center gap-1">
                            <x-token-dot :color="$item->tokenColor->slug" size="sm" />
                            <span class="text-[10px] font-bold">{{ $item->quantity }}x</span>
                        </div>
                    @endforeach
                </div>

                <span class="material-symbols-outlined text-outline-variant text-sm">swap_horiz</span>

                <div class="flex items-center gap-2">
                    @foreach ($rightItems as $item)
                        <div class="flex items-center gap-1">
                            <x-token-dot :color="$item->tokenColor->slug" size="sm" />
                            <span class="text-[10px] font-bold">{{ $item->quantity }}x</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2 mt-2">
                <button
                    wire:click="executeTrade({{ $trade->id }}, 'left_to_right')"
                    @class([
                        'flex-1 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors',
                        'bg-surface-variant text-on-surface-variant hover:bg-primary hover:text-on-primary' => $canAffordLeftToRight,
                        'bg-surface-variant/50 text-on-surface-variant/50 cursor-not-allowed' => !$canAffordLeftToRight,
                    ])
                    @if (!$canAffordLeftToRight) disabled @endif
                >
                    →
                </button>
                <button
                    wire:click="executeTrade({{ $trade->id }}, 'right_to_left')"
                    @class([
                        'flex-1 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors',
                        'bg-surface-variant text-on-surface-variant hover:bg-primary hover:text-on-primary' => $canAffordRightToLeft,
                        'bg-surface-variant/50 text-on-surface-variant/50 cursor-not-allowed' => !$canAffordRightToLeft,
                    ])
                    @if (!$canAffordRightToLeft) disabled @endif
                >
                    ←
                </button>
            </div>
        </div>
    @endforeach
</div>
