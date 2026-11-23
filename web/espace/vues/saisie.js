/* Vue saisie manuelle d'une partie de tournoi (S5 + S7, issue #25). */

import { api } from '../api.js';
import { damierSaisie, fenVersCarte, svgPiece } from '../echiquier.js';
import { echapperHtml, vue } from '../ui.js';

let saisie = null;
let naviguer = null;

export function vueSaisie(id, aller) {
    naviguer = aller;
    saisie = {
        id: id || null,
        moves: [],
        fen: null,
        san: [],
        legal: [],
        destinations: [],
        invalidPly: null,
        invalidMove: null,
        verification: true,
        couleur: 'white',
        selection: null,
        fiche: {},
    };

    vue().innerHTML = '<p>Chargement…</p>';
    const chargement = id
        ? api('/games/' + id).then((r) => {
            if (r.status !== 200) { return r; }
            const d = r.body;
            saisie.moves = (d.raw_moves && d.raw_moves.length > 0) ? d.raw_moves : (d.moves_uci || []);
            saisie.couleur = d.color === 'black' ? 'black' : 'white';
            saisie.fiche = {
                played_at: String(d.played_at || '').slice(0, 10),
                opponent: d.opponent || '',
                opponent_rating: d.opponent_rating,
                result: d.result === '*' ? '' : (d.result || ''),
                time_control: d.time_control || '',
                tournament: d.tournament || '',
                round: d.round || '',
            };
            return r;
        })
        : Promise.resolve({ status: 200 });

    chargement.then((r) => {
        if (r.status !== 200) {
            vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
            return;
        }
        construireVueSaisie();
        apresCoupSaisie(true);
    });
}

