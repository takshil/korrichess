<?php

declare(strict_types=1);

use Korrichess\Api\Core\AuthMiddleware;
use Korrichess\Api\Core\Request;
use Korrichess\Api\Core\Router;
use Korrichess\Api\Domain\Sync\SyncService;

return static function (Router $router): void {
    $router->post('/api/v1/sync', static function (Request $request): array {
        if ($err = AuthMiddleware::requireUser($request)) {
            return $err;
        }
        $userId = AuthMiddleware::userId($request);
        $service = new SyncService(GLOBAL_SYNC_CLIENTS ?? []);
        $input = $request->input();
        $filters = [];
        if (isset($input['time_controls']) && is_array($input['time_controls'])) {
            $filters['time_controls'] = $input['time_controls'];
        }
        if (isset($input['period_start'])) {
            $filters['period_start'] = (string) $input['period_start'];
        }
        return $service->sync(
            $userId,
            (string) $request->input('platform', ''),
            (string) $request->input('username', ''),
            $filters,
        );
    });
};
