# AGENTS.md — Korrichess

Application mobile + site d'analyse de parties d'échecs. Ce fichier guide tout agent de code (Vibe Code, etc.) travaillant sur ce dépôt.

## Contexte produit

Korrichess (korrigan + chess) centralise **toutes** les parties d'un joueur : tournois sur papier (scan de feuille de partie) et parties en ligne (Lichess, Chess.com). L'application les analyse (Stockfish), génère des exercices personnalisés issus des erreurs du joueur, et mesure sa progression.

- **Ton produit** : tutoiement, complice, encourageant, humour. Les gaffes sont dédramatisées. Mascotte : korrigan malicieux.
- **Public prioritaire** : adolescents (version gratuite). Payeurs à terme : entraîneurs, clubs, Pack 5.
- **Langue produit** : français au lancement, structure prête pour l'internationalisation (clés de traduction dès le premier écran, pas de texte en dur).
- Référence complète : « Cahier des charges – Korrichess v1.1 » (PDF) et les issues GitLab #1 à #21 (les numéros C1–W8 ci-dessous y correspondent).

## Stack technique (choix retenus)

| Composant | Technologie |
| --- | --- |
| Application mobile (Android + iOS, base unique) | **Flutter** |
| API back-end | **PHP** (Laravel ou Symfony) |
| Site web | Application web connectée à la même API |
| Base de données | SQL (parties en PGN + données structurées, analyses, statistiques, exercices) |
| Moteur d'analyse | **Stockfish** côté serveur (⚠️ licence GPL : vérifier les obligations avant intégration/distribution) |
| File de tâches | Analyses moteur et synchronisations en arrière-plan |

## Structure cible du monorepo

```
/
├── app/        # Flutter (mobile)
├── api/        # Back-end PHP (API REST)
├── web/        # Site (vitrine + espace personnel)
└── docs/       # Cahier des charges, décisions (ADR)
```

## Roadmap (ne pas anticiper les versions suivantes)

- **V1 – Noyau (MVP)** : compte et profil (C1–C4), sync Lichess/Chess.com (L1–L6, L8), bibliothèque (B1–B2), rejeu (A1), saisie manuelle sur échiquier interactif (S5, S7), site vitrine + espace de consultation (W1–W4, W7–W8).
- **V2 – Parties de tournoi** : analyse moteur (A2–A6, A10), scan de feuilles avec correction (S1–S4, S6, S9), import PGN (S8), tableau de bord progression (P1–P2).
- **V3 – Progression** : exercices personnalisés (E1–E6), statistiques avancées (P3–P7), abonnement payant, offre entraîneur (T2–T4).
- **V4 – Aller plus loin** : Pack 5 (C5), offre club, répertoire d'ouvertures (R1–R3), autres langues.

Priorités du cahier des charges : **P1** indispensable au lancement, **P2** important, **P3** plus tard. Un ticket P1 ne doit pas être bloqué par du P2/P3.

## Règles de travail pour l'agent

1. **Travailler issue par issue.** Une branche + une MR par issue GitLab. Citer le numéro d'issue dans le message de commit et la MR (ex. `Closes #3`).
2. **Ne pas sortir du périmètre** : pas de jeu en ligne, pas d'appariement de tournois, pas de réseau social (exclusions du cahier des charges).
3. **Conventions Flutter** : null safety stricte, état via un pattern simple (Riverpod ou équivalent, à documenter dans `app/README.md`), thèmes clair/sombre dès le départ, échiquier lisible (composant réutilisable).
4. **Conventions API** : REST, JSON, versionnement de route (`/api/v1`), migrations de base de données versionnées, aucune logique métier dans les contrôleurs.
5. **Sécurité et RGPD par défaut** : hachage des mots de passe, chiffrement des échanges, minimisation des données, export et suppression de compte (C4/W8) conçus dès la V1. Beaucoup d'utilisateurs sont mineurs : rien de superflu.
6. **Hors ligne** : la saisie et la consultation doivent fonctionner sans réseau, synchronisation au retour (S9).
7. **Performances visées** : ouverture d'une partie < 2 s ; analyse en file d'attente, quelques minutes au plus.
8. **Commits** : conventionnels (`feat:`, `fix:`, `chore:`, `docs:`), en français ou anglais cohérent, un sujet logique par commit.
9. **Tests** : unitaires sur la logique (notation algébrique, validation des coups légaux, doublons de sync) ; l'échiquier interactif et le scan OCR sont à couvrir en priorité.
10. **Décisions structurantes** : documenter chaque choix technique majeur dans `docs/adr/NNN-nom.md` (contexte, options, décision, conséquences).

## Points sensibles

- **Synchronisation Lichess/Chess.com** : respecter les limites de requêtes (file d'attente, reprises), mémoriser la dernière partie récupérée, isoler la sync dans un module remplaçable (les API peuvent changer).
- **Stockfish (GPL)** : étude de licence requise avant le choix d'intégration — ne pas embarquer sans analyse.
- **Scan OCR** : plus gros risque technique ; test du concept à faire avant d'investir (issue #20).
- **Paiements** : prestataire certifié uniquement, aucune donnée de carte conservée.
