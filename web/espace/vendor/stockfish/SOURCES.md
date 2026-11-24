# Stockfish.js 19 — build « lite single-threaded »

Fichiers distribués tels quels (non modifiés) depuis le paquet npm
[`stockfish@19.0.1`](https://www.npmjs.com/package/stockfish) (nmrugg/stockfish.js) :

- `stockfish-19-lite-single.js`
- `stockfish-19-lite-single.wasm`
- `Copying.txt` — licence GPL v3, texte intégral

- Projet amont du build : https://github.com/nmrugg/stockfish.js (tag `v19.0.1`)
- Moteur sous-jacent : Stockfish 19 — sources : https://github.com/official-stockfish/Stockfish

Le build « lite single-threaded » est retenu car il ne nécessite pas
l'isolation cross-origin (`SharedArrayBuffer`, en-têtes COOP/COEP) et pèse
environ 1,8 Mo, ce qui convient à l'hébergement mutualisé (ADR-003, ADR-006).
Le `.js` sert directement de Web Worker : il parle UCI via `postMessage`.
