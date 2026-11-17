<?php

use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\V1\AnalysisController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LinkedAccountController;
use App\Http\Controllers\Api\V1\ManualEntryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Déclenchement des migrations par la CI (ADR-003)
|--------------------------------------------------------------------------
| En hébergement mutualisé FTP, la CI ne peut pas lancer `php artisan
| migrate` sur le serveur : elle appelle cette route avec un jeton secret.
| Sans jeton configuré (en local, par exemple), la route répond 404
| comme si elle n'existait pas.
*/
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->name('deploy.migrate');

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
        Route::get('/me/stats', [MeController::class, 'stats'])->name('me.stats');
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

        // Synchronisation des parties Lichess / Chess.com (issue #3).
        Route::post('/sync', [SyncController::class, 'store'])->name('sync.store');

        // État des synchronisations (asynchrone L7) : curseur et dernier passage.
        Route::get('/sync/statut', [SyncController::class, 'statut'])->name('sync.statut');

        // Bibliothèque de parties (issue #4).
        Route::get('/games', [GameController::class, 'index'])->name('games.index');
        Route::get('/games/{game}', [GameController::class, 'show'])->whereNumber('game')->name('games.show');
        Route::patch('/games/{game}', [GameController::class, 'update'])->whereNumber('game')->name('games.update');
        Route::post('/games/export', [GameController::class, 'export'])->name('games.export');
        Route::post('/games/search-position', [GameController::class, 'searchPosition'])->name('games.search-position');

        // Saisie manuelle des parties de tournoi (S5 + S7, issue #25).
        Route::post('/games/manual', [ManualEntryController::class, 'store'])->name('games.manual.store');
        Route::post('/games/manual/preview', [ManualEntryController::class, 'preview'])->name('games.manual.preview');
        Route::put('/games/{game}/manual', [ManualEntryController::class, 'update'])->whereNumber('game')->name('games.manual.update');

        // Rejeu et analyse des parties (issue #5, ADR-002).
        Route::get('/games/{game}/replay', [AnalysisController::class, 'replay'])->whereNumber('game')->name('games.replay');
        Route::post('/games/{game}/analyse', [AnalysisController::class, 'request'])->whereNumber('game')->name('games.analyse.request');
        Route::get('/games/{game}/analyse', [AnalysisController::class, 'result'])->whereNumber('game')->name('games.analyse.result');
        Route::post('/games/{game}/explore', [AnalysisController::class, 'explore'])->whereNumber('game')->name('games.explore');
    });
});
