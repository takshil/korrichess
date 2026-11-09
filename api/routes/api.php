<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LinkedAccountController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Korrichess — toutes les routes sont préfixées /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    // Authentification e-mail + mot de passe (C1) — jetons Sanctum.
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::middleware('auth:sanctum')->group(function (): void {
        // Profil joueur (C2) + RGPD (C4 / #18).
        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::patch('/me', [MeController::class, 'update'])->name('me.update');
        Route::get('/me/export', [MeController::class, 'export'])->name('me.export');
        Route::delete('/me', [MeController::class, 'destroy'])->name('me.destroy');

        // Comptes liés Lichess / Chess.com (C3).
        Route::post('/me/linked-accounts', [LinkedAccountController::class, 'store'])->name('linked-accounts.store');
        Route::delete('/me/linked-accounts', [LinkedAccountController::class, 'destroy'])->name('linked-accounts.destroy');

        // Consentements RGPD, révocables (#18).
        Route::get('/me/consents', [ConsentController::class, 'index'])->name('consents.index');
        Route::put('/me/consents', [ConsentController::class, 'update'])->name('consents.update');

        // Paramètres du joueur (issue #10).
        Route::get('/me/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::patch('/me/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
