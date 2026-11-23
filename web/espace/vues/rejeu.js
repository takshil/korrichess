/* Vue rejeu (W4). */

import { api } from '../api.js';
import { creerEchiquierRejeu } from '../echiquier.js';
import { construireRejeu } from '../partie.js';
import { courteDate, echapperHtml, libelleResultat, vue } from '../ui.js';

let ecouteurClavier = null;
let plateauRejeu = null;

export function detacherClavierRejeu() {
    if (ecouteurClavier) {
        document.removeEventListener('keydown', ecouteurClavier);
        ecouteurClavier = null;
    }
    if (plateauRejeu) {
        plateauRejeu.detruire();
        plateauRejeu = null;
    }
}

export function vueRejeu(id) {
    vue().innerHTML = '<p>Chargement de la partie…</p>';
    api('/games/' + id).then(async (detail) => {
        if (detail.status !== 200) {
            vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
            return;
        }
        let donneesRejeu = construireRejeu(detail.body);
        if (!donneesRejeu) {
            const rejeu = await api('/games/' + id + '/replay');
            if (rejeu.status === 200 && rejeu.body.positions) {
                donneesRejeu = {
                    positions: rejeu.body.positions,
                    moves_san: rejeu.body.moves_san || [],
                };
            }
        }
        const positions = donneesRejeu ? donneesRejeu.positions : null;
        const sans = donneesRejeu ? donneesRejeu.moves_san : [];
        let inverser = detail.body.color === 'black';
        let courant = 0;

        let listeCoups = '';
        for (let i = 0; i < sans.length; i += 2) {
            const numero = (i / 2) + 1;
            listeCoups += '<li class="numero">' + numero + '.</li>' +
                '<li><button type="button" data-ply="' + i + '" class="coup">' + echapperHtml(sans[i]) + '</button></li>' +
                (sans[i + 1] !== undefined
                    ? '<li><button type="button" data-ply="' + (i + 1) + '" class="coup">' + echapperHtml(sans[i + 1]) + '</button></li>'
                    : '<li></li>');
        }

        vue().innerHTML =
            '<p><a href="#/bibliotheque">← Bibliothèque</a></p>' +
            '<h1>' + echapperHtml(detail.body.opponent || 'Partie') +
            (detail.body.color === 'white' ? ' — avec les blancs' : ' — avec les noirs') + '</h1>' +
            '<p>' + echapperHtml(courteDate(detail.body.played_at)) + ' · ' +
                echapperHtml(libelleResultat(detail.body)) + (detail.body.opening ? ' · ' + echapperHtml(detail.body.opening) : '') + '</p>' +
            (positions
                ? '<div class="rejeu-disposition">' +
                  '  <div>' +
                  '    <div class="echiquier-cadre" id="echiquier" role="img" aria-label="Échiquier"></div>' +
                  '    <div class="rejeu-controles">' +
                  '      <button type="button" id="cp-debut" aria-label="Début">⏮</button>' +
                  '      <button type="button" id="cp-avant" aria-label="Coup précédent">◀</button>' +
                  '      <button type="button" id="cp-apres" aria-label="Coup suivant">▶</button>' +
                  '      <button type="button" id="cp-fin" aria-label="Fin">⏭</button>' +
                  '      <button type="button" id="cp-inverser" aria-label="Retourner l\'échiquier" title="Retourner l\'échiquier">⇅</button>' +
                  '    </div>' +
                  '    <p class="rejeu-position" id="rejeu-position"></p>' +
                  '  </div>' +
                  '  <div class="coups" id="liste-coups"><ol>' + listeCoups + '</ol></div>' +
                  '</div>'
                : '<p>Le rejeu n\'est pas disponible pour cette partie. PGN :</p>' +
                  '<pre style="white-space:pre-wrap">' + echapperHtml(detail.body.pgn || '') + '</pre>');

        if (!positions) {
            return;
        }

        detacherClavierRejeu();
        plateauRejeu = creerEchiquierRejeu(document.getElementById('echiquier'), {
            fen: positions[0],
            inverser,
        });

        function afficher(ply) {
            courant = Math.max(0, Math.min(ply, positions.length - 1));
            plateauRejeu.afficher(positions[courant]);
            document.getElementById('rejeu-position').textContent = courant === 0
                ? 'Position de départ'
                : 'Coup ' + Math.ceil(courant / 2) + (courant % 2 === 1 ? '… ' : '. ') + (sans[courant - 1] || '');
            document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
                bouton.classList.toggle('courant', Number(bouton.getAttribute('data-ply')) === courant - 1);
            });
        }

        document.getElementById('cp-debut').addEventListener('click', () => afficher(0));
        document.getElementById('cp-avant').addEventListener('click', () => afficher(courant - 1));
        document.getElementById('cp-apres').addEventListener('click', () => afficher(courant + 1));
        document.getElementById('cp-fin').addEventListener('click', () => afficher(positions.length - 1));
        document.getElementById('cp-inverser').addEventListener('click', () => {
            inverser = !inverser;
            plateauRejeu.inverser(inverser);
        });
        document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
            bouton.addEventListener('click', () => afficher(Number(bouton.getAttribute('data-ply')) + 1));
        });

        ecouteurClavier = (evenement) => {
            if (evenement.key === 'ArrowLeft') { afficher(courant - 1); }
            if (evenement.key === 'ArrowRight') { afficher(courant + 1); }
            if (evenement.key === 'Home') { afficher(0); }
            if (evenement.key === 'End') { afficher(positions.length - 1); }
        };
        document.addEventListener('keydown', ecouteurClavier);
        afficher(0);
    });
}
