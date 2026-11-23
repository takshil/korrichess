/* Logique d'échecs côté client via chess.js (rejeu PGN/UCI et validation de saisie). */

import { Chess } from './vendor/chess.js';

const REGEX_UCI = /^[a-h][1-8][a-h][1-8][qrbn]?$/i;

function normaliserSan(coup) {
    const t = String(coup || '').trim();
    if (/^0-0-0([+#!?]*)$/.test(t) || /^o-o-o([+#!?]*)$/i.test(t)) {
        return t.replace(/^(0-0-0|o-o-o)/i, 'O-O-O');
    }
    if (/^0-0([+#!?]*)$/.test(t) || /^o-o([+#!?]*)$/i.test(t)) {
        return t.replace(/^(0-0|o-o)/i, 'O-O');
    }
    return t;
}

function jouerCoup(chess, coup) {
    const brut = String(coup || '').trim();
    if (brut === '') {
        return null;
    }
    try {
        if (REGEX_UCI.test(brut)) {
            const uci = brut.toLowerCase();
            return chess.move({
                from: uci.slice(0, 2),
                to: uci.slice(2, 4),
                promotion: uci[4] || undefined,
            });
        }
        return chess.move(normaliserSan(brut));
    } catch {
        return null;
    }
}

function coupsLegauxUci(chess) {
    return chess.moves({ verbose: true }).map((m) => m.from + m.to + (m.promotion || ''));
}

/**
 * Rejoue une suite de coups saisis (UCI ou SAN) depuis la position initiale.
 * S'arrête au premier coup illégal et retourne l'état complet pour la saisie.
 */
export function analyserSaisie(moves) {
    const chess = new Chess();
    const moves_san = [];
    const moves_uci = [];
    const liste = Array.isArray(moves) ? moves : [];

    for (let i = 0; i < liste.length; i++) {
        const brut = String(liste[i] ?? '').trim();
        const joue = jouerCoup(chess, brut);
        if (!joue) {
            return {
                fen: chess.fen(),
                moves_san,
                moves_uci,
                legal_uci: coupsLegauxUci(chess),
                invalid_ply: i,
                invalid_move: brut,
                turn: chess.turn() === 'w' ? 'white' : 'black',
                in_check: chess.inCheck(),
                is_checkmate: chess.isCheckmate(),
                is_stalemate: chess.isStalemate(),
            };
        }
        moves_san.push(joue.san);
        moves_uci.push(joue.from + joue.to + (joue.promotion || ''));
    }

    return {
        fen: chess.fen(),
        moves_san,
        moves_uci,
        legal_uci: coupsLegauxUci(chess),
        invalid_ply: null,
        invalid_move: null,
        turn: chess.turn() === 'w' ? 'white' : 'black',
        in_check: chess.inCheck(),
        is_checkmate: chess.isCheckmate(),
        is_stalemate: chess.isStalemate(),
    };
}

/**
 * Construit la séquence { positions, moves_san } pour le rejeu à partir du PGN
 * ou des coups UCI d'une partie. Retourne null si la partie ne peut pas être rejouée.
 */
export function construireRejeu(detail) {
    if (!detail) {
        return null;
    }

    if (detail.pgn) {
        try {
            const chess = new Chess();
            chess.loadPgn(detail.pgn, { strict: false });
            const hist = chess.history({ verbose: true });
            if (hist.length > 0) {
                return {
                    positions: [hist[0].before, ...hist.map((m) => m.after)],
                    moves_san: hist.map((m) => m.san),
                };
            }
            if (!detail.moves_uci || detail.moves_uci.length === 0) {
                return {
                    positions: [chess.fen()],
                    moves_san: [],
                };
            }
        } catch {
            /* PGN invalide ou incomplet : on tente moves_uci puis l'API /replay. */
        }
    }

    if (Array.isArray(detail.moves_uci) && detail.moves_uci.length > 0) {
        const chess = new Chess();
        const positions = [chess.fen()];
        const moves_san = [];
        for (const uci of detail.moves_uci) {
            const joue = jouerCoup(chess, uci);
            if (!joue) {
                break;
            }
            moves_san.push(joue.san);
            positions.push(chess.fen());
        }
        if (moves_san.length > 0) {
            return { positions, moves_san };
        }
    }

    return null;
}
