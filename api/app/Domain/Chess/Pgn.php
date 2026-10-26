<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Chess;

final class Pgn
{
    /**
     * Parse un PGN (têtes + coups) et retourne la liste des coups en SAN pur,
     * sans rejouer sur un échiquier (utile pour l'affichage).
     * @return array{headers:array<string,string>,moves:array<int,string>}
     */
    public static function parse(string $pgn): array
    {
        $headers = [];
        if (preg_match_all('/\[(\w+)\s+"([^"]*)"\]/', $pgn, $m, PREG_SET_ORDER)) {
            foreach ($m as $h) {
                $headers[$h[1]] = $h[2];
            }
        }
        $movesSection = preg_replace('/\[[^\]]*\]/', '', $pgn);
        $movesSection = preg_replace('/\{[^}]*\}/', '', $movesSection) ?? '';
        $movesSection = preg_replace('/\d+\.\.\./', ' ', $movesSection) ?? '';
        $movesSection = preg_replace('/\d+\./', ' ', $movesSection) ?? '';
        $movesSection = str_replace([';', "\n", "\r"], ' ', $movesSection);
        $tokens = preg_split('/\s+/', trim($movesSection)) ?: [];
        $moves = [];
        foreach ($tokens as $token) {
            $token = trim($token, '.');
            if ($token === '' || $token === '*' || str_starts_with($token, '$')) {
                continue;
            }
            if (in_array($token, ['1-0', '0-1', '1/2-1/2'], true)) {
                continue;
            }
            if (preg_match('/^([a-hKQRBNO][^\s]*)$/', $token)) {
                $moves[] = $token;
            }
        }
        return ['headers' => $headers, 'moves' => $moves];
    }

    /**
     * Rejoue les coups SAN sur un échiquier ; renvoie les coups UCI ou null si un coup est illégal.
     * @return array<int,string>|null
     */
    public static function movesToUci(string $pgn): ?array
    {
        $parsed = self::parse($pgn);
        $board = Board::start();
        $uci = [];
        foreach ($parsed['moves'] as $san) {
            $move = $board->sanToUci($san);
            if ($move === null) {
                return null;
            }
            $board->applyUci($move);
            $uci[] = $move;
        }
        return $uci;
    }

    /** Génère un PGN minimal. @param array<string,string> $headers @param array<int,string> $movesUci */
    public static function build(array $headers, array $movesUci, string $result = '*'): string
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            $lines[] = sprintf('[%s "%s"]', $key, $value);
        }
        $board = Board::start();
        $sanMoves = [];
        foreach ($movesUci as $uci) {
            $sanMoves[] = $board->uciToSan($uci);
            $board->applyUci($uci);
        }
        $body = '';
        $num = 1;
        for ($i = 0; $i < count($sanMoves); $i += 2) {
            $body .= $num . '. ' . $sanMoves[$i];
            if (isset($sanMoves[$i + 1])) {
                $body .= ' ' . $sanMoves[$i + 1];
            }
            $body .= ' ';
            $num++;
        }
        $lines[] = trim($body) . ' ' . $result;
        return implode("\n", $lines) . "\n";
    }
}
