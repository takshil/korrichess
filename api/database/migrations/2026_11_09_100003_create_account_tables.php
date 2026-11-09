<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables du compte joueur (C1–C4, RGPD #18, paramètres #10).
     * Les clés étrangères suppriment en cascade : la suppression RGPD du
     * compte emporte toutes ses données relationnelles.
     */
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('display_name')->default('');
            $table->string('club')->default('');
            $table->unsignedInteger('rating')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('linked_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('username', 64);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'platform', 'username']);
        });

        Schema::create('consents', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->primary(['user_id', 'kind']);
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('language', 5)->default('fr');
            $table->string('theme', 20)->default('clair');
            $table->string('board_style', 20)->default('classique');
            $table->string('piece_style', 20)->default('classique');
            $table->boolean('notify_analysis_done')->default(true);
            $table->boolean('notify_exercise_reminder')->default(true);
            $table->boolean('notify_games_synced')->default(true);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('linked_accounts');
        Schema::dropIfExists('player_profiles');
    }
};
