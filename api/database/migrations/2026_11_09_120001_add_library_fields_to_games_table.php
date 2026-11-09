<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annotations de parties (issue #4) : favori, note personnelle et
     * étiquettes sur la table games (B4). Nullable pour rester portable
     * (MySQL refuse un DEFAULT sur TEXT avant 8.0.13).
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->boolean('favorite')->default(false);
            $table->text('personal_note')->nullable();
            $table->text('tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['favorite', 'personal_note', 'tags']);
        });
    }
};
