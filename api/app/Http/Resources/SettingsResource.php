<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue JSON des paramètres du joueur (issue #10).
 *
 * @property UserSetting $resource
 */
final class SettingsResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

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
}
