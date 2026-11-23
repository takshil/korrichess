/* Vue rejeu (W4). */

import { api } from '../api.js';
import { damierDepuisFen } from '../echiquier.js';
import { courteDate, echapperHtml, libelleResultat, vue } from '../ui.js';

let ecouteurClavier = null;

export function detacherClavierRejeu() {
    if (ecouteurClavier) {
        document.removeEventListener('keydown', ecouteurClavier);
        ecouteurClavier = null;
    }
}

export function vueRejeu(id) {
    vue().innerHTML = '<p>Chargement de la partie…</p>';
    Promise.all([
        api('/games/' + id),
        api('/games/' + id + '/replay'),
    ]).then(([detail, rejeu]) => {
        if (detail.status !== 200) {
            vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
            return;
        }
        const positions = (rejeu.status === 200 && rejeu.body.positions) ? rejeu.body.positions : null;
        const sans = (rejeu.status === 200 && rejeu.body.moves_san) ? rejeu.body.moves_san : [];
        const inverser = detail.body.color === 'black';
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
                  '    <div class="echiquier" id="echiquier" role="img" aria-label="Échiquier"></div>' +
                  '    <div class="rejeu-controles">' +
                  '      <button type="button" id="cp-debut" aria-label="Début">⏮</button>' +
                  '      <button type="button" id="cp-avant" aria-label="Coup précédent">◀</button>' +
                  '      <button type="button" id="cp-apres" aria-label="Coup suivant">▶</button>' +
                  '      <button type="button" id="cp-fin" aria-label="Fin">⏭</button>' +
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

        function afficher(ply) {
            courant = Math.max(0, Math.min(ply, positions.length - 1));
            document.getElementById('echiquier').innerHTML = damierDepuisFen(positions[courant], inverser);
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
        document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
            bouton.addEventListener('click', () => afficher(Number(bouton.getAttribute('data-ply')) + 1));
        });

        detacherClavierRejeu();
        ecouteurClavier = (evenement) => {
            if (evenement.key === 'ArrowLeft') { afficher(courant - 1); }
            if (evenement.key === 'ArrowRight') { afficher(courant + 1); }
        };
        document.addEventListener('keydown', ecouteurClavier);
        afficher(0);
    });
}
