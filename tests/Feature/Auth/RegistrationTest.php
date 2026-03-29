<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the registration page', function () {
    $this->get('/register')->assertStatus(200);
});

it('allows user to register with valid data', function () {
    Event::fake();

    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'jogador@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('arena'));

    expect(User::where('email', 'jogador@test.com')->exists())->toBeTrue();
    expect(auth()->check())->toBeTrue();
});

it('fails registration with duplicate username', function () {
    User::factory()->create(['username' => 'existing']);

    Livewire::test('pages::auth.register')
        ->set('form.username', 'existing')
        ->set('form.email', 'new@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['form.username']);
});

it('fails registration with duplicate email', function () {
    User::factory()->create(['email' => 'taken@test.com']);

    Livewire::test('pages::auth.register')
        ->set('form.username', 'newuser')
        ->set('form.email', 'taken@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['form.email']);
});

it('fails registration with short username', function () {
    Livewire::test('pages::auth.register')
        ->set('form.username', 'ab')
        ->set('form.email', 'test@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['form.username']);
});

it('fails registration with long username', function () {
    Livewire::test('pages::auth.register')
        ->set('form.username', str_repeat('a', 21))
        ->set('form.email', 'test@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['form.username']);
});

it('fails registration with short password', function () {
    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'test@test.com')
        ->set('form.password', 'short')
        ->set('form.password_confirmation', 'short')
        ->call('save')
        ->assertHasErrors(['form.password']);
});

it('fails registration with mismatched password confirmation', function () {
    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'test@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'different123')
        ->call('save')
        ->assertHasErrors(['form.password']);
});

it('fails registration with invalid email format', function () {
    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'not-an-email')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['form.email']);
});

it('redirects to arena after successful registration', function () {
    Event::fake();

    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'jogador@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save')
        ->assertRedirect(route('arena'));
});

it('does not send verification email upon registration', function () {
    Event::fake();

    Livewire::test('pages::auth.register')
        ->set('form.username', 'jogador1')
        ->set('form.email', 'jogador@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('save');

    Event::assertNotDispatched(Registered::class);
});