function construireVueSaisie() {
    const f = saisie.fiche;
    const dateDefaut = f.played_at || new Date().toISOString().slice(0, 10);
    vue().innerHTML =
        '<p><a href="#/bibliotheque">← Bibliothèque</a></p>' +
        '<h1>' + (saisie.id ? 'Corriger une saisie' : 'Saisir une partie') + '</h1>' +
        '<p class="prose" style="color:var(--encre-doux)">Transcris ta feuille de partie : clique tes coups sur l\'échiquier, ' +
        'ou tape-les en notation algébrique (ex. <strong>Nf3</strong>). Clique un coup de la liste pour tronquer à partir de là.</p>' +
        '<div class="saisie-options">' +
        '  <label class="carree"><input type="checkbox" id="saisie-verification" checked> Vérifier les coups (refuse un coup illégal)</label>' +
        '  <p style="color:var(--encre-doux);margin:0.3rem 0 0">Décoche si ta feuille contient une erreur : la partie sera gardée telle quelle et marquée « à vérifier ».</p>' +
        '</div>' +
        '<div class="rejeu-disposition">' +
        '  <div>' +
        '    <div class="echiquier saisie-echiquier" id="saisie-echiquier" aria-label="Échiquier de saisie"></div>' +
        '    <p class="rejeu-position" id="saisie-statut"></p>' +
        '    <form id="saisie-form-san" style="display:flex;gap:0.5rem;flex-wrap:wrap">' +
        '      <div class="champ" style="flex:1;min-width:9rem;margin:0"><label for="saisie-san-texte">Tape un coup (ex. Nf3)</label>' +
        '        <input id="saisie-san-texte" type="text" autocomplete="off"></div>' +
        '      <button class="bouton bouton-secondaire" type="submit" style="align-self:flex-end">Ajouter</button>' +
        '    </form>' +
        '    <div id="saisie-promotion" hidden style="display:flex;gap:0.5rem;justify-content:center;margin-top:0.6rem"></div>' +
        '    <p style="margin-top:0.8rem"><button type="button" class="bouton bouton-secondaire" id="saisie-retirer">↩ Retirer le dernier coup</button></p>' +
        '  </div>' +
        '  <div class="coups" id="saisie-liste"></div>' +
        '</div>' +
        '<h2>La fiche de la partie</h2>' +
        '<form class="filtres" id="saisie-fiche">' +
        '  <div class="champ"><label for="s-date">Date</label>' +
        '    <input id="s-date" type="date" value="' + echapperHtml(dateDefaut) + '" required></div>' +
        '  <div class="champ"><label for="s-adversaire">Adversaire</label>' +
        '    <input id="s-adversaire" type="text" value="' + echapperHtml(f.opponent || '') + '" required maxlength="64"></div>' +
        '  <div class="champ"><label for="s-classement">Classement adverse (facultatif)</label>' +
        '    <input id="s-classement" type="number" min="0" max="5000" value="' + echapperHtml(f.opponent_rating || '') + '"></div>' +
        '  <div class="champ"><label for="s-couleur">Tu jouais…</label>' +
        '    <select id="s-couleur"><option value="white"' + (saisie.couleur === 'white' ? ' selected' : '') + '>les blancs</option>' +
        '      <option value="black"' + (saisie.couleur === 'black' ? ' selected' : '') + '>les noirs</option></select></div>' +
        '  <div class="champ"><label for="s-resultat">Résultat</label>' +
        '    <select id="s-resultat">' +
        '      <option value=""' + (f.result === '' ? ' selected' : '') + '>je ne sais pas</option>' +
        '      <option value="1-0"' + (f.result === '1-0' ? ' selected' : '') + '>1–0</option>' +
        '      <option value="0-1"' + (f.result === '0-1' ? ' selected' : '') + '>0–1</option>' +
        '      <option value="1/2-1/2"' + (f.result === '1/2-1/2' ? ' selected' : '') + '>nulle</option>' +
        '    </select></div>' +
        '  <div class="champ"><label for="s-cadence">Cadence (ex. 15+10)</label>' +
        '    <input id="s-cadence" type="text" maxlength="24" value="' + echapperHtml(f.time_control || '') + '"></div>' +
        '  <div class="champ"><label for="s-tournoi">Tournoi</label>' +
        '    <input id="s-tournoi" type="text" maxlength="160" value="' + echapperHtml(f.tournament || '') + '"></div>' +
        '  <div class="champ"><label for="s-ronde">Ronde</label>' +
        '    <input id="s-ronde" type="text" maxlength="24" value="' + echapperHtml(f.round || '') + '"></div>' +
        '  <div class="filtres-actions">' +
        '    <button class="bouton bouton-principal" type="submit">' + (saisie.id ? 'Enregistrer la correction' : 'Enregistrer la partie') + '</button>' +
        '  </div>' +
        '  <p class="message-erreur" id="saisie-erreur" role="alert" style="grid-column:1/-1"></p>' +
        '</form>';

    document.getElementById('saisie-verification').addEventListener('change', function () {
        saisie.verification = this.checked;
    });
    document.getElementById('s-couleur').addEventListener('change', function () {
        saisie.couleur = this.value;
        saisie.selection = null;
        saisie.destinations = [];
        rafraichirSaisie();
    });
    document.getElementById('saisie-echiquier').addEventListener('click', (evenement) => {
        const cible = evenement.target.closest ? evenement.target.closest('.case') : null;
        if (cible) { clicCaseSaisie(cible.getAttribute('data-case')); }
    });
    document.getElementById('saisie-form-san').addEventListener('submit', (evenement) => {
        evenement.preventDefault();
        const champ = document.getElementById('saisie-san-texte');
        const valeur = champ.value.trim();
        if (valeur === '') { return; }
        champ.value = '';
        saisie.moves.push(valeur);
        apresCoupSaisie(false);
    });
    document.getElementById('saisie-retirer').addEventListener('click', () => {
        if (saisie.moves.length === 0) { return; }
        saisie.moves.pop();
        apresCoupSaisie(true);
    });
    document.getElementById('saisie-liste').addEventListener('click', (evenement) => {
        const bouton = evenement.target.closest ? evenement.target.closest('button.coup') : null;
        if (!bouton) { return; }
        saisie.moves = saisie.moves.slice(0, Number(bouton.getAttribute('data-tronque')));
        apresCoupSaisie(true);
    });
    document.getElementById('saisie-fiche').addEventListener('submit', envoyerSaisie);
}

