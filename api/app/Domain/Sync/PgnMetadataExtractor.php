<?php

declare(strict_types=1);

namespace App\Domain\Sync;

use App\Domain\Chess\Pgn;

/**
 * Métadonnées extraites des en-têtes PGN ; l'adversaire et la couleur sont
 * déduits du pseudo synchronisé.
 */
final class PgnMetadataExtractor
{
    /** @return array<string,mixed> */
    public function extract(string $pgn, string $username): array
    {
        $headers = Pgn::parse($pgn)['headers'];
        $white = $headers['White'] ?? null;
        $black = $headers['Black'] ?? null;

        $meta = [
            'played_at' => $headers['UTCDate'] ?? $headers['Date'] ?? null,
            'opening' => $headers['ECO'] ?? $headers['Opening'] ?? null,
            'time_control' => $headers['TimeControl'] ?? null,
            'result' => $headers['Result'] ?? null,
            'tournament' => $headers['Event'] ?? null,
        ];

        if ($white !== null && $black !== null) {
            $playsWhite = strcasecmp((string) $white, $username) === 0;
            $meta['opponent'] = $playsWhite ? $black : $white;
            $meta['color'] = $playsWhite ? 'white' : 'black';
        }

        return $meta;
    }
}
