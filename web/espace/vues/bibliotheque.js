/* Vue bibliothèque de parties (W3). */

import { api } from '../api.js';
import { courteDate, echapperHtml, libelleResultat, vue } from '../ui.js';

let filtresEnMemoire = {};

export function vueBibliotheque(offset, aller) {
    offset = offset || 0;
    vue().innerHTML = '<p>Chargement…</p>';

    let requete = '/games?limit=20&offset=' + offset + '&sort=played_at&order=desc';
    Object.keys(filtresEnMemoire).forEach((cle) => {
        const valeur = filtresEnMemoire[cle];
        if (valeur !== '' && valeur !== null && valeur !== undefined) {
            requete += '&' + encodeURIComponent(cle) + '=' + encodeURIComponent(valeur);
        }
    });

    api(requete).then((r) => {
        if (r.status !== 200) {
            return;
        }
        const total = r.body.total || 0;
        const lignes = (r.body.games || []).map((p) => (
            '<tr>' +
            '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
            '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || '?') + '</a></td>' +
            '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
            '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
            '<td>' + echapperHtml(p.time_control || '—') + '</td>' +
            '<td>' + echapperHtml(p.opening || '—') + '</td>' +
            '<td>' + echapperHtml(p.source || '—') + (p.favorite ? ' ★' : '') +
                (p.source === 'manual' ? ' <a href="#/saisie/' + echapperHtml(p.id) + '" title="Corriger la saisie">✎ corriger</a>' : '') + '</td>' +
            '</tr>'
        )).join('');

        const pagePrecedente = offset >= 20 ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset - 20) + '">Précédentes</a>' : '';
        const pageSuivante = offset + 20 < total ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset + 20) + '">Suivantes</a>' : '';

        vue().innerHTML =
            '<h1>Bibliothèque</h1>' +
            '<p><a class="bouton bouton-principal" href="#/saisie">Saisir une partie papier</a></p>' +
            '<form class="filtres" id="formulaire-filtres">' +
            '  <div class="champ"><label for="f-q">Recherche</label>' +
            '    <input id="f-q" type="text" placeholder="Adversaire, tournoi, ouverture…" value="' + echapperHtml(filtresEnMemoire.q || '') + '"></div>' +
            '  <div class="champ"><label for="f-source">Source</label>' +
            '    <select id="f-source"><option value="">Toutes</option><option value="lichess"' + (filtresEnMemoire.source === 'lichess' ? ' selected' : '') + '>Lichess</option><option value="chesscom"' + (filtresEnMemoire.source === 'chesscom' ? ' selected' : '') + '>Chess.com</option></select></div>' +
            '  <div class="champ"><label for="f-couleur">Couleur</label>' +
            '    <select id="f-couleur"><option value="">Toutes</option><option value="white"' + (filtresEnMemoire.color === 'white' ? ' selected' : '') + '>Blancs</option><option value="black"' + (filtresEnMemoire.color === 'black' ? ' selected' : '') + '>Noirs</option></select></div>' +
            '  <div class="champ"><label for="f-resultat">Résultat</label>' +
            '    <select id="f-resultat"><option value="">Tous</option><option value="1-0"' + (filtresEnMemoire.result === '1-0' ? ' selected' : '') + '>1-0</option><option value="0-1"' + (filtresEnMemoire.result === '0-1' ? ' selected' : '') + '>0-1</option><option value="1/2-1/2"' + (filtresEnMemoire.result === '1/2-1/2' ? ' selected' : '') + '>Nulle</option></select></div>' +
            '  <div class="champ carree"><label for="f-favori">Favoris seulement</label>' +
            '    <input id="f-favori" type="checkbox" ' + (filtresEnMemoire.favorite ? 'checked' : '') + '></div>' +
            '  <div class="filtres-actions">' +
            '    <button class="bouton bouton-principal" type="submit">Filtrer</button>' +
            '    <button class="bouton bouton-secondaire" type="button" id="f-reset">Effacer</button>' +
            '  </div>' +
            '</form>' +
            (total === 0
                ? '<p>Aucune partie ne correspond. Synchronise tes pseudos depuis le tableau de bord.</p>'
                : '<div class="tableau-wrap"><table class="tableau">' +
                  '<thead><tr><th>Date</th><th>Adversaire</th><th>Couleur</th><th>Résultat</th><th>Cadence</th><th>Ouverture</th><th>Source</th></tr></thead>' +
                  '<tbody>' + lignes + '</tbody></table></div>') +
            '<p>' + total + ' partie(s) — page ' + (Math.floor(offset / 20) + 1) + '</p>' +
            '<div style="display:flex;gap:0.6rem">' + pagePrecedente + pageSuivante + '</div>';

        document.getElementById('formulaire-filtres').addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            filtresEnMemoire = {
                q: document.getElementById('f-q').value,
                source: document.getElementById('f-source').value,
                color: document.getElementById('f-couleur').value,
                result: document.getElementById('f-resultat').value,
                favorite: document.getElementById('f-favori').checked ? '1' : '',
            };
            aller('#/bibliotheque/0');
        });
        document.getElementById('f-reset').addEventListener('click', () => {
            filtresEnMemoire = {};
            aller('#/bibliotheque/0');
        });
    });
}
