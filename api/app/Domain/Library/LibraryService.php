<?php

declare(strict_types=1);

namespace App\Domain\Library;

use App\Domain\Games\GameRepository;
use App\Domain\Shared\DomainError;
use App\Models\Game;

/**
 * Bibliothèque de parties (issue #4, B1–B4) : liste avec indication de la
 * source, filtres, recherche texte, détail, notes / étiquettes / favoris.
 * L'export PGN (B5) et la recherche par position (B6) ont leurs propres
 * services : PgnExporter et PositionSearch.
 */
final class LibraryService
{
    private const NOTE_MAX_LENGTH = 2000;
    private const TAG_MAX_LENGTH = 32;
    private const TAGS_MAX_COUNT = 20;

    public function __construct(private readonly GameRepository $games)
    {
    }

    /**
     * B1 + B2 : liste des parties avec filtres, tri et pagination.
     *
     * @param array<string,mixed> $filters
     */
    public function list(int $userId, array $filters = [], int $limit = 50, int $offset = 0, string $sort = 'played_at', string $order = 'desc'): GamePage
    {
        return $this->games->page($userId, $filters, $limit, $offset, $sort, $order);
    }

    /**
     * B1 : détail d'une partie.
     *
     * @throws DomainError partie introuvable
     */
    public function detail(int $userId, int $gameId): Game
    {
        return $this->games->findOwnedOrFail($userId, $gameId);
    }

    /**
     * B4 : note personnelle, étiquettes, favori.
     *
     * @param array<string,mixed> $input
     *
     * @throws DomainError partie introuvable, ou saisie invalide / vide
     */
    public function annotate(int $userId, int $gameId, array $input): Game
    {
        $game = $this->games->findOwnedOrFail($userId, $gameId);

        $touched = false;
        if (array_key_exists('personal_note', $input)) {
            $game->personal_note = $this->cleanNote($input['personal_note']);
            $touched = true;
        }
        if (array_key_exists('tags', $input)) {
            $game->tags = implode(',', $this->cleanTags($input['tags']));
            $touched = true;
        }
        if (array_key_exists('favorite', $input)) {
            $game->favorite = (bool) $input['favorite'];
            $touched = true;
        }
        if (!$touched) {
            throw DomainError::invalid('rien_a_mettre_a_jour');
        }

        $game->save();

        return $game;
    }

    private function cleanNote(mixed $note): string
    {
        $note = (string) $note;
        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw DomainError::invalid('note_trop_longue');
        }

        return $note;
    }

    /** @return list<string> */
    private function cleanTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            throw DomainError::invalid('etiquettes_invalides');
        }

        $clean = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || mb_strlen($tag) > self::TAG_MAX_LENGTH) {
                throw DomainError::invalid('etiquette_invalide');
            }
            $clean[] = $tag;
        }

        return array_slice(array_values(array_unique($clean)), 0, self::TAGS_MAX_COUNT);
    }
}
