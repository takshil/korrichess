<?php

declare(strict_types=1);

use Korrichess\Api\Core\QueueWorker;
use Korrichess\Api\Domain\Analysis\AnalysisService;
use Korrichess\Api\Domain\Analysis\HeuristicEngine;
use Korrichess\Api\Domain\Analysis\UciEngine;

if (!function_exists('analyse_engine')) {
    function analyse_engine(): Korrichess\Api\Domain\Analysis\EngineClient
    {
        $path = (string) (getenv('KORRI_STOCKFISH_PATH') ?: 'stockfish');
        try {
            return new UciEngine($path);
        } catch (InvalidArgumentException) {
            return new HeuristicEngine();
        }
    }
}

return static function (QueueWorker $worker): void {
    $engine = analyse_engine();
    $worker->register('analyse', static function (array $payload) use ($engine): void {
        $analysisId = (int) ($payload['analysis_id'] ?? 0);
        $gameId = (int) ($payload['game_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($analysisId <= 0 || $gameId <= 0 || $userId <= 0) {
            throw new RuntimeException('payload_analyse_invalide');
        }
        $service = new AnalysisService($engine);
        $result = $service->analyse($userId, $gameId, depth: (int) ($payload['depth'] ?? 12));
        if ($result['status'] !== 200) {
            throw new RuntimeException('analyse_echouee_' . $result['status']);
        }
    });
};

