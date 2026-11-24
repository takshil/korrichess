/* Moteur d'analyse local : Stockfish WASM dans un Web Worker, protocole UCI (ADR-006). */

const MATE_CP = 100000;

export const URL_MOTEUR_DEFAUT = new URL('vendor/stockfish/stockfish-19-lite-single.js', import.meta.url);

export function parserInfoUci(ligne) {
    if (typeof ligne !== 'string' || ligne.indexOf('info ') !== 0) {
        return null;
    }
    const mots = ligne.trim().split(/\s+/);
    if (mots[1] === 'string') {
        return null;
    }
    const info = { depth: 0, multipv: 1, scoreCp: null, mate: null, pv: [] };
    for (let i = 1; i < mots.length; i += 1) {
        const mot = mots[i];
        if (mot === 'depth') {
            info.depth = Number(mots[i + 1]) || 0;
            i += 1;
        } else if (mot === 'multipv') {
            info.multipv = Number(mots[i + 1]) || 1;
            i += 1;
        } else if (mot === 'score') {
            const type = mots[i + 1];
            const valeur = Number(mots[i + 2]);
            if (type === 'cp') {
                info.scoreCp = valeur;
            } else if (type === 'mate') {
                info.mate = valeur;
            }
            i += 2;
        } else if (mot === 'pv') {
            info.pv = mots.slice(i + 1);
            break;
        }
    }
    return info;
}

function scoreVersCp(info) {
    if (info.mate !== null) {
        return info.mate > 0 ? MATE_CP - info.mate : -(MATE_CP + info.mate);
    }
    return info.scoreCp || 0;
}

export function evalBlancs(fen, info) {
    if (!info) {
        return null;
    }
    const auTrait = String(fen).split(' ')[1] === 'b' ? -1 : 1;
    return auTrait * scoreVersCp(info);
}

export function formaterEval(cpBlancs) {
    if (cpBlancs === null || cpBlancs === undefined) {
        return '—';
    }
    if (Math.abs(cpBlancs) >= MATE_CP - 1000) {
        const n = MATE_CP - Math.abs(cpBlancs);
        return 'Mat en ' + n + (cpBlancs > 0 ? ' (blancs)' : ' (noirs)');
    }
    const pions = cpBlancs / 100;
    return (pions > 0 ? '+' : '') + pions.toFixed(1);
}

export function pourcentageBlancs(cpBlancs) {
    if (cpBlancs === null || cpBlancs === undefined) {
        return 50;
    }
    const p = 100 / (1 + Math.pow(10, -cpBlancs / 400));
    return Math.max(1, Math.min(99, p));
}

export function perteCoup(infoAvant, infoApres) {
    if (!infoAvant || !infoApres) {
        return null;
    }
    return scoreVersCp(infoAvant) + scoreVersCp(infoApres);
}

export function classerCoup(perte, estMeilleurCoup) {
    if (estMeilleurCoup) {
        return 'meilleur';
    }
    if (perte === null || perte === undefined) {
        return null;
    }
    if (perte <= 5) {
        return 'bon';
    }
    if (perte <= 50) {
        return 'imprecision';
    }
    if (perte <= 150) {
        return 'erreur';
    }
    return 'gaffe';
}

export class MoteurLocal {
    constructor(urlMoteur = URL_MOTEUR_DEFAUT) {
        this.worker = new Worker(urlMoteur);
        this._pret = null;
        this._onUciok = null;
        this._analyseEnCours = null;
        this._annule = false;
        this.worker.onmessage = (evenement) => this._recevoir(evenement.data);
        this.worker.onerror = () => {
            if (this._analyseEnCours) {
                const enCours = this._analyseEnCours;
                this._analyseEnCours = null;
                enCours.rejeter(new Error('worker indisponible'));
            }
        };
    }

    _envoyer(commande) {
        this.worker.postMessage(commande);
    }

    _recevoir(ligne) {
        if (typeof ligne !== 'string') {
            return;
        }
        if (ligne === 'uciok' && this._onUciok) {
            const pret = this._onUciok;
            this._onUciok = null;
            pret();
            return;
        }
        const enCours = this._analyseEnCours;
        if (!enCours) {
            return;
        }
        if (ligne.indexOf('bestmove') === 0) {
            this._analyseEnCours = null;
            const meilleur = ligne.split(/\s+/)[1] || '';
            enCours.resoudre(enCours.info || { depth: 0, multipv: 1, scoreCp: 0, mate: null, pv: meilleur ? [meilleur] : [] });
            return;
        }
        const info = parserInfoUci(ligne);
        if (info && info.multipv === 1) {
            const courant = enCours.info;
            const avecScore = (i) => i.pv.length > 0 || i.scoreCp !== null || i.mate !== null;
            if (!courant || info.depth > courant.depth || (info.depth === courant.depth && avecScore(info) && !avecScore(courant))) {
                enCours.info = info;
            }
        }
    }

    initialiser() {
        if (!this._pret) {
            this._pret = new Promise((resoudre) => {
                this._onUciok = resoudre;
            });
            this._envoyer('uci');
        }
        return this._pret;
    }

    analyser(fen, profondeur) {
        return this.initialiser().then(() => new Promise((resoudre, rejeter) => {
            if (this._annule) {
                rejeter(new Error('annule'));
                return;
            }
            this._analyseEnCours = { resoudre, rejeter, info: null };
            this._envoyer('position fen ' + fen);
            this._envoyer('go depth ' + profondeur);
        }));
    }

    async analyserSequence(fens, { profondeur = 12, onProgression } = {}) {
        const resultats = [];
        for (let i = 0; i < fens.length; i += 1) {
            if (this._annule) {
                break;
            }
            const info = await this.analyser(fens[i], profondeur);
            resultats.push(info);
            if (onProgression) {
                onProgression(i + 1, fens.length);
            }
        }
        return resultats;
    }

    annuler() {
        this._annule = true;
        this._envoyer('stop');
        if (this._analyseEnCours) {
            const enCours = this._analyseEnCours;
            this._analyseEnCours = null;
            enCours.resoudre(enCours.info);
        }
    }

    detruire() {
        this.annuler();
        this.worker.terminate();
    }
}
