<?php

declare(strict_types=1);

namespace Korrichess\Api\Domain\Settings;

use Korrichess\Api\Core\Database;

final class SettingsService
{
    private const THEMES = ['clair', 'sombre'];
    private const LANGUAGES = ['fr', 'en'];
    private const BOARD_STYLES = ['classique', 'bois', 'contraste'];
    private const PIECE_STYLES = ['classique', 'minimaliste'];

    /** @return array<string,mixed> */
    public function get(int $userId): array
    {
        $row = Database::one('SELECT * FROM user_settings WHERE user_id = :u', ['u' => $userId]);
        if ($row === null) {
            Database::run(
                'INSERT INTO user_settings (user_id, updated_at) VALUES (:u, :now)',
                ['u' => $userId, 'now' => gmdate('c')],
            );
            $row = Database::one('SELECT * FROM user_settings WHERE user_id = :u', ['u' => $userId]);
        }
        return [
            'language' => $row['language'],
            'theme' => $row['theme'],
            'board_style' => $row['board_style'],
            'piece_style' => $row['piece_style'],
            'notifications' => [
                'analysis_done' => ((int) $row['notify_analysis_done']) === 1,
                'exercise_reminder' => ((int) $row['notify_exercise_reminder']) === 1,
                'games_synced' => ((int) $row['notify_games_synced']) === 1,
            ],
        ];
    }

    /** @return array{status:int,body:array} */
    public function update(int $userId, array $input): array
    {
        $this->get($userId);
        $map = [
            'language' => ['language', self::LANGUAGES],
            'theme' => ['theme', self::THEMES],
            'board_style' => ['board_style', self::BOARD_STYLES],
            'piece_style' => ['piece_style', self::PIECE_STYLES],
            'notify_analysis_done' => ['notify_analysis_done', null],
            'notify_exercise_reminder' => ['notify_exercise_reminder', null],
            'notify_games_synced' => ['notify_games_synced', null],
        ];
        $fields = [];
        $params = ['u' => $userId, 'now' => gmdate('c')];
        foreach ($map as $key => [$column, $allowed]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if ($allowed === null) {
                $params[$column] = in_array($value, [true, 1, '1'], true) ? 1 : 0;
            } else {
                $value = (string) $value;
                if (!in_array($value, $allowed, true)) {
                    return ['status' => 422, 'body' => ['error' => 'valeur_non_autorisee', 'champ' => $key]];
                }
                $params[$column] = $value;
            }
            $fields[] = "$column = :$column";
        }
        if ($fields !== []) {
            Database::run('UPDATE user_settings SET ' . implode(', ', $fields) . ', updated_at = :now WHERE user_id = :u', $params);
        }
        return ['status' => 200, 'body' => $this->get($userId)];
    }
}
