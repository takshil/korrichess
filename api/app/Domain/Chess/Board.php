<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 * Plateau d'échecs : représentation FEN, génération des coups légaux,
 * application de coups UCI/SAN, détection échec/mat/pat, ambiguïtés SAN.
 *
 * Représentation interne : cases 'a1'..'h8' => 'wP','bK',...
 */
final class Board
{
    /** @var array<string,string> */
    private array $squares = [];

    private string $turn = 'w';

    /** @var array<string,bool> roques disponibles : 'K','Q','k','q' */
    private array $castling;

    /** case de prise en passant éligible ou null */
    private ?string $epSquare = null;

    /** demi-coups depuis la dernière prise/avance de pion */
    private int $halfmove = 0;

    private int $fullmove = 1;

    private function __construct()
    {
    }

    public static function start(): self
    {
        $b = new self();
        $b->squares = [
            'a1' => 'wR', 'b1' => 'wN', 'c1' => 'wB', 'd1' => 'wQ', 'e1' => 'wK', 'f1' => 'wB', 'g1' => 'wN', 'h1' => 'wR',
            'a2' => 'wP', 'b2' => 'wP', 'c2' => 'wP', 'd2' => 'wP', 'e2' => 'wP', 'f2' => 'wP', 'g2' => 'wP', 'h2' => 'wP',
            'a8' => 'bR', 'b8' => 'bN', 'c8' => 'bB', 'd8' => 'bQ', 'e8' => 'bK', 'f8' => 'bB', 'g8' => 'bN', 'h8' => 'bR',
            'a7' => 'bP', 'b7' => 'bP', 'c7' => 'bP', 'd7' => 'bP', 'e7' => 'bP', 'f7' => 'bP', 'g7' => 'bP', 'h7' => 'bP',
        ];
        $b->castling = ['K' => true, 'Q' => true, 'k' => true, 'q' => true];

        return $b;
    }

    public static function fromFen(string $fen): self
    {
        $b = new self();
        $parts = preg_split('/\s+/', trim($fen));
        if ($parts === false || count($parts) < 4) {
            throw new \InvalidArgumentException('FEN invalide');
        }
        [$placement, $turn, $castling, $ep] = $parts;
        $b->squares = [];
        $ranks = explode('/', $placement);
        if (count($ranks) !== 8) {
            throw new \InvalidArgumentException('FEN invalide : 8 rangées attendues');
        }
        for ($i = 0; $i < 8; $i++) {
            $rank = 8 - $i;
            $file = 1;
            foreach (str_split($ranks[$i]) as $ch) {
                if (ctype_digit($ch)) {
                    $file += (int) $ch;
                    continue;
                }
                $color = ctype_upper($ch) ? 'w' : 'b';
                $b->squares[chr(96 + $file) . $rank] = $color . strtoupper($ch);
                $file++;
            }
        }
        $b->turn = $turn === 'b' ? 'b' : 'w';
        $b->castling = ['K' => false, 'Q' => false, 'k' => false, 'q' => false];
        if ($castling !== '-') {
            foreach (str_split($castling) as $c) {
                if (isset($b->castling[$c])) {
                    $b->castling[$c] = true;
                }
            }
        }
        $b->epSquare = ($ep !== '-' && preg_match('/^[a-h][36]$/', $ep)) ? $ep : null;
        $b->halfmove = isset($parts[4]) ? (int) $parts[4] : 0;
        $b->fullmove = isset($parts[5]) ? max(1, (int) $parts[5]) : 1;

        return $b;
    }

