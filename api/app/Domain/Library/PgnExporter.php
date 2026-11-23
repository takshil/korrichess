<?php

declare(strict_types=1);

namespace App\Domain\Library;

use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use App\Models\Game;

/**
 * B5 : export PGN d'une sélection de parties. La note personnelle et les
 * étiquettes sont injectées en en-têtes `KorriNote` / `KorriTags`.
 */
final class PgnExporter
{
    public function __construct(private readonly GameRepository $games)
    {
    }

    /**
     * @param list<int> $gameIds
     *
     * @return array{count:int,pgn:string}
     *
     * @throws DomainError sélection vide ou aucune partie trouvée
     */
    public function export(int $userId, array $gameIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $gameIds)));
        if ($ids === []) {
            throw DomainError::invalid('selection_vide');
        }

        $rows = $this->games->findOwnedMany($userId, $ids);
        if ($rows->isEmpty()) {
            throw DomainError::notFound('aucune_partie');
        }

        $pgns = $rows->map(fn (Game $game): string => $this->withKorriHeaders($game))->all();

        return ['count' => count($pgns), 'pgn' => implode("\n\n", $pgns) . "\n"];
    }

    private function withKorriHeaders(Game $game): string
    {
        $pgn = trim((string) $game->pgn);

        $extras = '';
        if ((string) $game->personal_note !== '') {
            $extras .= $this->header('KorriNote', (string) $game->personal_note);
        }
        if ((string) $game->tags !== '') {
            $extras .= $this->header('KorriTags', (string) $game->tags);
        }

        $separator = strpos($pgn, "\n\n");
        if ($extras === '' || $separator === false) {
            return $pgn;
        }

        return substr($pgn, 0, $separator) . "\n" . $extras . "\n" . substr($pgn, $separator + 2);
    }

    private function header(string $name, string $value): string
    {
        return '[' . $name . ' "' . str_replace('"', '', $value) . "\"]\n";
    }
}
