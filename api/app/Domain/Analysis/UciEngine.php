<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use InvalidArgumentException;

/**
 * Moteur UCI (Stockfish) en processus externe, dialogue par tubes (ADR-002).
 * Le binaire n'est jamais embarqué ni distribué : chemin configurable.
 */
final class UciEngine implements EngineClient
{
    /** @var array{proc:resource,pipes:array{0:resource,1:resource,2:resource}}|null */
    private ?array $proc = null;

    public function __construct(private readonly string $binary, private readonly int $movetimeMs = 1000)
    {
        if ($binary === '' || (!is_executable($binary) && !self::resolvable($binary))) {
            throw new InvalidArgumentException('moteur_indisponible');
        }
    }

    private static function resolvable(string $binary): bool
    {
        $escaped = escapeshellcmd($binary);
        $out = shell_exec('command -v ' . $escaped . ' 2>/dev/null');

        return is_string($out) && trim($out) !== '';
    }

    /**
     * @return array{eval_cp:int,best_move:?string,variation:array<int,string>}
     */
    public function evaluate(string $fen, int $depth = 12): array
    {
        $this->ensureStarted();
        [$pipes, $proc] = $this->proc();
        [$in, $out] = $pipes;

        fwrite($in, "position fen $fen\n");
        fwrite($in, 'go depth ' . max(1, min(20, $depth)) . ' movetime ' . $this->movetimeMs . "\n");

        $eval = null;
        $best = null;
        $variation = [];
        $start = microtime(true);
        while ((microtime(true) - $start) < $this->movetimeMs / 1000 + 30) {
            $line = (string) fgets($out);
            if ($line === '') {
                continue;
            }
            $line = trim($line);
            if (str_starts_with($line, 'info') && str_contains($line, ' pv ')) {
                if (preg_match('/score cp (-?\d+)/', $line, $m) === 1) {
                    $eval = (int) $m[1];
                } elseif (preg_match('/score mate (-?\d+)/', $line, $m2) === 1) {
                    $eval = ($m2[1] >= 0 ? 1 : -1) * (100000 - abs((int) $m2[1]));
                }
                $pv = substr($line, (int) strpos($line, ' pv ') + 4);
                $variation = preg_split('/\s+/', trim((string) $pv)) ?: [];
            }
            if (str_starts_with($line, 'bestmove')) {
                $parts = explode(' ', $line);
                $best = $parts[1] ?? null;
                break;
            }
        }

        return [
            'eval_cp' => $eval ?? 0,
            'best_move' => ($best !== null && $best !== '(none)') ? $best : null,
            'variation' => array_slice($variation, 0, 12),
        ];
    }

    public function name(): string
    {
        return 'uci:' . basename($this->binary);
    }

    private function ensureStarted(): void
    {
        if ($this->proc !== null) {
            return;
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $proc = proc_open($this->binary, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new InvalidArgumentException('moteur_indisponible');
        }
        $this->proc = ['proc' => $proc, 'pipes' => $pipes];
        fwrite($pipes[0], "uci\n");
        $started = microtime(true);
        while ((microtime(true) - $started) < 5) {
            $line = (string) fgets($pipes[1]);
            if ($line === '') {
                continue;
            }
            if (trim($line) === 'uciok') {
                fwrite($pipes[0], "isready\n");
                continue;
            }
            if (trim($line) === 'readyok') {
                return;
            }
        }
        throw new InvalidArgumentException('moteur_indisponible');
    }

    /**
     * @return array{0:array{0:resource,1:resource,2:resource},1:resource}
     */
    private function proc(): array
    {
        if ($this->proc === null) {
            $this->ensureStarted();
        }
        $pipes = $this->proc['pipes'];

        return [[$pipes[0], $pipes[1], $pipes[2]], $this->proc['proc']];
    }

    public function __destruct()
    {
        if ($this->proc === null) {
            return;
        }
        $pipes = $this->proc['pipes'];
        fwrite($pipes[0], "quit\n");
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($this->proc['proc']);
        $this->proc = null;
    }
}
