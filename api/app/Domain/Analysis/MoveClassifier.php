<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

use App\Domain\Chess\Board;

/**
 * A4 : classe un coup d'après la perte d'évaluation qu'il provoque, et
 * délègue l'explication (A7) à MoveExplainer.
 */
final class MoveClassifier
{
    /** Seuils de perte, en centipions (du plus clément au plus sévère). */
    private const THRESHOLDS = [
        'meilleur' => 10,
        'bon' => 50,
        'imprecision' => 100,
        'erreur' => 250,
    ];

    private const WORST = 'gaffe';

    public function __construct(private readonly MoveExplainer $explainer)
    {
    }

    /**
     * @param string  $fenBefore  position avant le coup
     * @param string  $played     coup joué (UCI)
     * @param int     $evalBefore évaluation (cp, point de vue des blancs) avant le coup
     * @param int     $evalAfter  évaluation après le coup
     * @param ?string $best       meilleur coup du moteur (UCI)
     */
    public function classify(string $fenBefore, string $played, int $evalBefore, int $evalAfter, ?string $best): MoveVerdict
    {
        $board = Board::fromFen($fenBefore);
        $bestSan = $best !== null ? $board->uciToSan($best) : null;

        if ($best !== null && $played === $best) {
            return new MoveVerdict('meilleur', null, $bestSan);
        }

        // Évaluation du point de vue du joueur au trait : blanc veut +, noir veut −.
        $loss = $board->turn() === 'w' ? ($evalBefore - $evalAfter) : ($evalAfter - $evalBefore);
        $classification = $this->categoryFor($loss);

        $explanation = in_array($classification, ['meilleur', 'bon'], true)
            ? null
            : $this->explainer->explain($board, $played, $best, $loss);

        return new MoveVerdict($classification, $explanation, $bestSan);
    }

    private function categoryFor(int $loss): string
    {
        foreach (self::THRESHOLDS as $label => $threshold) {
            if ($loss <= $threshold) {
                return $label;
            }
        }

        return self::WORST;
    }
}
