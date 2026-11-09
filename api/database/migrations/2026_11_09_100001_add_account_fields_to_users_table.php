<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // C1 : le mot de passe peut être absent si un connecteur
            // Google/Apple est utilisé (colonnes ci-dessous, prévus au cahier
            // des charges mais non implémentés pour l'instant).
            $table->string('password')->nullable()->change();
            $table->string('google_sub')->nullable()->index();
            $table->string('apple_sub')->nullable()->index();
            $table->string('role', 20)->default('player');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['google_sub']);
            $table->dropIndex(['apple_sub']);
            $table->dropColumn(['google_sub', 'apple_sub', 'role']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
