/* Vue compte, pseudos liés, préférences et RGPD (W7 + W8). */

import { api, purgerJeton, suivreSync } from '../api.js';
import { echapperHtml, telecharger, vue } from '../ui.js';

export function vueCompte() {
    vue().innerHTML = '<p>Chargement…</p>';
    Promise.all([
        api('/me'),
        api('/me/settings'),
    ]).then(([moi, reglages]) => {
        if (moi.status !== 200) {
            return;
        }
        const comptes = moi.body.linked_accounts || [];

        vue().innerHTML =
            '<h1>Mon compte</h1>' +
            '<div class="compte-grille">' +
            '  <div class="compte-carte">' +
            '    <h2>Profil</h2>' +
            '    <p style="color:var(--encre-doux)">' + echapperHtml(moi.body.email) + '</p>' +
            '    <form id="formulaire-profil">' +
            '      <div class="champ"><label for="p-nom">Nom affiché</label>' +
            '        <input id="p-nom" type="text" value="' + echapperHtml(moi.body.name || '') + '"></div>' +
            '      <div class="champ"><label for="p-club">Club</label>' +
            '        <input id="p-club" type="text" value="' + echapperHtml(moi.body.club || '') + '"></div>' +
            '      <div class="champ"><label for="p-classement">Classement (Elo)</label>' +
            '        <input id="p-classement" type="number" min="0" value="' + echapperHtml(moi.body.rating || '') + '"></div>' +
            '      <button class="bouton bouton-principal" type="submit">Enregistrer</button>' +
            '      <p class="message-ok" id="retour-profil"></p>' +
            '    </form>' +
            '  </div>' +
            '  <div class="compte-carte">' +
            '    <h2>Pseudos liés</h2>' +
            '    <ul id="liste-comptes">' +
            (comptes.length === 0 ? '<li>Aucun pseudo lié.</li>' : comptes.map((c) => (
                '<li><span>' + echapperHtml(c.platform) + ' — ' + echapperHtml(c.username) + '</span>' +
                '<button type="button" class="bouton bouton-secondaire delier" data-platform="' + echapperHtml(c.platform) +
                '" data-username="' + echapperHtml(c.username) + '">Délier</button></li>'
            )).join('')) +
            '    </ul>' +
            '    <form id="formulaire-lier">' +
            '      <div class="champ"><label for="l-plateforme">Plateforme</label>' +
            '        <select id="l-plateforme"><option value="lichess">Lichess</option><option value="chesscom">Chess.com</option></select></div>' +
            '      <div class="champ"><label for="l-pseudo">Pseudo</label>' +
            '        <input id="l-pseudo" type="text" required></div>' +
            '      <button id="bouton-lier" class="bouton bouton-secondaire" type="submit">Lier ce pseudo</button>' +
            '      <p class="message-ok" id="retour-lier"></p>' +
            '    </form>' +
            '  </div>' +
            '  <div class="compte-carte">' +
            '    <h2>Préférences</h2>' +
            '    <form id="formulaire-reglages">' +
            '      <div class="champ"><label for="r-theme">Thème</label>' +
            '        <select id="r-theme"><option value="clair"' + (reglages.body.theme === 'clair' ? ' selected' : '') + '>Clair</option>' +
            '          <option value="sombre"' + (reglages.body.theme === 'sombre' ? ' selected' : '') + '>Sombre</option></select></div>' +
            '      <div class="champ"><label for="r-langue">Langue</label>' +
            '        <select id="r-langue"><option value="fr"' + (reglages.body.language === 'fr' ? ' selected' : '') + '>Français</option>' +
            '          <option value="en"' + (reglages.body.language === 'en' ? ' selected' : '') + '>English</option></select></div>' +
            '      <div class="champ"><label for="r-echiquier">Style d\'échiquier</label>' +
            '        <select id="r-echiquier">' +
            ['classique', 'bois', 'contraste'].map((s) => (
                '<option value="' + s + '"' + (reglages.body.board_style === s ? ' selected' : '') + '>' + s + '</option>'
            )).join('') + '</select></div>' +
            '      <div class="champ"><label for="r-pieces">Style de pièces</label>' +
            '        <select id="r-pieces">' +
            ['classique', 'minimaliste'].map((s) => (
                '<option value="' + s + '"' + (reglages.body.piece_style === s ? ' selected' : '') + '>' + s + '</option>'
            )).join('') + '</select></div>' +
            '      <button class="bouton bouton-principal" type="submit">Enregistrer</button>' +
            '      <p class="message-ok" id="retour-reglages"></p>' +
            '    </form>' +
            '    <p style="color:var(--encre-doux)">Abonnement : version gratuite. Les offres entraîneur et club arriveront plus tard.</p>' +
            '  </div>' +
            '  <div class="compte-carte">' +
            '    <h2>Mes données</h2>' +
            '    <p>Exporte tout ce que Korrichess sait de toi, ou supprime ton compte définitivement.</p>' +
            '    <button class="bouton bouton-secondaire" type="button" id="bouton-export">Exporter mes données (JSON)</button>' +
            '  </div>' +
            '  <div class="compte-carte zone-danger">' +
            '    <h2>Zone dangereuse</h2>' +
            '    <p>Supprimer le compte efface toutes tes parties et données, sans retour.</p>' +
            '    <div class="champ"><label for="confirmation">Tape SUPPRIMER pour confirmer</label>' +
            '      <input id="confirmation" type="text" autocomplete="off"></div>' +
            '    <button class="bouton bouton-danger" type="button" id="bouton-supprimer" disabled>Supprimer mon compte</button>' +
            '  </div>' +
            '</div>';

        document.getElementById('formulaire-profil').addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            api('/me', {
                methode: 'PATCH',
                body: {
                    display_name: document.getElementById('p-nom').value,
                    club: document.getElementById('p-club').value,
                    rating: document.getElementById('p-classement').value === '' ? null : Number(document.getElementById('p-classement').value),
                },
            }).then((r) => {
                document.getElementById('retour-profil').textContent = r.status === 200 ? 'Profil enregistré.' : 'Enregistrement impossible.';
            });
        });

        document.getElementById('formulaire-lier').addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            document.getElementById('bouton-lier').disabled = true;
            const plateforme = document.getElementById('l-plateforme').value;
            const pseudo = document.getElementById('l-pseudo').value;
            api('/me/linked-accounts', {
                methode: 'POST',
                body: { platform: plateforme, username: pseudo },
            }).then((r) => {
                const retour = document.getElementById('retour-lier');
                const bouton = document.getElementById('bouton-lier');
                if (r.status === 201) {
                    retour.innerHTML = 'Pseudo lié ! <span class="chargement"></span>Synchronisation de tes parties en cours…';
                    suivreSync({
                        plateforme,
                        pseudo,
                        depart: Date.now(),
                        maxEssais: 36,
                        onTermine: () => {
                            retour.textContent = 'Parties importées ! Rends-toi sur ton tableau de bord pour les rejouer.';
                            setTimeout(vueCompte, 1500);
                        },
                        onTimeout: () => {
                            retour.textContent = 'Synchronisation toujours en cours — tes parties arriveront d\'ici peu.';
                            vueCompte();
                        },
                    });
                } else {
                    retour.textContent = r.body.message === 'deja_lie' ? 'Ce pseudo est déjà lié.' : 'Liaison impossible.';
                    if (bouton) { bouton.disabled = false; }
                }
            });
        });

        document.querySelectorAll('button.delier').forEach((bouton) => {
            bouton.addEventListener('click', () => {
                api('/me/linked-accounts?platform=' + encodeURIComponent(bouton.getAttribute('data-platform')) +
                    '&username=' + encodeURIComponent(bouton.getAttribute('data-username')), { methode: 'DELETE' })
                    .then(() => vueCompte());
            });
        });

        document.getElementById('formulaire-reglages').addEventListener('submit', (evenement) => {
            evenement.preventDefault();
            const corps = {
                theme: document.getElementById('r-theme').value,
                language: document.getElementById('r-langue').value,
                board_style: document.getElementById('r-echiquier').value,
                piece_style: document.getElementById('r-pieces').value,
            };
            api('/me/settings', { methode: 'PATCH', body: corps }).then((r) => {
                const retour = document.getElementById('retour-reglages');
                retour.textContent = r.status === 200 ? 'Préférences enregistrées.' : 'Enregistrement impossible.';
                if (r.status === 200) {
                    document.documentElement.setAttribute('data-theme', corps.theme);
                    try { localStorage.setItem('korrichess_theme', corps.theme); } catch { /* stockage indisponible */ }
                }
            });
        });

        document.getElementById('bouton-export').addEventListener('click', () => {
            api('/me/export').then((r) => {
                if (r.status === 200) {
                    telecharger('korrichess-export-' + new Date().toISOString().slice(0, 10) + '.json',
                        JSON.stringify(r.body, null, 2));
                }
            });
        });

        document.getElementById('confirmation').addEventListener('input', function () {
            document.getElementById('bouton-supprimer').disabled = this.value !== 'SUPPRIMER';
        });
        document.getElementById('bouton-supprimer').addEventListener('click', () => {
            api('/me', { methode: 'DELETE' }).then((r) => {
                if (r.body.message === 'compte_supprime') {
                    purgerJeton();
                    location.hash = '';
                    location.reload();
                }
            });
        });
    });
}
