# Cron mutualisé (o2switch / cPanel) — scheduler Korrichess

## Pourquoi

En hébergement FTP mutualisé (ADR-003), aucun processus permanent n'est possible :
pas de `php artisan queue:work` ni de `schedule:work` en continu. Le scheduler
Laravel est donc piloté par **une seule tâche cron cPanel**, qui déclenche
`php artisan schedule:run` chaque minute. Le scheduler enchaîne alors :

- `korrichess:sync-all` vers 3 h du matin : sync incrémentale de tous les comptes liés ;
- `queue:work --stop-when-empty --max-time=90` toutes les 5 minutes : traite les
  jobs enfilés par le bouton « Synchroniser » de l'espace joueur.

## Mise en place (cPanel → Tâches Cron)

Chemin de l'API sur le compte (à adapter si l'arborescence change) :
`/home/CLCY/korrichess.clemenceau.email/korrideploy/korrichess-api`

Tâche, chaque minute :

```cron
* * * * * cd /home/CLCY/korrichess.clemenceau.email/korrideploy/korrichess-api && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Remplacer `CLCY` par le préfixe du compte et vérifier le binaire PHP :

- `/opt/cpanel/ea-php83/root/usr/bin/php` (EasyApache, PHP 8.3) ;
- à défaut, `/usr/local/bin/php` ou `/opt/alt/php83/usr/bin/php` selon le serveur.

Vérification : `storage/logs/scheduler.log` doit montrer, chaque minute, la trace
`Running scheduled command` (ou l'absence de tâche due). Un envoi de mail cron
peut aussi être configuré le temps du diagnostic.

## Notes

- `QUEUE_CONNECTION=database` (déjà en prod) : les jobs vivent dans la table
  `jobs`, traitée par vagues toutes les 5 minutes — un clic « Synchroniser »
  importe donc ses parties en moins de ~5 minutes.
- Le `scheduler.log` grossit : le prune par `model:prune` ne le gère pas ; le
  vider périodiquement dans le gestionnaire de fichiers suffit.
