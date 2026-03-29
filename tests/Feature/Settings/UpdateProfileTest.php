<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders settings page for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertStatus(200);
});

it('allows user to update username', function () {
    $user = User::factory()->create(['username' => 'oldname']);

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.username', 'newname')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->username)->toBe('newname');
});

it('allows user to update email without re-verification', function () {
    $user = User::factory()->create([
        'username' => 'jogador',
        'email' => 'old@test.com',
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.email', 'new@test.com')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('new@test.com');
});

it('fails username update with duplicate username', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create(['username' => 'myname']);

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.username', 'taken')
        ->call('updateProfile')
        ->assertHasErrors(['profileForm.username']);
});

it('fails email update with duplicate email', function () {
    User::factory()->create(['email' => 'taken@test.com']);
    $user = User::factory()->create(['email' => 'mine@test.com']);

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.email', 'taken@test.com')
        ->call('updateProfile')
        ->assertHasErrors(['profileForm.email']);
});

it('fails username validation with too short username', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.username', 'ab')
        ->call('updateProfile')
        ->assertHasErrors(['profileForm.username']);
});

it('fails username validation with too long username', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.index')
        ->set('profileForm.username', str_repeat('a', 21))
        ->call('updateProfile')
        ->assertHasErrors(['profileForm.username']);
});
