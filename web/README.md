# web/ — Site Korrichess

Site vitrine + espace personnel connecté à la même API que l'application
mobile (`api/`). Décision technique : site statique sans framework, cf.
`docs/adr/005-site-statique-sans-framework.md`.

## Vitrine (issue #11)

Pages HTML statiques, feuille de style et script partagés dans `assets/`.
Thème clair/sombre persisté, responsive, accessibilité soignée (liens
d'évitement, focus visibles, contrastes AA) et référencement naturel
(`robots.txt`, `sitemap.xml`, URL canoniques).

- `index.html` — accueil : promesse, démonstration en image, appels à l'action ;
- `fonctionnalites.html`, `tarifs.html`, `faq.html`, `blog.html`,
  `contact.html` — pages publiques ;
- `mentions-legales.html`, `cgu.html`, `confidentialite.html` — pages légales ;
- `404.html` — page d'erreur personnalisée.

⚠️ `sitemap.xml`, `robots.txt` et les canoniques utilisent le domaine
placeholder `https://korrichess.fr` : à adapter quand le nom de domaine
sera choisi. L'hébergeur reste à préciser dans `mentions-legales.html`,
et le slogan « Dalc'h mat » à faire valider par un bretonnant (issue #19).

## Espace personnel (issue #12)

`espace/` — application monopage (SPA) JavaScript sans framework, routage
par hash (`#/tableau-de-bord`, `#/bibliotheque`, `#/rejeu/{id}`,
`#/saisie`, `#/saisie/{id}`, `#/compte`) et appels à `/api/v1` avec le
jeton Sanctum (`Authorization: Bearer`). Échappement HTML systématique
avant tout rendu.

- `espace/index.html` — coquille (en-tête, conteneur `#vue`, pied),
  `<meta name="robots" content="noindex">`, thème mémorisé appliqué
  avant le rendu ;
- `espace/espace.css` — styles propres (authentification, statistiques,
  filtres, liste de coups, échiquier de saisie, cartes de compte, zone
  dangereuse) ;
- `espace/app.js` — connexion/inscription (W1), tableau de bord avec
  synchronisation (W2), bibliothèque filtrable et paginée (W3), rejeu
  coup par coup au clavier (W4), compte : profil, pseudos liés,
  préférences, export RGPD, suppression (W7/W8) ;
- saisie manuelle d'une partie papier (S5/S7, issue #25) : échiquier
  cliquable (sélection, destinations, promotion) ou saisie texte SAN,
  troncature en un clic, retrait du dernier coup, fiche de la partie
  (date, adversaire, classement, couleur, résultat, cadence, tournoi,
  ronde). La validation des coups, la génération des coups légaux et le
  rejeu PGN/UCI sont assurés localement par `chess.js` (`espace/partie.js`
  et `espace/vendor/chess.js`) ; la vérification peut être désactivée si
  la feuille contient une erreur : la partie est alors enregistrée telle
  quelle, marquée `needs_review`, et la saisie d'origine est conservée
  (`raw_moves`). Correction possible depuis la bibliothèque (`✎ corriger`,
  `PUT /api/v1/games/{id}/manual`).

L'analyse moteur n'y est pas exposée : les workers de file ne tournent
pas en mutualisé (ADR-003), le rejeu seul est disponible.

## Déploiement

Le job `api:deploy` copie ce dossier dans la racine web (ADR-003) :
`scripts/deploy-ftp.sh` en fait un `mirror` lftp, sans étape de build.
