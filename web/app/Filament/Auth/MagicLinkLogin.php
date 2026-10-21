<?php

namespace App\Filament\Auth;

use App\Auth\MagicLink;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Login sin contraseña: pide el correo y envía un enlace de un solo uso.
 */
class MagicLinkLogin extends Login
{
    public function mount(): void
    {
        // En local y preproducción LoginLocalUser entra solo (p. ej. tras cerrar sesión)
        if (app()->environment(config('fuji.auto_login_environments'))) {
            $this->redirect(Filament::getUrl());

            return;
        }

        parent::mount();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        app(MagicLink::class)->send($this->form->getState()['email']);

        // Mismo mensaje con cualquier correo, para no revelar cuál es el bueno
        Notification::make()
            ->title('Revisa tu correo')
            ->body('Si es tu dirección, te hemos enviado un enlace para entrar. Caduca en '.config('fuji.magic_link_minutes').' minutos.')
            ->success()
            ->persistent()
            ->send();

        $this->form->fill();

        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent()->label('Correo'),
        ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Entrar en Fuji Recipes';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Te enviamos un enlace de un solo uso, sin contraseña.';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Enviar enlace');
    }
}
