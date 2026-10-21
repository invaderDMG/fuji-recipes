<?php

use Database\Seeders\LocalUserSeeder;

it('serves the dashboard at the root, logging in the local user', function () {
    $this->seed(LocalUserSeeder::class);

    $this->get('/')->assertOk()->assertSee('Fuji Recipes');
    $this->assertAuthenticated();
});

it('fails clearly when the local user does not exist', function () {
    $this->withoutExceptionHandling();

    $this->get('/');
})->throws(RuntimeException::class, 'No existe el usuario local');
