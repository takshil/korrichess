# ADR-006 — Analyse de la partie en local dans le navigateur (Stockfish WASM)

- Statut : accepté
- Date : nov. 2026 (demande utilisateur « analyse comme lichess directement sur le PC », sur le rejeu web W4)
- Contexte : `web/README.md` actait que l'analyse moteur n'était pas exposée dans l'espace web car les workers de file ne tournent pas en mutualisé (ADR-003) ; seul le rejeu (A1) y était disponible. L'ADR-002 garde l'analyse serveur (processus UCI externe + file de taches) comme référence, mais elle est indisponible sur l'hébergement mutualisé actuel.

## Contexte

Le rejeu web (`web/espace/vues/rejeu.js`) rejoue déjà les parties localement grâce à
chess.js (`web/espace/partie.js`). Lichess analyse les parties dans le navigateur via
un build WASM de Stockfish tournant dans un Web Worker : aucune infrastructure serveur,
résultat immédiat, fonctionne hors ligne une fois la page chargée (règle AGENTS.md n°6).
Le site étant statique sans étape de build (ADR-005), la librairie est vendored comme
chess.js et cm-chessboard.

## Options envisagées

1. **Rester 100 % serveur** : rejeté — indisponible en mutualisé (ADR-003), donc pas d'analyse du tout sur le web aujourd'hui.
2. **Build WASM multi-threadé complet (~94 Mo)** : rejeté — nécessite l'isolation cross-origin (SharedArrayBuffer + en-têtes COOP/COEP non maîtrisés sur o2switch) et un téléchargement trop lourd.
3. **Build WASM « lite single-threaded » (~1,8 Mo), mono-thread** : retenu — aucun en-tête requis, servi tel quel, assez fort pour noter des coups humains (cf. README amont : « far stronger than any human »).
4. **asm.js** : rejeté — lent et plus gros que le lite WASM.

## Décision

- Le build **non modifié** `stockfish-19-lite-single.js` + `.wasm` du paquet npm
  `stockfish@19.0.1` (nmrugg/stockfish.js, basé sur Stockfish 19) est vendored dans
  `web/espace/vendor/stockfish/`, avec `Copying.txt` (GPL v3) et `SOURCES.md`
  (tag amont `v19.0.1`, sources officielles d'official-stockfish/Stockfish).
- Le fichier `.js` est lui-même le Web Worker (`new Worker(...)`) : il parle UCI
  par `postMessage` ; le `.wasm` doit être servi en `application/wasm`
  (`web/espace/.htaccess`, `AddType application/wasm .wasm`).
- `web/espace/moteur.js` encapsule le protocole UCI (`MoteurLocal` : `uci`/`uciok`,
  `position fen …`, `go depth N`, parsing des lignes `info … score … pv …`,
  attente de `bestmove`, `stop` pour annuler) plus les fonctions pures testées
  (conversion du score au point de vue des blancs, pourcentage de gain pour la
  barre d'évaluation, perte en centipawns d'un coup, classement du coup).
- Sur `#/rejeu/{id}`, un bouton « Analyser la partie » analyse chaque position du
  rejeu (profondeur réglable 10/12/14/16, défaut 12 = `ANALYSIS_DEFAULT_DEPTH`) :
  barre d'évaluation, meilleur coup du moteur sur l'échiquier (marqueurs
  cm-chessboard), verdict par coup dans la liste (!, ?, ??, ???) fondé sur la perte
  en centipawns : ≤5 bon, ≤50 imprécision, ≤150 erreur, au-delà gaffe.
- **Licence GPL** (point sensible AGENTS.md) : le binaire est distribué tel quel,
  non modifié, accompagné de la licence GPL v3 et d'un renvoi vers les sources
  exactes (upstream + tag) dans `SOURCES.md` et dans le panneau d'analyse
  (lien « sources du moteur »). ADR-002 reste inchangé côté serveur : aucun lien
  binaire entre l'API PHP et Stockfish.

## Conséquences

- L'analyse fonctionne sur l'hébergement mutualisé et hors ligne après chargement
  de la page ; elle ne consomme aucune ressource serveur.
- ~1,8 Mo téléchargés une fois (cache navigateur) ; sur mobile l'analyse reste
  possible mais plus lente (profondeur réglable, arrêt manuel possible).
- Tests : `node --test web/tests/moteur.test.mjs` (parsing UCI + moteur WASM réel
  en node + pipeline PGN → verdicts sur le mat du fou).
- Si un jour l'hébergement supporte COOP/COEP, on pourra passer au build complet
  multi-threadé sans changer l'interface (`MoteurLocal`).
- L'analyse serveur (ADR-002) et l'analyse locale coexistent : la version locale
  n'est pas persistée en base (pas de `game_analyses`), elle est recalculée à la
  demande sur le rejeu.
