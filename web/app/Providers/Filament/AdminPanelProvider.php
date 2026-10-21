<?php

namespace App\Providers\Filament;

use App\Filament\Auth\MagicLinkLogin;
use App\Http\Middleware\LoginLocalUser;
use App\Support\FujiTheme;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            // En local y preproducción no se ve: LoginLocalUser entra solo
            ->login(MagicLinkLogin::class)
            ->brandName('Fuji Recipes')
            ->colors([
                'primary' => FujiTheme::PRIMARY,
                'gray' => FujiTheme::GRAY,
            ])
            ->font(FujiTheme::FONT, url: FujiTheme::FONT_URL, provider: GoogleFontProvider::class)
            // Hoja propia sin compilar: el droplet no necesita Node (ver public/css/fuji.css)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => FujiTheme::stylesheet())
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Un solo usuario: en local entra solo, en producción con enlace
                LoginLocalUser::class,
                Authenticate::class,
            ]);
    }
}
