<?php

use App\Auth\MagicLink;
use App\Filament\Auth\MagicLinkLogin;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Database\Seeders\LocalUserSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LocalUserSeeder::class);
    $this->app->detectEnvironment(fn () => 'production');
    config(['fuji.login_email' => 'yo@example.com']);
    Notification::fake();
});

/**
 * Pide un enlace desde la página de login y devuelve la URL enviada por correo.
 */
function requestMagicLink(string $email = 'yo@example.com'): ?string
{
    Livewire::test(MagicLinkLogin::class)
        ->set('data.email', $email)
        ->call('authenticate')
        ->assertNotified('Revisa tu correo');

    $sent = Notification::sent(new AnonymousNotifiable, MagicLinkNotification::class);

    return $sent->last()?->url;
}

it('asks for the login in production instead of logging in automatically', function () {
    $this->get('/')->assertRedirect('/login');
    $this->assertGuest();

    $this->get('/login')->assertOk()->assertSee('Enviar enlace')->assertDontSee('Password');
});

it('sends a one-time link to the allowed email and logs in with it', function () {
    $url = requestMagicLink('YO@Example.com');

    Notification::assertSentOnDemand(MagicLinkNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'yo@example.com');

    $this->get($url)->assertRedirect('/');
    $this->assertAuthenticated();
    $this->get('/')->assertOk();
});

it('does not reuse a link', function () {
    $url = requestMagicLink();
    $this->get($url);
    auth()->logout();

    $this->get($url)->assertRedirect('/login');
    $this->assertGuest();
});

it('expires the link', function () {
    $url = requestMagicLink();

    $this->travel(config('fuji.magic_link_minutes') + 1)->minutes();

    $this->get($url)->assertRedirect('/login');
    $this->assertGuest();
});

it('sends nothing to other emails, with the same message', function () {
    expect(requestMagicLink('otro@example.com'))->toBeNull();

    Notification::assertNothingSent();
});

it('sends nothing when no email is configured', function () {
    config(['fuji.login_email' => null]);

    app(MagicLink::class)->send('yo@example.com');

    Notification::assertNothingSent();
});

it('rejects made-up tokens', function () {
    $this->get('/login/enlace/'.str_repeat('a', 64))->assertRedirect('/login');
    $this->assertGuest();
});

it('skips the login in local and staging', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    $this->get('/')->assertOk();
    $this->assertAuthenticated();
})->with(['local', 'staging']);

it('does not store passwords', function () {
    expect(User::first()->password)->toBeNull();
});
