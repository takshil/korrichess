/* Korrichess — espace web (issue #12). Application JavaScript légère, sans
   framework (ADR-005). Routage par hash (#/…), appels à /api/v1 avec le
   jeton Sanctum. Aucune donnée n'est rendue sans échappement HTML. */
'use strict';

(function () {
    var API = (typeof window.KORRICHESS_API === 'string' && window.KORRICHESS_API) || '/api/v1';
    var CLE_JETON = 'korrichess_jeton';

    var PIECES = {
        P: '\u2659', N: '\u2658', B: '\u2657', R: '\u2656', Q: '\u2655', K: '\u2654',
        p: '\u265F', n: '\u265E', b: '\u265D', r: '\u265C', q: '\u265B', k: '\u265A'
    };

    // ------------------------------------------------ utilitaires

    function echapperHtml(valeur) {
        return String(valeur === null || valeur === undefined ? '' : valeur)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function jeton() {
        try { return localStorage.getItem(CLE_JETON); } catch (e) { return null; }
    }
    function poserJeton(t) { try { localStorage.setItem(CLE_JETON, t); } catch (e) { /* stockage indisponible */ } }
    function purgerJeton() { try { localStorage.removeItem(CLE_JETON); } catch (e) { /* idem */ } }

    function api(chemin, options) {
        options = options || {};
        var entetes = { 'Accept': 'application/json' };
        if (options.body !== undefined) { entetes['Content-Type'] = 'application/json'; }
        if (jeton() !== null) { entetes['Authorization'] = 'Bearer ' + jeton(); }
        return fetch(API + chemin, {
            method: options.methode || 'GET',
            headers: entetes,
            body: options.body !== undefined ? JSON.stringify(options.body) : undefined
        }).then(function (reponse) {
            return reponse.json().catch(function () { return {}; }).then(function (corps) {
                if (reponse.status === 401 && chemin.indexOf('/auth/') !== 0) {
                    purgerJeton();
                    aller('#/connexion');
                }
                return { status: reponse.status, body: corps };
            });
        });
    }

    function aller(hash) { if (location.hash !== hash) { location.hash = hash; } else { router(); } }

    function courteDate(valeur) {
        if (!valeur) { return '—'; }
        var date = new Date(String(valeur).replace(/\./g, '-').replace('T', ' '));
        if (isNaN(date.getTime())) { return String(valeur).slice(0, 10); }
        return date.toLocaleDateString('fr-FR');
    }

    function libelleResultat(partie) {
        if (!partie.result) { return '—'; }
        if (partie.result.indexOf('1/2') === 0 || partie.result.indexOf('\u00BD') === 0) { return 'nulle'; }
        var blancGagne = partie.result === '1-0';
        if (partie.color === 'white') { return blancGagne ? 'victoire' : 'défaite'; }
        return blancGagne ? 'défaite' : 'victoire';
    }

    function telecharger(nom, texte) {
        var blob = new Blob([texte], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var lien = document.createElement('a');
        lien.href = url;
        lien.download = nom;
        document.body.appendChild(lien);
        lien.click();
        lien.remove();
        URL.revokeObjectURL(url);
    }

    var vue = function () { return document.getElementById('vue'); };

    // ------------------------------------------------ connexion / inscription (W1)

    function vueConnexion() {
        document.body.classList.add('sans-session');
        vue().innerHTML =
            '<div class="authencarte">' +
            '  <h1>Bienvenue !</h1>' +
            '  <div class="authent-onglets" role="tablist">' +
            '    <button type="button" id="onglet-connexion" role="tab" aria-selected="true">Se connecter</button>' +
            '    <button type="button" id="onglet-inscription" role="tab" aria-selected="false">Créer un compte</button>' +
            '  </div>' +
            '  <form id="formulaire-connexion">' +
            '    <div class="champ"><label for="courriel">Adresse e-mail</label>' +
            '      <input id="courriel" name="email" type="email" autocomplete="email" required></div>' +
            '    <div class="champ"><label for="motdepasse">Mot de passe</label>' +
            '      <input id="motdepasse" name="password" type="password" autocomplete="current-password" required></div>' +
            '    <p class="message-erreur" id="erreur-connexion" role="alert"></p>' +
            '    <button class="bouton bouton-principal" type="submit">Se connecter</button>' +
            '  </form>' +
            '  <form id="formulaire-inscription" hidden>' +
            '    <div class="champ"><label for="courriel2">Adresse e-mail</label>' +
            '      <input id="courriel2" name="email" type="email" autocomplete="email" required></div>' +
            '    <div class="champ"><label for="motdepasse2">Mot de passe (8 caractères min.)</label>' +
            '      <input id="motdepasse2" name="password" type="password" autocomplete="new-password" minlength="8" required></div>' +
            '    <p class="message-erreur" id="erreur-inscription" role="alert"></p>' +
            '    <button class="bouton bouton-principal" type="submit">Créer mon compte</button>' +
            '  </form>' +
            '</div>';

        var formulaireConnexion = document.getElementById('formulaire-connexion');
        var formulaireInscription = document.getElementById('formulaire-inscription');

        document.getElementById('onglet-connexion').addEventListener('click', function () {
            formulaireConnexion.hidden = false;
            formulaireInscription.hidden = true;
            this.setAttribute('aria-selected', 'true');
            document.getElementById('onglet-inscription').setAttribute('aria-selected', 'false');
        });
        document.getElementById('onglet-inscription').addEventListener('click', function () {
            formulaireConnexion.hidden = true;
            formulaireInscription.hidden = false;
            this.setAttribute('aria-selected', 'true');
            document.getElementById('onglet-connexion').setAttribute('aria-selected', 'false');
        });

        formulaireConnexion.addEventListener('submit', function (evenement) {
            evenement.preventDefault();
            var erreur = document.getElementById('erreur-connexion');
            erreur.textContent = '';
            api('/auth/login', {
                methode: 'POST',
                body: {
                    email: document.getElementById('courriel').value,
                    password: document.getElementById('motdepasse').value
                }
            }).then(function (r) {
                if (r.status === 200 && r.body.token) {
                    poserJeton(r.body.token);
                    document.body.classList.remove('sans-session');
                    aller('#/tableau-de-bord');
                    return;
                }
                erreur.textContent = r.body.error === 'identifiants_invalides'
                    ? 'E-mail ou mot de passe incorrect.'
                    : 'Connexion impossible, réessaie dans un instant.';
            });
        });

        formulaireInscription.addEventListener('submit', function (evenement) {
            evenement.preventDefault();
            var erreur = document.getElementById('erreur-inscription');
            erreur.textContent = '';
            api('/auth/register', {
                methode: 'POST',
                body: {
                    email: document.getElementById('courriel2').value,
                    password: document.getElementById('motdepasse2').value
                }
            }).then(function (r) {
                if (r.status === 201 && r.body.token) {
                    poserJeton(r.body.token);
                    document.body.classList.remove('sans-session');
                    aller('#/tableau-de-bord');
                    return;
                }
                if (r.status === 422 && r.body.errors) {
                    if (r.body.errors.password) { erreur.textContent = 'Le mot de passe est trop court (8 caractères minimum).'; return; }
                    if (r.body.errors.email) { erreur.textContent = 'Cette adresse e-mail ne semble pas valide.'; return; }
                }
                if (r.body.error === 'email_deja_utilise') { erreur.textContent = 'Cette adresse a déjà un compte. Connecte-toi plutôt.'; return; }
                if (r.body.error === 'email_invalide') { erreur.textContent = 'Cette adresse e-mail ne semble pas valide.'; return; }
                erreur.textContent = 'Inscription impossible, réessaie dans un instant.';
            });
        });
    }

    // ------------------------------------------------ tableau de bord (W2)

    function vueTableauDeBord() {
        vue().innerHTML = '<p>Chargement…</p>';
        api('/games?limit=200&sort=played_at&order=desc').then(function (r) {
            if (r.status !== 200) { return; }
            var parties = r.body.games || [];
            var total = r.body.total || parties.length;
            var victoires = 0, nulles = 0, defaites = 0;
            parties.forEach(function (p) {
                var l = libelleResultat(p);
                if (l === 'victoire') { victoires++; } else if (l === 'nulle') { nulles++; } else if (l === 'défaite') { defaites++; }
            });
            var taux = total > 0 ? Math.round((victoires / total) * 100) : 0;
            var recentes = parties.slice(0, 8).map(function (p) {
                return '<tr>' +
                    '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
                    '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || 'adversaire inconnu') + '</a></td>' +
                    '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
                    '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
                    '<td>' + echapperHtml(p.source || '') + '</td>' +
                    '</tr>';
            }).join('');

            vue().innerHTML =
                '<h1>Tableau de bord</h1>' +
                '<div class="stat-grille">' +
                '  <div class="stat-carte"><span class="valeur">' + total + '</span><span class="libelle">parties</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + victoires + '</span><span class="libelle">victoires</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + nulles + '</span><span class="libelle">nulles</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + defaites + '</span><span class="libelle">défaites</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + taux + ' %</span><span class="libelle">de victoires</span></div>' +
                '</div>' +

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

            var retour = document.getElementById('sync-retour');
            document.getElementById('formulaire-sync').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                retour.textContent = '';
                api('/sync', {
                    methode: 'POST',
                    body: {
                        platform: document.getElementById('sync-plateforme').value,
                        username: document.getElementById('sync-pseudo').value
                    }
                }).then(function (res) {
                    if (res.status === 200) {
                        retour.textContent = res.body.imported + ' partie(s) importée(s), ' +
                            res.body.duplicates_ignores + ' déjà connue(s).';
                        setTimeout(function () { vueTableauDeBord(); }, 1200);
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

    // ------------------------------------------------ bibliothèque (W3)

    var filtresEnMemoire = {};

    function vueBibliotheque(offset) {
        offset = offset || 0;
        vue().innerHTML = '<p>Chargement…</p>';

        function charger() {
            var requete = '/games?limit=20&offset=' + offset + '&sort=played_at&order=desc';
            var courants = filtresEnMemoire;
            Object.keys(courants).forEach(function (cle) {
                if (courants[cle] !== '' && courants[cle] !== null && courants[cle] !== undefined) {
                    requete += '&' + encodeURIComponent(cle) + '=' + encodeURIComponent(courants[cle]);
                }
            });
            api(requete).then(function (r) {
                if (r.status !== 200) { return; }
                var total = r.body.total || 0;
                var lignes = (r.body.games || []).map(function (p) {
                    return '<tr>' +
                        '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
                        '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || '?') + '</a></td>' +
                        '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
                        '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
                        '<td>' + echapperHtml(p.time_control || '—') + '</td>' +
                        '<td>' + echapperHtml(p.opening || '—') + '</td>' +
                        '<td>' + echapperHtml(p.source || '—') + (p.favorite ? ' ★' : '') + '</td>' +
                        '</tr>';
                }).join('');

                var pagePrecedente = offset >= 20 ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset - 20) + '">Précédentes</a>' : '';
                var pageSuivante = offset + 20 < total ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset + 20) + '">Suivantes</a>' : '';

                vue().innerHTML =
                    '<h1>Bibliothèque</h1>' +
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

                document.getElementById('formulaire-filtres').addEventListener('submit', function (evenement) {
                    evenement.preventDefault();
                    filtresEnMemoire = {
                        q: document.getElementById('f-q').value,
                        source: document.getElementById('f-source').value,
                        color: document.getElementById('f-couleur').value,
                        result: document.getElementById('f-resultat').value,
                        favorite: document.getElementById('f-favori').checked ? '1' : ''
                    };
                    aller('#/bibliotheque/0');
                });
                document.getElementById('f-reset').addEventListener('click', function () {
                    filtresEnMemoire = {};
                    aller('#/bibliotheque/0');
                });
            });
        }
        charger();
    }

    // ------------------------------------------------ rejeu (W4)

    var ecouteurClavier = null;

    function damierDepuisFen(fen, inverser) {
        var placement = fen.split(' ')[0];
        var cases = [];
        var ligne = 0, colonne = 0;
        for (var i = 0; i < placement.length; i++) {
            var c = placement[i];
            if (c === '/') { colonne = 0; ligne++; continue; }
            if (c >= '1' && c <= '8') {
                for (var j = 0; j < Number(c); j++) {
                    cases.push({ piece: null, clair: (ligne + colonne) % 2 === 0 });
                    colonne++;
                }
                continue;
            }
            cases.push({ piece: c, clair: (ligne + colonne) % 2 === 0 });
            colonne++;
        }
        if (inverser) { cases.reverse(); }
        return cases.map(function (cellule) {
            var glyphe = cellule.piece ? (PIECES[cellule.piece] || '?') : '';
            return '<div class="case ' + (cellule.clair ? 'clair' : 'fonce') + '" aria-hidden="true">' + glyphe + '</div>';
        }).join('');
    }

    function vueRejeu(id) {
        vue().innerHTML = '<p>Chargement de la partie…</p>';
        Promise.all([
            api('/games/' + id),
            api('/games/' + id + '/replay')
        ]).then(function (reponses) {
            var detail = reponses[0], rejeu = reponses[1];
            if (detail.status !== 200) {
                vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
                return;
            }
            var positions = (rejeu.status === 200 && rejeu.body.positions) ? rejeu.body.positions : null;
            var sans = (rejeu.status === 200 && rejeu.body.moves_san) ? rejeu.body.moves_san : [];
            var inverser = detail.body.color === 'black';
            var courant = 0;

            var listeCoups = '';
            for (var i = 0; i < sans.length; i += 2) {
                var numero = (i / 2) + 1;
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

            if (!positions) { return; }

            function afficher(ply) {
                courant = Math.max(0, Math.min(ply, positions.length - 1));
                document.getElementById('echiquier').innerHTML = damierDepuisFen(positions[courant], inverser);
                document.getElementById('rejeu-position').textContent = courant === 0
                    ? 'Position de départ'
                    : 'Coup ' + Math.ceil(courant / 2) + (courant % 2 === 1 ? '… ' : '. ') + (sans[courant - 1] || '');
                var boutons = document.querySelectorAll('#liste-coups button.coup');
                for (var b = 0; b < boutons.length; b++) {
                    boutons[b].classList.toggle('courant', Number(boutons[b].getAttribute('data-ply')) === courant - 1);
                }
            }

            document.getElementById('cp-debut').addEventListener('click', function () { afficher(0); });
            document.getElementById('cp-avant').addEventListener('click', function () { afficher(courant - 1); });
            document.getElementById('cp-apres').addEventListener('click', function () { afficher(courant + 1); });
            document.getElementById('cp-fin').addEventListener('click', function () { afficher(positions.length - 1); });
            Array.prototype.forEach.call(document.querySelectorAll('#liste-coups button.coup'), function (bouton) {
                bouton.addEventListener('click', function () { afficher(Number(bouton.getAttribute('data-ply')) + 1); });
            });

            if (ecouteurClavier) { document.removeEventListener('keydown', ecouteurClavier); }
            ecouteurClavier = function (evenement) {
                if (evenement.key === 'ArrowLeft') { afficher(courant - 1); }
                if (evenement.key === 'ArrowRight') { afficher(courant + 1); }
            };
            document.addEventListener('keydown', ecouteurClavier);
            afficher(0);
        });
    }

    // ------------------------------------------------ compte (W7 + W8)

    function vueCompte() {
        vue().innerHTML = '<p>Chargement…</p>';
        Promise.all([
            api('/me'),
            api('/me/settings')
        ]).then(function (reponses) {
            var moi = reponses[0], reglages = reponses[1];
            if (moi.status !== 200) { return; }
            var comptes = moi.body.linked_accounts || [];

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
                (comptes.length === 0 ? '<li>Aucun pseudo lié.</li>' : comptes.map(function (c) {
                    return '<li><span>' + echapperHtml(c.platform) + ' — ' + echapperHtml(c.username) + '</span>' +
                        '<button type="button" class="bouton bouton-secondaire delier" data-platform="' + echapperHtml(c.platform) +
                        '" data-username="' + echapperHtml(c.username) + '">Délier</button></li>';
                }).join('')) +
                '    </ul>' +
                '    <form id="formulaire-lier">' +
                '      <div class="champ"><label for="l-plateforme">Plateforme</label>' +
                '        <select id="l-plateforme"><option value="lichess">Lichess</option><option value="chesscom">Chess.com</option></select></div>' +
                '      <div class="champ"><label for="l-pseudo">Pseudo</label>' +
                '        <input id="l-pseudo" type="text" required></div>' +
                '      <button class="bouton bouton-secondaire" type="submit">Lier ce pseudo</button>' +
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
                ['classique', 'bois', 'contraste'].map(function (s) {
                    return '<option value="' + s + '"' + (reglages.body.board_style === s ? ' selected' : '') + '>' + s + '</option>';
                }).join('') + '</select></div>' +
                '      <div class="champ"><label for="r-pieces">Style de pièces</label>' +
                '        <select id="r-pieces">' +
                ['classique', 'minimaliste'].map(function (s) {
                    return '<option value="' + s + '"' + (reglages.body.piece_style === s ? ' selected' : '') + '>' + s + '</option>';
                }).join('') + '</select></div>' +
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

            document.getElementById('formulaire-profil').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                api('/me', {
                    methode: 'PATCH',
                    body: {
                        display_name: document.getElementById('p-nom').value,
                        club: document.getElementById('p-club').value,
                        rating: document.getElementById('p-classement').value === '' ? null : Number(document.getElementById('p-classement').value)
                    }
                }).then(function (r) {
                    document.getElementById('retour-profil').textContent = r.status === 200 ? 'Profil enregistré.' : 'Enregistrement impossible.';
                });
            });

            document.getElementById('formulaire-lier').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                api('/me/linked-accounts', {
                    methode: 'POST',
                    body: {
                        platform: document.getElementById('l-plateforme').value,
                        username: document.getElementById('l-pseudo').value
                    }
                }).then(function (r) {
                    var retour = document.getElementById('retour-lier');
                    retour.textContent = r.body.message === 'compte_lie' ? 'Pseudo lié !'
                        : r.body.message === 'deja_lie' ? 'Ce pseudo est déjà lié.'
                        : 'Liaison impossible.';
                    if (r.status === 201) { setTimeout(vueCompte, 800); }
                });
            });

            Array.prototype.forEach.call(document.querySelectorAll('button.delier'), function (bouton) {
                bouton.addEventListener('click', function () {
                    api('/me/linked-accounts?platform=' + encodeURIComponent(bouton.getAttribute('data-platform')) +
                        '&username=' + encodeURIComponent(bouton.getAttribute('data-username')), { methode: 'DELETE' })
                        .then(function () { vueCompte(); });
                });
            });

            document.getElementById('formulaire-reglages').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                var corps = {
                    theme: document.getElementById('r-theme').value,
                    language: document.getElementById('r-langue').value,
                    board_style: document.getElementById('r-echiquier').value,
                    piece_style: document.getElementById('r-pieces').value
                };
                api('/me/settings', { methode: 'PATCH', body: corps }).then(function (r) {
                    var retour = document.getElementById('retour-reglages');
                    retour.textContent = r.status === 200 ? 'Préférences enregistrées.' : 'Enregistrement impossible.';
                    if (r.status === 200) {
                        document.documentElement.setAttribute('data-theme', corps.theme);
                        try { localStorage.setItem('korrichess_theme', corps.theme); } catch (e) { /* stockage indisponible */ }
                    }
                });
            });

            document.getElementById('bouton-export').addEventListener('click', function () {
                api('/me/export').then(function (r) {
                    if (r.status === 200) {
                        telecharger('korrichess-export-' + new Date().toISOString().slice(0, 10) + '.json',
                            JSON.stringify(r.body, null, 2));
                    }
                });
            });

            document.getElementById('confirmation').addEventListener('input', function () {
                document.getElementById('bouton-supprimer').disabled = this.value !== 'SUPPRIMER';
            });
            document.getElementById('bouton-supprimer').addEventListener('click', function () {
                api('/me', { methode: 'DELETE' }).then(function (r) {
                    if (r.body.message === 'compte_supprime') {
                        purgerJeton();
                        location.hash = '';
                        location.reload();
                    }
                });
            });
        });
    }

    // ------------------------------------------------ routeur

    function router() {
        var hash = location.hash || '#/';
        document.body.classList.toggle('sans-session', jeton() === null);

        if (ecouteurClavier) { document.removeEventListener('keydown', ecouteurClavier); ecouteurClavier = null; }

        if (jeton() === null && hash !== '#/connexion') {
            location.hash = '#/connexion';
            return;
        }
        if (jeton() !== null && (hash === '#/' || hash === '')) {
            location.hash = '#/tableau-de-bord';
            return;
        }

        if (hash === '#/connexion') { vueConnexion(); return; }
        if (hash === '#/tableau-de-bord') { vueTableauDeBord(); return; }
        if (hash === '#/bibliotheque') { vueBibliotheque(0); return; }
        if (hash.indexOf('#/bibliotheque/') === 0) { vueBibliotheque(Number(hash.slice('#/bibliotheque/'.length)) || 0); return; }
        if (hash.indexOf('#/rejeu/') === 0) { vueRejeu(Number(hash.slice('#/rejeu/'.length))); return; }
        if (hash === '#/compte') { vueCompte(); return; }

        location.hash = jeton() !== null ? '#/tableau-de-bord' : '#/connexion';
    }

    document.getElementById('deconnexion').addEventListener('click', function () {
        api('/auth/logout', { methode: 'POST' }).then(function () {
            purgerJeton();
            location.hash = '#/connexion';
            router();
        });
    });

    window.addEventListener('hashchange', router);
    router();
}());
