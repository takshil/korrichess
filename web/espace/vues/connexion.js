/* Vue connexion / inscription (W1). */

import { api, poserJeton } from '../api.js';
import { vue } from '../ui.js';

export function vueConnexion(aller) {
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

    const formulaireConnexion = document.getElementById('formulaire-connexion');
    const formulaireInscription = document.getElementById('formulaire-inscription');

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

    formulaireConnexion.addEventListener('submit', (evenement) => {
        evenement.preventDefault();
        const erreur = document.getElementById('erreur-connexion');
        erreur.textContent = '';
        api('/auth/login', {
            methode: 'POST',
            body: {
                email: document.getElementById('courriel').value,
                password: document.getElementById('motdepasse').value,
            },
        }).then((r) => {
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

    formulaireInscription.addEventListener('submit', (evenement) => {
        evenement.preventDefault();
        const erreur = document.getElementById('erreur-inscription');
        erreur.textContent = '';
        api('/auth/register', {
            methode: 'POST',
            body: {
                email: document.getElementById('courriel2').value,
                password: document.getElementById('motdepasse2').value,
            },
        }).then((r) => {
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
