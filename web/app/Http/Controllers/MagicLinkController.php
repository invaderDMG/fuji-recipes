<?php

namespace App\Http\Controllers;

use App\Auth\MagicLink;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    public function __invoke(Request $request, MagicLink $magicLink, string $token): RedirectResponse
    {
        $user = $magicLink->consume($token);

        if ($user === null) {
            Notification::make()
                ->title('El enlace ya se ha usado o ha caducado')
                ->body('Pide otro.')
                ->danger()
                ->send();

            return redirect()->route('filament.admin.auth.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getUrl());
    }
}
