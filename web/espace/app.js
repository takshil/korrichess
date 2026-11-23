/* Korrichess — routeur de l'espace web (ADR-005, modules ES sans étape de build). */

import { api, jeton, purgerJeton } from './api.js';
import { vueBibliotheque } from './vues/bibliotheque.js';
import { vueCompte } from './vues/compte.js';
import { vueConnexion } from './vues/connexion.js';
import { detacherClavierRejeu, vueRejeu } from './vues/rejeu.js';
import { vueSaisie } from './vues/saisie.js';
import { vueTableauDeBord } from './vues/tableau-de-bord.js';

function aller(hash) {
    if (location.hash !== hash) {
        location.hash = hash;
    } else {
        router();
    }
}

function router() {
    const hash = location.hash || '#/';
    document.body.classList.toggle('sans-session', jeton() === null);
    detacherClavierRejeu();

    if (jeton() === null && hash !== '#/connexion') {
        location.hash = '#/connexion';
        return;
    }
    if (jeton() !== null && (hash === '#/' || hash === '')) {
        location.hash = '#/tableau-de-bord';
        return;
    }

    if (hash === '#/connexion') { vueConnexion(aller); return; }
    if (hash === '#/tableau-de-bord') { vueTableauDeBord(); return; }
    if (hash === '#/bibliotheque') { vueBibliotheque(0, aller); return; }
    if (hash.indexOf('#/bibliotheque/') === 0) { vueBibliotheque(Number(hash.slice('#/bibliotheque/'.length)) || 0, aller); return; }
    if (hash.indexOf('#/rejeu/') === 0) { vueRejeu(Number(hash.slice('#/rejeu/'.length))); return; }
    if (hash === '#/saisie') { vueSaisie(null, aller); return; }
    if (hash.indexOf('#/saisie/') === 0) { vueSaisie(Number(hash.slice('#/saisie/'.length)), aller); return; }
    if (hash === '#/compte') { vueCompte(); return; }

    location.hash = jeton() !== null ? '#/tableau-de-bord' : '#/connexion';
}

document.getElementById('deconnexion').addEventListener('click', () => {
    api('/auth/logout', { methode: 'POST' }).then(() => {
        purgerJeton();
        location.hash = '#/connexion';
        router();
    });
});

window.addEventListener('hashchange', router);
router();
