<?php

use App\Models\GameMatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Partida')] class extends Component
{
    public GameMatch $match;
};
?>

<div class="py-6">
    <h1 class="font-display text-3xl font-bold text-on-surface">
        Partida #{{ $match->id }}
    </h1>
    <p class="mt-1 text-on-surface-variant">Tabuleiro em construção...</p>
</div>
