/* Vue tableau de bord (W2). */

import { api, suivreSync } from '../api.js';
import { courteDate, echapperHtml, libelleResultat, vue } from '../ui.js';

export function vueTableauDeBord() {
    vue().innerHTML = '<p>Chargement…</p>';
    // Les compteurs viennent du serveur (/me/stats) : les calculer sur les
    // 200 dernières parties sous-estimait les totaux dès 200+ parties.
    Promise.all([
        api('/games?limit=8&sort=played_at&order=desc'),
        api('/me/stats'),
    ]).then(([r, statsRep]) => {
        if (r.status !== 200) {
            return;
        }
        const parties = r.body.games || [];
        const stats = statsRep.status === 200 ? statsRep.body : null;
        const total = stats ? (stats.total || 0) : (r.body.total || parties.length);
        const victoires = stats ? (stats.victoires || 0) : 0;
        const nulles = stats ? (stats.nulles || 0) : 0;
        const defaites = stats ? (stats.defaites || 0) : 0;
        const taux = stats ? (stats.taux_victoires || 0) : (total > 0 ? Math.round((victoires / total) * 100) : 0);

        const recentes = parties.slice(0, 8).map((p) => (
            '<tr>' +
            '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
            '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || 'adversaire inconnu') + '</a></td>' +
            '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
            '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
            '<td>' + echapperHtml(p.source || '') + '</td>' +
            '</tr>'
        )).join('');

        vue().innerHTML =
            '<h1>Tableau de bord</h1>' +
            '<div class="stat-grille">' +
            '  <div class="stat-carte"><span class="valeur">' + total + '</span><span class="libelle">parties</span></div>' +
            '  <div class="stat-carte"><span class="valeur">' + victoires + '</span><span class="libelle">victoires</span></div>' +
            '  <div class="stat-carte"><span class="valeur">' + nulles + '</span><span class="libelle">nulles</span></div>' +
            '  <div class="stat-carte"><span class="valeur">' + defaites + '</span><span class="libelle">défaites</span></div>' +
            '  <div class="stat-carte"><span class="valeur">' + taux + ' %</span><span class="libelle">de victoires</span></div>' +
            '</div>' +
            '<p><a class="bouton bouton-principal" href="#/saisie">Saisir une partie papier</a></p>' +
            '<h2>Synchroniser mes parties</h2>' +
            '<form class="filtres" id="formulaire-sync">' +
            '  <div class="champ"><label for="sync-plateforme">Plateforme</label>' +
            '    <select id="sync-plateforme"><option value="lichess">Lichess</option><option value="chesscom">Chess.com</option></select></div>' +
            '  <div class="champ"><label for="sync-pseudo">Pseudo</label>' +
            '    <input id="sync-pseudo" type="text" placeholder="Ton pseudo" required></div>' +
            '  <div class="filtres-actions"><button class="bouton bouton-principal" type="submit">Synchroniser</button></div>' +
            '  <p class="message-ok" id="sync-retour" style="grid-column:1/-1"></p>' +
            '</form>' +
            '<h2>Parties récentes</h2>' +
            (parties.length === 0
                ? '<p>Aucune partie pour l\'instant. Synchronise un pseudo ci-dessus pour commencer !</p>'
                : '<div class="tableau-wrap"><table class="tableau">' +
                  '<thead><tr><th>Date</th><th>Adversaire</th><th>Couleur</th><th>Résultat</th><th>Source</th></tr></thead>' +
                  '<tbody>' + recentes + '</tbody></table></div>') +
            '<p class="prose" style="color:var(--encre-doux)">Les statistiques détaillées de progression arrivent plus tard (V3).</p>';

        const retour = document.getElementById('sync-retour');
        document.getElementById('formulaire-sync').addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            retour.textContent = '';
            const plateforme = document.getElementById('sync-plateforme').value;
            const pseudo = document.getElementById('sync-pseudo').value;
            api('/sync', {
                methode: 'POST',
                body: { platform: plateforme, username: pseudo },
            }).then((res) => {
                if (res.status === 202) {
                    retour.textContent = 'Synchronisation lancée en arrière-plan…';
                    suivreSync({
                        plateforme,
                        pseudo,
                        depart: Date.now(),
                        maxEssais: 24,
                        onTermine: () => {
                            retour.textContent = 'Synchronisation terminée ! Tes dernières parties sont là.';
                            vueTableauDeBord();
                        },
                        onTimeout: () => {
                            retour.textContent = 'Synchronisation toujours en cours, reviens dans un instant.';
                            vueTableauDeBord();
                        },
                    });
                } else if (res.body.error === 'plateforme_inconnue') {
                    retour.textContent = 'Plateforme inconnue.';
                } else if (res.body.error === 'pseudo_invalide') {
                    retour.textContent = 'Ce pseudo ne semble pas valide.';
                } else {
                    retour.textContent = 'Synchronisation impossible pour l\'instant.';
                }
            });
        });
    });
}
