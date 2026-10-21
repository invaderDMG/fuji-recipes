<?php

namespace App\Auth;

use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Acceso sin contraseña: un enlace de un solo uso que caduca a los
 * fuji.magic_link_minutes. En la caché solo se guarda el hash del token.
 */
final class MagicLink
{
    /**
     * Envía el enlace si el correo es el autorizado (fuji.login_email). Para
     * cualquier otro no hace nada, sin decirlo: la respuesta es la misma.
     */
    public function send(string $email): void
    {
        $allowed = config('fuji.login_email');

        if (blank($allowed) || strcasecmp($email, $allowed) !== 0) {
            return;
        }

        $token = Str::random(64);
        $minutes = config('fuji.magic_link_minutes');
        Cache::put($this->key($token), true, now()->addMinutes($minutes));

        Notification::route('mail', $allowed)
            ->notify(new MagicLinkNotification(route('login.magic', $token), $minutes));
    }

    /**
     * Gasta el token: devuelve el usuario solo la primera vez y antes de caducar.
     */
    public function consume(string $token): ?User
    {
        if (! Cache::pull($this->key($token))) {
            return null;
        }

        return User::where('email', config('fuji.local_user.email'))->first();
    }

    private function key(string $token): string
    {
        return 'magic-link:'.hash('sha256', $token);
    }
}