    public function fen(): string
    {
        $rows = [];
        for ($rank = 8; $rank >= 1; $rank--) {
            $row = '';
            $empty = 0;
            for ($file = 1; $file <= 8; $file++) {
                $piece = $this->squares[chr(96 + $file) . $rank] ?? null;
                if ($piece === null) {
                    $empty++;
                    continue;
                }
                if ($empty > 0) {
                    $row .= $empty;
                    $empty = 0;
                }
                $row .= $piece[0] === 'w' ? $piece[1] : strtolower($piece[1]);
            }
            if ($empty > 0) {
                $row .= $empty;
            }
            $rows[] = $row;
        }
        $castling = '';
        foreach (['K', 'Q', 'k', 'q'] as $c) {
            if ($this->castling[$c]) {
                $castling .= $c;
            }
        }

        return sprintf(
            '%s %s %s %s %d %d',
            implode('/', $rows),
            $this->turn,
            $castling === '' ? '-' : $castling,
            $this->epSquare ?? '-',
            $this->halfmove,
            $this->fullmove,
        );
    }

    public function turn(): string
    {
        return $this->turn;
    }

    public function pieceAt(string $square): ?string
    {
        return $this->squares[$square] ?? null;
    }

    /**
     * Coups légaux de la position, en notation UCI (ex. e2e4, e7e8q).
     *
     * @return array<int,string>
     */
    public function legalMovesUci(): array
    {
        $moves = [];
        foreach ($this->squares as $square => $piece) {
            if ($piece[0] !== $this->turn) {
                continue;
            }
            foreach ($this->pseudoMoves($piece, $square) as $target) {
                [$from, $to, $promoStr] = $target;
                $promo = $promoStr === '' ? null : $promoStr;
                if ($this->isLegal($from, $to, $promo)) {
                    $moves[] = $from . $to . $promoStr;
                }
            }
        }
        sort($moves);

        return $moves;
    }

    /** Conversion SAN -> UCI pour le prochain coup. */
    public function sanToUci(string $san): ?string
    {
        $san = $this->normalizeSan($san);
        foreach ($this->legalMovesUci() as $uci) {
            if ($this->uciToSan($uci) === $san || $this->uciToSan($uci, false) === $san) {
                return $uci;
            }
        }

        return null;
    }

    /** Conversion UCI -> SAN du prochain coup. */
    public function uciToSan(string $uci, bool $withCheck = true): string
    {
        $from = substr($uci, 0, 2);
        $to = substr($uci, 2, 2);
        $promo = strlen($uci) === 5 ? $uci[4] : null;
        $piece = $this->pieceAt($from);
        if ($piece === null) {
            return $uci;
        }
        $isCapture = $this->pieceAt($to) !== null || ($piece[1] === 'P' && $to === $this->epSquare);
        $san = '';
        if ($piece[1] === 'K' && abs(ord($from[0]) - ord($to[0])) === 2) {
            $san = $to[0] > $from[0] ? 'O-O' : 'O-O-O';
        } else {
            if ($piece[1] !== 'P') {
                $san .= $piece[1];
                if ($this->ambiguous($piece[1], $from, $to) !== null) {
                    $sameFile = false;
                    $sameRank = false;
                    $candidates = [];
                    foreach ($this->squares as $sq => $p) {
                        if ($p === $piece && $sq !== $from && $this->isLegal($sq, $to, null)) {
                            $candidates[] = $sq;
                        }
                    }
                    foreach ($candidates as $c) {
                        $sameFile = $sameFile || $c[0] === $from[0];
                        $sameRank = $sameRank || $c[1] === $from[1];
                    }
                    $san .= $sameFile && ! $sameRank ? $from[1] : $from[0];
                }
                if ($isCapture) {
                    $san .= 'x';
                }
            } elseif ($isCapture) {
                $san .= $from[0] . 'x';
            }
            $san .= $to;
            if ($promo !== null) {
                $san .= '=' . strtoupper($promo);
            }
        }
        $clone = clone $this;
        $clone->applyUnchecked($from, $to, $promo);
        if ($withCheck) {
            if ($clone->isCheck($clone->turn())) {
                $san .= $clone->isCheckmate($clone->turn()) ? '#' : '+';
            }
        }

        return $san;
    }

    public function applyUci(string $uci): bool
    {
        if (strlen($uci) < 4 || ! $this->isLegal(substr($uci, 0, 2), substr($uci, 2, 2), strlen($uci) === 5 ? $uci[4] : null)) {
            return false;
        }
        $this->applyUnchecked(substr($uci, 0, 2), substr($uci, 2, 2), strlen($uci) === 5 ? $uci[4] : null);

        return true;
    }

