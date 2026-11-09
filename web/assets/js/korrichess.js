/* Korrichess — JS partagé : bascule du thème clair/sombre (persistée) et
   menu mobile de la vitrine. Aucune dépendance, aucun framework. */
(function () {
    'use strict';

    var CLE_THEME = 'korrichess_theme';

    function themeSombreParDefaut() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applique(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(CLE_THEME, theme); } catch (e) { /* stockage indisponible */ }
    }

    function themeActuel() {
        var explicite = document.documentElement.getAttribute('data-theme');
        if (explicite === 'clair' || explicite === 'sombre') { return explicite; }
        return themeSombreParDefaut() ? 'sombre' : 'clair';
    }

    // Le thème mémorisé est appliqué dès que possible (avant le rendu CSS final
    // pour éviter un flash), ici et via l'attribut posé par la page.
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.theme-bascule'), function (bouton) {
            bouton.addEventListener('click', function () {
                applique(themeActuel() === 'sombre' ? 'clair' : 'sombre');
            });
        });

        var menu = document.querySelector('.menu-bascule');
        var nav = document.querySelector('.navigation');
        if (menu && nav) {
            menu.addEventListener('click', function () {
                var ouvert = nav.classList.toggle('ouverte');
                menu.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
            });
        }
    });
}());
