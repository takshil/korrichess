<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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

        // Repli o2switch : env('DEPLOY_TOKEN') ressort vide en production
        // alors que le .env le contient ; la CI depose donc aussi le jeton
        // dans .deploy-token, relu ici si la config est vide.
        if ($token === '') {
            $fichier = base_path('.deploy-token');
            if (is_file($fichier) && is_readable($fichier)) {
                $token = trim((string) file_get_contents($fichier));
            }
        }

        // Jeton accepté via X-Deploy-Token ou Authorization: Bearer : certains
        // hébergements mutualisés filtrent les en-têtes personnalisés (WAF)
        // ou ne transmettent pas Authorization en CGI sans passe-plat Apache.
        $fourni = (string) $request->header('X-Deploy-Token');
        if ($fourni === '' && preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization', ''), $m) === 1) {
            $fourni = $m[1];
        }
        // Repli mutualisé : si le WAF/Apache filtre tout en-tête entrant, le
        // jeton peut être passé en paramètre d'URL (?token=...). A retirer
        // une fois la cause du filtrage identifiée (voir Log ci-dessous).
        if ($fourni === '' && $request->query('token') !== null) {
            $fourni = (string) $request->query('token');
        }

        if ($token === '' || ! hash_equals($token, $fourni)) {
            // Diagnostic : journaliser ce que PHP a réellement reçu, pour
            // identifier le maillon qui perd le jeton (WAF, Apache, CGI).
            Log::warning('Jeton de deploiement invalide', [
                'x_deploy_token' => $request->header('X-Deploy-Token'),
                'authorization' => $request->header('Authorization'),
                'token_query' => $request->query('token') !== null,
                'longueur_config' => strlen($token),
                'longueur_env' => strlen((string) env('DEPLOY_TOKEN')),
                'longueur_recue' => strlen($fourni),
            ]);
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'message' => 'migrations_appliquees',
            'sortie' => trim(Artisan::output()),
        ]);
    }
}
