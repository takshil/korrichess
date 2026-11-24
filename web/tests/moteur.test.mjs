/* Tests du moteur local (ADR-006) : parsing UCI et moteur WASM vendored.
   Lancer avec : node --test web/tests/ */

import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

import {
    classerCoup,
    evalBlancs,
    formaterEval,
    MoteurLocal,
    parserInfoUci,
    perteCoup,
    pourcentageBlancs,
} from '../espace/moteur.js';
import { construireRejeu } from '../espace/partie.js';

const FEN_DEPART = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

const require = createRequire(import.meta.url);
const ICI = path.dirname(fileURLToPath(import.meta.url));
const MOTEUR_VENDORED = path.join(ICI, '../espace/vendor/stockfish/stockfish-19-lite-single.js');

let moteurNoeud = null;

function moteurPartage() {
    if (!moteurNoeud) {
        moteurNoeud = require(MOTEUR_VENDORED)();
    }
    return moteurNoeud;
}

test('parserInfoUci extrait profondeur, score et variante', () => {
    const info = parserInfoUci('info depth 12 seldepth 14 multipv 1 score cp 31 nodes 12743 nps 554043 time 23 pv e2e4 c7c5 b1c3');
    assert.equal(info.depth, 12);
    assert.equal(info.multipv, 1);
    assert.equal(info.scoreCp, 31);
    assert.equal(info.mate, null);
    assert.deepEqual(info.pv, ['e2e4', 'c7c5', 'b1c3']);
});

test('parserInfoUci gère les scores de mat', () => {
    const info = parserInfoUci('info depth 20 multipv 1 score mate -3 pv g8f6');
    assert.equal(info.mate, -3);
    assert.equal(info.scoreCp, null);
    assert.deepEqual(info.pv, ['g8f6']);
});

test('parserInfoUci ignore les lignes qui ne sont pas des info', () => {
    assert.equal(parserInfoUci('bestmove e2e4 ponder c7c5'), null);
    assert.equal(parserInfoUci('uciok'), null);
    assert.equal(parserInfoUci('info string Network replica 1: Local memory'), null);
    assert.equal(parserInfoUci(null), null);
    const sansPv = parserInfoUci('info depth 5 score cp 10 nodes 100');
    assert.deepEqual(sansPv.pv, []);
});

test('evalBlancs convertit le score relatif au trait en score relatif aux blancs', () => {
    assert.equal(evalBlancs(FEN_DEPART, { scoreCp: 31, mate: null }), 31);
    const fenNoirs = FEN_DEPART.replace(' w ', ' b ');
    assert.equal(evalBlancs(fenNoirs, { scoreCp: 31, mate: null }), -31);
    assert.equal(evalBlancs(FEN_DEPART, { scoreCp: null, mate: 3 }), 100000 - 3);
    assert.equal(evalBlancs(fenNoirs, { scoreCp: null, mate: -2 }), 100000 - 2);
    assert.equal(evalBlancs(FEN_DEPART, null), null);
});

test('formaterEval affiche des pions ou un mat', () => {
    assert.equal(formaterEval(31), '+0.3');
    assert.equal(formaterEval(-150), '-1.5');
    assert.equal(formaterEval(0), '0.0');
    assert.equal(formaterEval(100000 - 3), 'Mat en 3 (blancs)');
    assert.equal(formaterEval(-(100000 - 2)), 'Mat en 2 (noirs)');
    assert.equal(formaterEval(null), '—');
});

test('pourcentageBlancs suit une courbe de gain bornée', () => {
    assert.equal(pourcentageBlancs(0), 50);
    assert.ok(pourcentageBlancs(400) > 85);
    assert.ok(pourcentageBlancs(-400) < 15);
    assert.ok(pourcentageBlancs(100000) <= 99);
    assert.ok(pourcentageBlancs(-100000) >= 1);
});

test('perteCoup mesure la perte du joueur qui vient de jouer', () => {
    assert.equal(perteCoup({ scoreCp: 30, mate: null }, { scoreCp: -20, mate: null }), 10);
    assert.equal(perteCoup({ scoreCp: 30, mate: null }, { scoreCp: 40, mate: null }), 70);
    assert.equal(perteCoup({ scoreCp: null, mate: 1 }, { scoreCp: null, mate: 1 }), (100000 - 1) * 2);
    assert.equal(perteCoup(null, { scoreCp: 0, mate: null }), null);
});

