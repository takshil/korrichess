/* Rendu de l'échiquier via cm-chessboard (SVG, coordonnées, glisser-déposer et clic). */

import { BORDER_TYPE, Chessboard, COLOR, FEN, INPUT_EVENT_TYPE } from './vendor/cm-chessboard/src/Chessboard.js';
import { MARKER_TYPE, Markers } from './vendor/cm-chessboard/src/extensions/markers/Markers.js';
import { PROMOTION_DIALOG_RESULT_TYPE, PromotionDialog } from './vendor/cm-chessboard/src/extensions/promotion-dialog/PromotionDialog.js';

const ASSETS_URL = './vendor/cm-chessboard/assets/';

function optionsStyle() {
    return {
        cssClass: 'green',
        showCoordinates: true,
        borderType: BORDER_TYPE.thin,
        pieces: { file: 'pieces/staunty.svg' },
        animationDuration: 150,
    };
}

/**
 * Crée un échiquier en lecture seule pour le rejeu.
 * Retourne une API simple : afficher(fen), inverser(etat), detruire().
 */
export function creerEchiquierRejeu(conteneur, { fen = FEN.start, inverser = false } = {}) {
    const plateau = new Chessboard(conteneur, {
        position: fen,
        orientation: inverser ? COLOR.black : COLOR.white,
        assetsUrl: ASSETS_URL,
        style: optionsStyle(),
        extensions: [{ class: Markers }],
    });

    return {
        afficher(nouveauFen) {
            plateau.setPosition(nouveauFen, true);
        },
        inverser(estNoir) {
            plateau.setOrientation(estNoir ? COLOR.black : COLOR.white, true);
        },
        detruire() {
            plateau.destroy();
        },
    };
}

/**
 * Crée un échiquier interactif pour la saisie manuelle (clic et glisser-déposer).
 * `getEtat()` retourne `{ fen, legal, trait, invalidPly }`.
 * `onCoup(uci)` est appelé lorsqu'un coup légal est joué.
 */
export function creerEchiquierSaisie(conteneur, { fen = FEN.start, inverser = false, getEtat, onCoup }) {
    const plateau = new Chessboard(conteneur, {
        position: fen,
        orientation: inverser ? COLOR.black : COLOR.white,
        assetsUrl: ASSETS_URL,
        style: optionsStyle(),
        extensions: [
            { class: Markers },
            { class: PromotionDialog },
        ],
    });

    function activerSaisie() {
        plateau.enableMoveInput((evenement) => {
            const etat = getEtat();
            if (evenement.type === INPUT_EVENT_TYPE.moveInputStarted) {
                if (etat.invalidPly !== null) {
                    return false;
                }
                const destinations = [];
                etat.legal.forEach((m) => {
                    if (m.slice(0, 2) === evenement.squareFrom && !destinations.includes(m.slice(2, 4))) {
                        destinations.push(m.slice(2, 4));
                    }
                });
                if (destinations.length === 0) {
                    return false;
                }
                plateau.removeMarkers(MARKER_TYPE.dot);
                destinations.forEach((sq) => plateau.addMarker(MARKER_TYPE.dot, sq));
                return true;
            }

            if (evenement.type === INPUT_EVENT_TYPE.validateMoveInput) {
                plateau.removeMarkers(MARKER_TYPE.dot);
                const candidats = etat.legal.filter(
                    (m) => m.slice(0, 2) === evenement.squareFrom && m.slice(2, 4) === evenement.squareTo,
                );
                if (candidats.length === 0) {
                    return false;
                }
                if (candidats.length === 1) {
                    queueMicrotask(() => onCoup(candidats[0]));
                    return true;
                }
                const couleur = etat.trait === 'black' ? COLOR.black : COLOR.white;
                plateau.showPromotionDialog(evenement.squareTo, couleur, (resultat) => {
                    if (resultat && resultat.type === PROMOTION_DIALOG_RESULT_TYPE.pieceSelected && resultat.piece) {
                        onCoup(evenement.squareFrom + evenement.squareTo + resultat.piece[1]);
                    } else {
                        plateau.setPosition(getEtat().fen, false);
                    }
                });
                return true;
            }

            if (evenement.type === INPUT_EVENT_TYPE.moveInputCanceled) {
                plateau.removeMarkers(MARKER_TYPE.dot);
            }
            return true;
        });
    }

    activerSaisie();

    return {
        afficher(nouveauFen, figee) {
            plateau.removeMarkers(MARKER_TYPE.dot);
            plateau.setPosition(nouveauFen, true);
            if (figee) {
                plateau.disableMoveInput();
            } else {
                plateau.disableMoveInput();
                activerSaisie();
            }
        },
        inverser(estNoir) {
            plateau.setOrientation(estNoir ? COLOR.black : COLOR.white, true);
        },
        detruire() {
            plateau.destroy();
        },
    };
}
