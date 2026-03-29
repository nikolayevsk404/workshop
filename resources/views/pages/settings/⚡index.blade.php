<?php

use App\Livewire\Forms\UpdatePasswordForm;
use App\Livewire\Forms\UpdateProfileForm;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Configurações')] class extends Component
{
    public UpdateProfileForm $profileForm;

    public UpdatePasswordForm $passwordForm;

    public function mount(): void
    {
        $user = auth()->user();
        $this->profileForm->username = $user->username;
        $this->profileForm->email = $user->email;
    }

    public function updateProfile(): void
    {
        $this->profileForm->validate();

        auth()->user()->update([
            'username' => $this->profileForm->username,
            'email' => $this->profileForm->email,
        ]);

        session()->flash('success', 'Perfil atualizado com sucesso!');
    }

    public function updatePassword(): void
    {
        $this->passwordForm->validate();

        auth()->user()->update([
            'password' => Hash::make($this->passwordForm->password),
        ]);

        $this->passwordForm->reset();

        session()->flash('success', 'Senha atualizada com sucesso!');
    }
};
?>

<div class="py-6">
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold text-on-surface">Configurações</h1>
        <p class="mt-1 text-on-surface-variant">Gerencie seu perfil e senha</p>
    </div>

    <div class="max-w-2xl space-y-6">
        {{-- Profile Update --}}
        <x-card>
            <h2 class="mb-4 font-display text-xl font-bold text-on-surface">Perfil</h2>

            <form wire:submit="updateProfile" class="space-y-4">
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-on-surface-variant">Nome de usuário</label>
                    <input
                        type="text"
                        id="username"
                        wire:model="profileForm.username"
                        class="w-full rounded-lg border border-outline-variant/15 bg-surface-container px-4 py-2.5 text-on-surface placeholder-on-surface-variant/50 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    @error('profileForm.username') <span class="mt-1 text-sm text-danger">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-on-surface-variant">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        wire:model="profileForm.email"
                        class="w-full rounded-lg border border-outline-variant/15 bg-surface-container px-4 py-2.5 text-on-surface placeholder-on-surface-variant/50 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    @error('profileForm.email') <span class="mt-1 text-sm text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="pt-2">
                    <x-button type="submit">Salvar Perfil</x-button>
                </div>
            </form>
        </x-card>

        {{-- Password Update --}}
        <x-card>
            <h2 class="mb-4 font-display text-xl font-bold text-on-surface">Alterar Senha</h2>

            <form wire:submit="updatePassword" class="space-y-4">
                <div>
                    <label for="current_password" class="mb-1 block text-sm font-medium text-on-surface-variant">Senha atual</label>
                    <input
                        type="password"
                        id="current_password"
                        wire:model="passwordForm.current_password"
                        class="w-full rounded-lg border border-outline-variant/15 bg-surface-container px-4 py-2.5 text-on-surface placeholder-on-surface-variant/50 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    @error('passwordForm.current_password') <span class="mt-1 text-sm text-danger">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-on-surface-variant">Nova senha</label>
                    <input
                        type="password"
                        id="password"
                        wire:model="passwordForm.password"
                        class="w-full rounded-lg border border-outline-variant/15 bg-surface-container px-4 py-2.5 text-on-surface placeholder-on-surface-variant/50 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    @error('passwordForm.password') <span class="mt-1 text-sm text-danger">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-on-surface-variant">Confirmar nova senha</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        wire:model="passwordForm.password_confirmation"
                        class="w-full rounded-lg border border-outline-variant/15 bg-surface-container px-4 py-2.5 text-on-surface placeholder-on-surface-variant/50 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>

                <div class="pt-2">
                    <x-button type="submit">Alterar Senha</x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
