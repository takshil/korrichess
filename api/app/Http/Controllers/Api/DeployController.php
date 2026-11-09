<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

final class DeployController extends Controller
{
    /**
     * Applique les migrations de la base de production.
     *
     * Déclenché par la CI après un déploiement FTP mutualisé, où aucun
     * accès SSH n'est disponible pour lancer `php artisan migrate`
     * (ADR-003). Protégé par un jeton secret : sans jeton configuré ou
     * avec un jeton invalide, la route répond 404 comme si elle
     * n'existait pas.
     */
    public function migrate(Request $request): JsonResponse
    {
        $token = (string) config('korrichess.deploy_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'message' => 'migrations_appliquees',
            'sortie' => trim(Artisan::output()),
        ]);
    }
}
