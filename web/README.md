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

`espace/` — application JavaScript légère appelant `/api/v1` (connexion,
tableau de bord, bibliothèque, rejeu, compte). Livrée dans la MR dédiée.

## Déploiement

Le job `api:deploy` copie ce dossier dans la racine web (ADR-003) :
`scripts/deploy-ftp.sh` en fait un `mirror` lftp, sans étape de build.
