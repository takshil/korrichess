<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

/*
|--------------------------------------------------------------------------
| Synchronisation automatique (L7)
|--------------------------------------------------------------------------
| En hébergement mutualisé, aucun worker de queue permanent n'est possible
| (ADR-003) : le cron cPanel déclenche `php artisan schedule:run` chaque
| minute, et le scheduler joue ici deux rôles :
|   1. la sync nocturne de tous les comptes liés, vers 3 h du matin ;
|   2. un worker de queue par vagues de 90 s toutes les 5 minutes, qui traite
|      les jobs enfilés par le bouton « Synchroniser » de l'espace.
|
| NOTE (!41) : ces deux rôles sont AUSSI remplis par le middleware
| DrainQueueAfterResponse, qui enfile la sync quotidienne à la première
| visite après 3 h et vide la file après chaque requête. Le cron reste
| en filet de sécurité : la sync quotidienne est idempotente (curseurs
| incrémentaux), un double passage ne coûte que des requêtes API.
*/
Schedule::command('korrichess:sync-all')->dailyAt('03:00');
Schedule::command('queue:work --stop-when-empty --max-time=90 --timeout=900')->everyFiveMinutes()->withoutOverlapping();
