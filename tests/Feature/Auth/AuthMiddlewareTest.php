<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated user to login when accessing arena', function () {
    $this->get('/arena')
        ->assertRedirect(route('login'));
});

it('allows authenticated user to access arena', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/arena')
        ->assertStatus(200);
});

it('allows unverified user to access arena', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/arena')
        ->assertStatus(200);
});