function apresCoupSaisie(recharge) {
    return api('/games/manual/preview', { methode: 'POST', body: { moves: saisie.moves } }).then((r) => {
        if (r.status !== 200) {
            const statut = document.getElementById('saisie-statut');
            if (statut) { statut.textContent = 'Prévisualisation impossible, réessaie dans un instant.'; }
            return;
        }
        const b = r.body;
        const nouveauFautif = b.invalid_ply !== null && !recharge && b.invalid_ply === saisie.moves.length - 1;
        if (nouveauFautif && saisie.verification) {
            saisie.moves.pop();
            return apresCoupSaisie(true).then(() => {
                document.getElementById('saisie-statut').textContent =
                    'Ce coup n\'est pas légal ici — vérifie ta feuille, ou décoche la vérification pour l\'enregistrer tel quel.';
            });
        }
        saisie.fen = b.fen;
        saisie.san = b.moves_san || [];
        saisie.legal = b.legal_uci || [];
        saisie.invalidPly = b.invalid_ply;
        saisie.invalidMove = b.invalid_move;
        saisie.trait = b.turn;
        saisie.echec = b.in_check;
        saisie.mat = b.is_checkmate;
        saisie.pat = b.is_stalemate;
        saisie.selection = null;
        saisie.destinations = [];
        rafraichirSaisie();
    });
}

function rafraichirSaisie() {
    const echiquier = document.getElementById('saisie-echiquier');
    if (!echiquier || !saisie.fen) { return; }
    const figee = saisie.invalidPly !== null;
    echiquier.innerHTML = damierSaisie(saisie.fen, saisie.couleur === 'black', saisie.selection, saisie.destinations);
    echiquier.classList.toggle('figee', figee);

    let html = '<ol>';
    for (let i = 0; i < saisie.moves.length; i += 2) {
        html += '<li class="numero">' + (i / 2 + 1) + '.</li>' + ligneCoupSaisie(i);
        html += (i + 1 < saisie.moves.length) ? ligneCoupSaisie(i + 1) : '<li></li>';
    }
    html += '</ol>';
    document.getElementById('saisie-liste').innerHTML = html +
        '<p style="color:var(--encre-doux);margin:0.6rem 0 0">' + saisie.moves.length +
        ' coup(s) — clique un coup pour tronquer à partir de là.</p>';

    const statut = document.getElementById('saisie-statut');
    if (figee) {
        statut.textContent = 'Coup ' + (saisie.invalidPly + 1) + ' (« ' + saisie.invalidMove +
            ' ») impossible à rejouer : la position est figée, tape la suite en texte.';
    } else if (saisie.mat) {
        statut.textContent = 'Échec et mat !';
    } else if (saisie.pat) {
        statut.textContent = 'Pat — la partie est nulle.';
    } else if (saisie.echec) {
        statut.textContent = 'Échec aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + ' !';
    } else {
        statut.textContent = 'Aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + '.';
    }
}

function ligneCoupSaisie(i) {
    const douteux = saisie.invalidPly !== null && i >= saisie.invalidPly;
    const affiche = douteux ? String(saisie.moves[i]) : saisie.san[i];
    return '<li><button type="button" class="coup' + (douteux ? ' douteux' : '') + '" data-tronque="' + i +
        '" title="Retirer à partir de ce coup">' + echapperHtml(affiche) + (douteux ? ' ⚠' : '') + '</button></li>';
}