test('classerCoup suit les seuils documentés', () => {
    assert.equal(classerCoup(0, true), 'meilleur');
    assert.equal(classerCoup(3, false), 'bon');
    assert.equal(classerCoup(30, false), 'imprecision');
    assert.equal(classerCoup(80, false), 'erreur');
    assert.equal(classerCoup(300, false), 'gaffe');
    assert.equal(classerCoup(null, false), null);
});

test('le moteur WASM vendored répond en UCI', async () => {
    const engine = moteurPartage();
    const meilleurCoup = await new Promise((resoudre, rejeter) => {
        const delai = setTimeout(() => rejeter(new Error('délai dépassé')), 30000);
        engine.listener = (ligne) => {
            if (ligne === 'uciok') {
                engine.processCommand('position startpos');
                engine.processCommand('go depth 8');
            }
            if (ligne.indexOf('bestmove') === 0) {
                clearTimeout(delai);
                resoudre(ligne.split(/\s+/)[1]);
            }
        };
        engine.processCommand('uci');
    });
    assert.match(meilleurCoup, /^[a-h][1-8][a-h][1-8][qrbn]?$/);
});

test('MoteurLocal enchaîne uciok, analyse et bestmove via un worker factice', async () => {
    class WorkerFactice {
        constructor() {
            this.onmessage = null;
            this.onerror = null;
            this._engine = moteurPartage();
            this._engine.listener = (ligne) => {
                if (this.onmessage) {
                    this.onmessage({ data: ligne });
                }
            };
        }

        postMessage(commande) {
            this._engine.processCommand(commande);
        }

        terminate() {}
    }
    const WorkerAvant = globalThis.Worker;
    globalThis.Worker = WorkerFactice;
    try {
        const moteur = new MoteurLocal('factice');
        const progression = [];
        const resultats = await moteur.analyserSequence([FEN_DEPART, FEN_DEPART.replace(' w ', ' b ')], {
            profondeur: 8,
            onProgression: (fait, total) => progression.push([fait, total]),
        });
        assert.equal(resultats.length, 2);
        assert.ok(resultats[0].pv.length > 0);
        assert.ok(resultats[0].depth >= 8);
        assert.deepEqual(progression, [[1, 2], [2, 2]]);
        moteur.detruire();
    } finally {
        if (WorkerAvant === undefined) {
            delete globalThis.Worker;
        } else {
            globalThis.Worker = WorkerAvant;
        }
    }
});

test('pipeline complet : PGN -> positions -> analyse WASM -> verdicts', async () => {
    const donnees = construireRejeu({ pgn: '1. f3 e5 2. g4 Qh4# 0-1' });
    assert.ok(donnees);
    assert.equal(donnees.positions.length, 5);
    assert.deepEqual(donnees.moves_uci, ['f2f3', 'e7e5', 'g2g4', 'd8h4']);

    class WorkerFactice {
        constructor() {
            this.onmessage = null;
            this.onerror = null;
            this._engine = moteurPartage();
            this._engine.listener = (ligne) => {
                if (this.onmessage) {
                    this.onmessage({ data: ligne });
                }
            };
        }

        postMessage(commande) {
            this._engine.processCommand(commande);
        }

        terminate() {}
    }
    const WorkerAvant = globalThis.Worker;
    globalThis.Worker = WorkerFactice;
    try {
        const moteur = new MoteurLocal('factice');
        const analyses = await moteur.analyserSequence(donnees.positions, { profondeur: 6 });
        moteur.detruire();
        assert.equal(analyses.length, 5);
        const gaffe = classerCoup(perteCoup(analyses[2], analyses[3]), donnees.moves_uci[2] === analyses[2].pv[0]);
        assert.equal(gaffe, 'gaffe');
        assert.notEqual(analyses[2].pv[0], 'g2g4');
        assert.equal(evalBlancs(donnees.positions[4], analyses[4]) <= -(100000 - 1000), true);
    } finally {
        if (WorkerAvant === undefined) {
            delete globalThis.Worker;
        } else {
            globalThis.Worker = WorkerAvant;
        }
    }
});
