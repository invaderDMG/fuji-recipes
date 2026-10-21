<?php

use App\Http\Controllers\MagicLinkController;
use Illuminate\Support\Facades\Route;

// La interfaz es el panel de Filament, servido en la raíz (ver AdminPanelProvider).

// Enlace de acceso de un solo uso que envía MagicLinkLogin
Route::get('login/enlace/{token}', MagicLinkController::class)
    ->middleware('throttle:10,1')
    ->name('login.magic');
