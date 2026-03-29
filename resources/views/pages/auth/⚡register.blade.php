<?php

use App\Livewire\Forms\RegisterForm;
use App\Models\User;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Criar Conta')] class extends Component
{
    public RegisterForm $form;

    public function save(): void
    {
        $this->form->validate();

        $user = User::create($this->form->only(['username', 'email', 'password']));

        auth()->login($user);

        $this->redirect(route('arena'));
    }
};
?>

<div>
    <x-slot:subtitle>Inicie sua Jornada na Arena</x-slot:subtitle>

    <form wire:submit="save" class="space-y-5">
        <x-input
            label="Nome de Jogador"
            name="form.username"
            type="text"
            placeholder="Ex: PLAYER_ONE"
            wire:model="form.username"
            :error="$errors->first('form.username')"
        />

        <x-input
            label="E-mail"
            name="form.email"
            type="email"
            placeholder="seu@email.com"
            wire:model="form.email"
            :error="$errors->first('form.email')"
        />

        <x-input
            label="Senha de Acesso"
            name="form.password"
            type="password"
            placeholder="••••••••"
            wire:model="form.password"
            :error="$errors->first('form.password')"
        />

        <x-input
            label="Confirmar Senha"
            name="form.password_confirmation"
            type="password"
            placeholder="••••••••"
            wire:model="form.password_confirmation"
        />

        <x-button type="submit" class="w-full justify-center" wire:loading.attr="disabled">
            <span wire:loading.remove>Criar Conta</span>
            <span wire:loading>Criando...</span>
        </x-button>
    </form>

    <div class="mt-6 pt-6 border-t border-outline-variant/15 text-center">
        <p class="text-on-surface-variant text-sm">
            Já possui um registro?
            <a href="{{ route('login') }}" class="text-primary font-bold hover:underline ml-1" wire:navigate>Acessar Arena</a>
        </p>
    </div>
</div>
