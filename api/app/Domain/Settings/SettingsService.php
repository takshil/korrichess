<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Models\UserSetting;

/**
 * Paramètres du joueur (issue #10) : langue (i18n fr/en), thème clair/sombre,
 * styles d'échiquier et de pièces, notifications désactivables.
 */
final class SettingsService
{
    private const THEMES = ['clair', 'sombre'];
    private const LANGUAGES = ['fr', 'en'];
    private const BOARD_STYLES = ['classique', 'bois', 'contraste'];
    private const PIECE_STYLES = ['classique', 'minimaliste'];

    /** @return array<string,mixed> */
    public function get(int $userId): array
    {
        $setting = $this->row($userId);

        return [
            'language' => $setting->language,
            'theme' => $setting->theme,
            'board_style' => $setting->board_style,
            'piece_style' => $setting->piece_style,
            'notifications' => [
                'analysis_done' => $setting->notify_analysis_done,
                'exercise_reminder' => $setting->notify_exercise_reminder,
                'games_synced' => $setting->notify_games_synced,
            ],
        ];
    }

    /** @return array{status:int,body:array} */
    public function update(int $userId, array $input): array
    {
        $setting = $this->row($userId);

        $map = [
            'language' => ['language', self::LANGUAGES],
            'theme' => ['theme', self::THEMES],
            'board_style' => ['board_style', self::BOARD_STYLES],
            'piece_style' => ['piece_style', self::PIECE_STYLES],
            'notify_analysis_done' => ['notify_analysis_done', null],
            'notify_exercise_reminder' => ['notify_exercise_reminder', null],
            'notify_games_synced' => ['notify_games_synced', null],
        ];

        $changes = [];
        foreach ($map as $key => [$column, $allowed]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if ($allowed === null) {
                $changes[$column] = in_array($value, [true, 1, '1'], true);
            } else {
                $value = (string) $value;
                if (!in_array($value, $allowed, true)) {
                    return ['status' => 422, 'body' => ['error' => 'valeur_non_autorisee', 'champ' => $key]];
                }
                $changes[$column] = $value;
            }
        }

        if ($changes !== []) {
            $setting->fill($changes)->save();
        }

        return ['status' => 200, 'body' => $this->get($userId)];
    }

    private function row(int $userId): UserSetting
    {
        return UserSetting::query()->firstOrCreate(['user_id' => $userId]);
    }
}
