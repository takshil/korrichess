<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Models\UserSetting;

/**
 * Paramètres du joueur (issue #10) : langue (i18n fr/en), thème clair/sombre,
 * styles d'échiquier et de pièces, notifications désactivables. Les valeurs
 * autorisées sont validées en amont par UpdateSettingsRequest.
 */
final class SettingsService
{
    private const CHOICE_FIELDS = ['language', 'theme', 'board_style', 'piece_style'];
    private const FLAG_FIELDS = ['notify_analysis_done', 'notify_exercise_reminder', 'notify_games_synced'];

    public function get(int $userId): UserSetting
    {
        return UserSetting::query()->firstOrCreate(['user_id' => $userId]);
    }

    /**
     * @param array<string,mixed> $input champs validés
     */
    public function update(int $userId, array $input): UserSetting
    {
        $setting = $this->get($userId);

        $changes = [];
        foreach (self::CHOICE_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = (string) $input[$field];
            }
        }
        foreach (self::FLAG_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = in_array($input[$field], [true, 1, '1'], true);
            }
        }
        if ($changes !== []) {
            $setting->fill($changes)->save();
        }

        return $setting;
    }
}
