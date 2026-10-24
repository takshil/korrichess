<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Korrichess — toutes les routes sont préfixées /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    // Authentification e-mail + mot de passe (issue C1) :
    // à implémenter dans l'issue dédiée (enregistrement, connexion,
    // jetons Sanctum). Le squelette réserve l'espace de nommage.
});