    public function applySan(string $san): bool
    {
        $uci = $this->sanToUci($san);
        if ($uci === null) {
            return false;
        }

        return $this->applyUci($uci);
    }

    /**
     * Le roi de $color est-il attaqué ? On scanne les pseudo-coups adverses
     * ciblant la case du roi : une pièce clouée attaque quand même, on
     * raisonne donc en pseudo-coups (attaques) et non en coups légaux.
     */
    public function isCheck(string $color): bool
    {
        $kingSquare = null;
        foreach ($this->squares as $sq => $piece) {
            if ($piece === $color . 'K') {
                $kingSquare = $sq;
                break;
            }
        }
        if ($kingSquare === null) {
            return false;
        }
        $opponent = $color === 'w' ? 'b' : 'w';
        foreach ($this->squares as $square => $piece) {
            if ($piece[0] !== $opponent) {
                continue;
            }
            foreach ($this->pseudoMoves($piece, $square) as $target) {
                if ($target[1] === $kingSquare) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isCheckmate(string $color): bool
    {
        return $this->isCheck($color) && $this->legalMovesUci() === [];
    }

    public function isStalemate(): bool
    {
        return ! $this->isCheck($this->turn) && $this->legalMovesUci() === [];
    }

    /** @return array<string,string> */
    public function squares(): array
    {
        return $this->squares;
    }

    // --- interne ---

    private function normalizeSan(string $san): string
    {
        $san = str_replace(['0-0-0', '0-0'], ['O-O-O', 'O-O'], trim($san));
        $san = str_replace(['+', '#', '!?', '?', '†', 'ep'], '', $san);

        return preg_replace('/\s+/', '', $san) ?? '';
    }

    private function ambiguous(string $pieceType, string $from, string $to): ?string
    {
        $found = [];
        foreach ($this->squares as $sq => $piece) {
            if ($piece === $this->turn . $pieceType && $sq !== $from && $this->isLegal($sq, $to, null)) {
                $found[] = $sq;
            }
        }

        return $found === [] ? null : 'ambiguous';
    }

    private function isLegal(string $from, string $to, ?string $promo): bool
    {
        $promo = $promo === '' ? null : $promo;
        if (! isset($this->squares[$from])) {
            return false;
        }
        $ok = false;
        foreach ($this->pseudoMoves($this->squares[$from], $from) as $candidate) {
            if ($candidate[0] === $from && $candidate[1] === $to) {
                if ($promo !== null) {
                    if ($candidate[2] === $promo) {
                        $ok = true;
                        break;
                    }
                } elseif ($candidate[2] === '') {
                    $ok = true;
                    break;
                }
            }
        }
        if (! $ok) {
            return false;
        }
        $clone = clone $this;
        $clone->applyUnchecked($from, $to, $promo);

        return ! $clone->isCheck($this->turn);
    }

    /**
     * Pseudo-coups de la pièce (sans filtrage du roi en échec).
     *
     * @return array<int,array{0:string,1:string,2:string}> [from, to, promo]
     */
    private function pseudoMoves(string $piece, string $from): array
    {
        $moves = [];
        $color = $piece[0];
        $type = $piece[1];
        $file = ord($from[0]) - 96;
        $rank = (int) $from[1];

        switch ($type) {
            case 'P':
                $this->collectPawnMoves($from, $color, $file, $rank, $moves);
                break;
            case 'N':
                $this->collectLeaps($from, $color, $file, $rank, [[1, 2], [2, 1], [-1, 2], [-2, 1], [1, -2], [2, -1], [-1, -2], [-2, -1]], $moves);
                break;
            case 'B':
                $this->collectSliding($from, $color, [[1, 1], [1, -1], [-1, 1], [-1, -1]], $moves);
                break;
            case 'R':
                $this->collectSliding($from, $color, [[1, 0], [-1, 0], [0, 1], [0, -1]], $moves);
                break;
            case 'Q':
                $this->collectSliding($from, $color, [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]], $moves);
                break;
            case 'K':
                $this->collectLeaps($from, $color, $file, $rank, [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]], $moves);
                $this->collectCastling($from, $color, $moves);
                break;
        }

        return $moves;
    }

    /** @param array<int,string> $moves */
    private function collectPawnMoves(string $from, string $color, int $file, int $rank, array &$moves): void
    {
        $dir = $color === 'w' ? 1 : -1;
        $start = $color === 'w' ? 2 : 7;
        $last = $color === 'w' ? 8 : 1;
        $one = chr(96 + $file) . ($rank + $dir);
        if ($rank + $dir >= 1 && $rank + $dir <= 8 && $this->pieceAt($one) === null) {
            if ($rank + $dir === $last) {
                foreach (['q', 'r', 'b', 'n'] as $p) {
                    $moves[] = [$from, $one, $p];
                }
            } else {
                $moves[] = [$from, $one, ''];
                if ($rank === $start) {
                    $two = chr(96 + $file) . ($rank + 2 * $dir);
                    if ($this->pieceAt($two) === null) {
                        $moves[] = [$from, $two, ''];
                    }
                }
            }
        }
        foreach ([-1, 1] as $df) {
            $nf = $file + $df;
            $nr = $rank + $dir;
            if ($nf < 1 || $nf > 8 || $nr < 1 || $nr > 8) {
                continue;
            }
            $target = chr(96 + $nf) . $nr;
            $captured = $this->pieceAt($target);
            if ($captured !== null && $captured[0] !== $color) {
                if ($nr === $last) {
                    foreach (['q', 'r', 'b', 'n'] as $p) {
                        $moves[] = [$from, $target, $p];
                    }
                } else {
                    $moves[] = [$from, $target, ''];
                }
            } elseif ($target === $this->epSquare && $captured === null) {
                $moves[] = [$from, $target, ''];
            }
        }
    }

    /** @param array<int,array{0:int,1:int}> $deltas @param array<int,string> $moves */
    private function collectLeaps(string $from, string $color, int $file, int $rank, array $deltas, array &$moves): void
    {
        foreach ($deltas as [$df, $dr]) {
            $nf = $file + $df;
            $nr = $rank + $dr;
            if ($nf < 1 || $nf > 8 || $nr < 1 || $nr > 8) {
                continue;
            }
            $target = chr(96 + $nf) . $nr;
            $captured = $this->pieceAt($target);
            if ($captured === null || $captured[0] !== $color) {
                $moves[] = [$from, $target, ''];
            }
        }
    }

    /** @param array<int,array{0:int,1:int}> $dirs @param array<int,string> $moves */
    private function collectSliding(string $from, string $color, array $dirs, array &$moves): void
    {
        $file = ord($from[0]) - 96;
        $rank = (int) $from[1];
        foreach ($dirs as [$df, $dr]) {
            $dist = 1;
            while (true) {
                $nf = $file + $df * $dist;
                $nr = $rank + $dr * $dist;
                if ($nf < 1 || $nf > 8 || $nr < 1 || $nr > 8) {
                    break;
                }
                $target = chr(96 + $nf) . $nr;
                $captured = $this->pieceAt($target);
                if ($captured !== null && $captured[0] === $color) {
                    break; // pièce alliée : la case est inatteignable
                }
                $moves[] = [$from, $target, ''];
                if ($captured !== null) {
                    break; // prise : la marche s'arrête après
                }
                $dist++;
            }
        }
    }

    /** @param array<int,string> $moves */
    private function collectCastling(string $from, string $color, array &$moves): void
    {
        $homeRank = $color === 'w' ? '1' : '8';
        if ($from !== 'e' . $homeRank) {
            return; // roque impossible si le roi n'est pas sur sa case de départ
        }
        $rookSquares = $color === 'w' ? ['K' => 'h1', 'Q' => 'a1'] : ['k' => 'h8', 'q' => 'a8'];
        $keys = $color === 'w' ? ['K', 'Q'] : ['k', 'q'];
        foreach ($keys as $key) {
            if (! $this->castling[$key]) {
                continue;
            }
            $rookSq = $rookSquares[$key];
            if ($this->pieceAt($rookSq) !== $color . 'R') {
                continue;
            }
            $isKingside = in_array($key, ['K', 'k'], true);
            $between = $isKingside
                ? ['f' . $homeRank, 'g' . $homeRank]
                : ['d' . $homeRank, 'c' . $homeRank, 'b' . $homeRank];
            $clear = true;
            foreach ($between as $sq) {
                if ($this->pieceAt($sq) !== null) {
                    $clear = false;
                    break;
                }
            }
            if (! $clear) {
                continue;
            }
            $kingTo = ($isKingside ? 'g' : 'c') . $homeRank;
            $path = $isKingside
                ? ['e' . $homeRank, 'f' . $homeRank, 'g' . $homeRank]
                : ['e' . $homeRank, 'd' . $homeRank, 'c' . $homeRank];
            $safe = true;
            foreach ($path as $sq) {
                $probe = clone $this;
                $probe->squares = array_diff_key($probe->squares, ['e' . $homeRank => 1]);
                $probe->squares[$sq] = $color . 'K';
                // Une sonde ne teste que des attaques : on neutralise les droits
                // de roque pour éviter toute régénération de roques en cascade.
                $probe->castling = ['K' => false, 'Q' => false, 'k' => false, 'q' => false];
                if ($probe->isCheck($color)) {
                    $safe = false;
                    break;
                }
            }
            if ($safe) {
                $moves[] = [$from, $kingTo, ''];
            }
        }
    }

    private function applyUnchecked(string $from, string $to, ?string $promo): void
    {
        $promo = $promo === '' ? null : $promo;
        $piece = $this->squares[$from] ?? null;
        if ($piece === null) {
            throw new \InvalidArgumentException('coup invalide : case source vide');
        }
        $capture = ($this->squares[$to] ?? null) !== null;
        $color = $piece[0];
        $type = $piece[1];

        if ($type === 'P' && $to === $this->epSquare && ! $capture) {
            $capSq = $to[0] . ($color === 'w' ? (int) $to[1] - 1 : (int) $to[1] + 1);
            unset($this->squares[$capSq]);
        }

        $this->epSquare = null;
        if ($type === 'P' && abs((int) $to[1] - (int) $from[1]) === 2) {
            $this->epSquare = $to[0] . ((int) $to[1] + ($color === 'w' ? -1 : 1));
        }

        unset($this->squares[$from]);
        $this->squares[$to] = $promo !== null ? $color . strtoupper($promo) : $piece;

        if ($type === 'K') {
            if ($color === 'w') {
                $this->castling['K'] = false;
                $this->castling['Q'] = false;
            } else {
                $this->castling['k'] = false;
                $this->castling['q'] = false;
            }
            if (abs(ord($to[0]) - ord($from[0])) === 2) {
                $rookFrom = $to[0] > $from[0] ? 'h' . $from[1] : 'a' . $from[1];
                $rookTo = $to[0] > $from[0] ? 'f' . $from[1] : 'd' . $from[1];
                $rook = $this->squares[$rookFrom] ?? null;
                unset($this->squares[$rookFrom]);
                if ($rook !== null) {
                    $this->squares[$rookTo] = $rook;
                }
            }
        }
        foreach (['a1' => 'Q', 'h1' => 'K', 'a8' => 'q', 'h8' => 'k'] as $sq => $flag) {
            if ($from === $sq || $to === $sq) {
                $this->castling[$flag] = false;
            }
        }

        if ($type === 'P' || $capture) {
            $this->halfmove = 0;
        } else {
            $this->halfmove++;
        }
        if ($color === 'b') {
            $this->fullmove++;
        }
        $this->turn = $color === 'w' ? 'b' : 'w';
    }
}