function clicCaseSaisie(nom) {
    if (saisie.invalidPly !== null) {
        document.getElementById('saisie-statut').textContent =
            'Position figée au coup douteux : corrige la liste ci-contre pour reprendre.';
        return;
    }
    if (saisie.selection === null) {
        const carte = fenVersCarte(saisie.fen);
        const piece = carte[nom];
        if (!piece) { return; }
        const estBlanc = piece === piece.toUpperCase();
        if ((saisie.trait === 'white') !== estBlanc) {
            document.getElementById('saisie-statut').textContent =
                'Ce n\'est pas le trait — aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + '.';
            return;
        }
        saisie.destinations = [];
        saisie.legal.forEach((m) => {
            if (m.slice(0, 2) === nom && saisie.destinations.indexOf(m.slice(2, 4)) < 0) {
                saisie.destinations.push(m.slice(2, 4));
            }
        });
        if (saisie.destinations.length === 0) { return; }
        saisie.selection = nom;
        rafraichirSaisie();
        return;
    }
    if (saisie.selection === nom) {
        saisie.selection = null;
        saisie.destinations = [];
        rafraichirSaisie();
        return;
    }
    const de = saisie.selection;
    const candidats = saisie.legal.filter((m) => m.slice(0, 2) === de && m.slice(2, 4) === nom);
    saisie.selection = null;
    saisie.destinations = [];
    if (candidats.length === 0) {
        rafraichirSaisie();
        return;
    }
    if (candidats.length === 1) {
        saisie.moves.push(candidats[0]);
        apresCoupSaisie(false);
        return;
    }
    const zone = document.getElementById('saisie-promotion');
    const base = de + nom;
    zone.innerHTML = ['q', 'r', 'b', 'n'].map((p) => {
        const pieceChar = saisie.trait === 'white' ? p.toUpperCase() : p;
        return '<button type="button" class="bouton bouton-secondaire" data-coup="' + base + p + '">' + svgPiece(pieceChar) + '</button>';
    }).join('');
    zone.hidden = false;
    zone.querySelectorAll('button').forEach((bouton) => {
        bouton.addEventListener('click', () => {
            zone.hidden = true;
            zone.innerHTML = '';
            saisie.moves.push(bouton.getAttribute('data-coup'));
            apresCoupSaisie(false);
        });
    });
    rafraichirSaisie();
}

function envoyerSaisie(evenement) {
    evenement.preventDefault();
    const erreur = document.getElementById('saisie-erreur');
    erreur.textContent = '';
    if (saisie.moves.length === 0) {
        erreur.textContent = 'Ajoute au moins un coup avant d\'enregistrer.';
        return;
    }
    const corps = {
        moves: saisie.moves,
        validate_moves: saisie.verification,
        played_at: document.getElementById('s-date').value,
        opponent: document.getElementById('s-adversaire').value.trim(),
        opponent_rating: document.getElementById('s-classement').value === '' ? null : Number(document.getElementById('s-classement').value),
        color: document.getElementById('s-couleur').value,
        result: document.getElementById('s-resultat').value || '*',
        time_control: document.getElementById('s-cadence').value.trim() || null,
        tournament: document.getElementById('s-tournoi').value.trim() || null,
        round: document.getElementById('s-ronde').value.trim() || null,
    };
    const requete = saisie.id
        ? api('/games/' + saisie.id + '/manual', { methode: 'PUT', body: corps })
        : api('/games/manual', { methode: 'POST', body: corps });
    requete.then((r) => {
        if (r.status === 201 || r.status === 200) {
            naviguer('#/rejeu/' + r.body.id);
            return;
        }
        if (r.body.error === 'coup_invalide') {
            erreur.textContent = 'Le coup ' + (r.body.ply + 1) + ' (« ' + r.body.move +
                ' ») n\'est pas légal et la vérification est active. Corrige-le dans la liste ou décoche la vérification.';
            return;
        }
        if (r.body.error === 'pas_une_saisie_manuelle') {
            erreur.textContent = 'Cette partie n\'a pas été saisie à la main : elle ne peut pas être corrigée ici.';
            return;
        }
        if (r.status === 422) {
            erreur.textContent = 'Vérifie la fiche : date et adversaire sont obligatoires.';
            return;
        }
        erreur.textContent = 'Enregistrement impossible, réessaie dans un instant.';
    });
}
