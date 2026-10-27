# ADR-002 — Intégration du moteur d'analyse (Stockfish) côté serveur

- Statut : accepté
- Date : 2025 (issue #5 — Rejeu et analyse des parties, A2–A10 ; issue #15 — Moteur d'analyse Stockfish)
- Contexte : cahier des charges Korrichess v1.1, section 6.1 ; AGENTS.md signale la licence GPL de Stockfish comme point sensible.

## Contexte

Korrichess analyse chaque partie coup par coup (évaluation, courbe, classement
des coups, meilleur coup, variante, explications). Stockfish est le moteur de
référence, mais il est distribué sous **GPL v3** : l'embarquer dans l'API PHP
(ou dans l'application mobile) imposerait des obligations de source à toute
l'application. Le cahier des charges exige en outre que l'analyse passe par la
file de tâches (quelques minutes au plus par partie).

## Options envisagées

1. **Compiler/appeler Stockfish comme bibliothèque embarquée** dans l'API :
   rejeté — la GPL contaminerait le binaire de l'API ; couplage fort à une
   version précise du moteur.
2. **Analyse côté client uniquement** : rejeté — les P1 A2–A6 exigent une
   analyse complète en quelques minutes pour des parties longues, impossible
   de manière fiable sur un téléphone d'adolescent.
3. **Moteur comme processus externe standard UCI, invoqué par l'API via la
   file de tâches, derrière une interface interne remplaçable** : retenu.

## Décision

- Le moteur tourne **en processus séparé** sur le serveur, dialoguant via le
  protocole **UCI** (`api/app/Domain/Analysis/UciEngine.php`). L'API n'établit
  aucun lien binaire avec lui : l'analyse est un échange de texte par tubes,
  ce qui préserve l'indépendance des licences (processus séparé, appelé par
  son nom configurable `KORRI_STOCKFISH_PATH`).
- Toute l'analyse passe par l'interface `EngineClient`
  (`api/app/Domain/Analysis/EngineClient.php`). L'application ne dépend jamais
  d'une implémentation concrète : `HeuristicEngine` (évaluations
  déterministes sans processus externe) sert aux tests et aux environnements
  sans moteur ; `UciEngine` appelle le binaire configuré en production.
- Les analyses sont **asynchrones** : la requête HTTP enfile un job
  (`analyse`), le worker (`api/queue-handlers/analyse.php`) exécute l'analyse
  et persiste les résultats dans `game_analyses` / `analysis_moves`.
- Aucune distribution du binaire Stockfish n'est faite par ce dépôt : le
  déploiement l'installe séparément (paquet système ou téléchargement
  officiel). Sa mise à disposition publique par le projet devra respecter la
  GPL (fournir les sources du moteur) — documenté pour l'issue #15.

## Conséquences

- Le chemin du binaire est configurable ; en son absence l'API reste
  fonctionnelle (rejeu A1, exploration A8) et l'analyse renvoie une erreur
  explicite plutôt que de planter.
- Les tests automatisés n'ont pas besoin de Stockfish : `HeuristicEngine`
  garantit des résultats reproductibles.
- Un déploiement réel devra dimensionner le pool de workers (une partie =
  plusieurs centaines de positions × quelques dizaines de ms).
