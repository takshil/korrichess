# ADR-005 — Site web statique sans framework

Date : 9 novembre 2026 — Statut : accepté — Issues : #11, #12

## Contexte

Le cahier des charges exige une vitrine référencée (accueil, fonctionnalités,
tarifs, FAQ, blog, pages légales) et un espace personnel connecté à la même
API que l'application mobile (W1–W8). L'hébergement est un mutualisé FTP
sans accès SSH ni workers (ADR-003) : PHP tourne pour l'API Laravel, mais
ni Node ni processus long ne sont disponibles.

## Décision

- **Vitrine (issue #11)** : pages HTML statiques, CSS et JavaScript vanilla,
  aucune étape de build, aucun framework.
- **Espace personnel (issue #12)** : application JavaScript légère dans
  `web/espace/`, routage par hash (`#/…`), appels directs à `/api/v1` avec
  les jetons Sanctum. Le routage par hash évite toute réécriture serveur.
- **Thèmes clair/sombre** partagés (vitrine + espace) via variables CSS et
  `prefers-color-scheme`, préférence mémorisée.
- **i18n** : français au lancement. Les textes de la vitrine sont en français ;
  l'introduction de clés de traduction se fera si le site doit être multilingue.

## Justification

- Déployable par simple FTP, servi par Apache, sans Node ni build ;
- aucune dépendance, surface d'attaque et audit minimaux ;
- l'API porte toute la logique métier : le site reste une vue ;
- des pages statiques honnêtes sont rapides, bonnes pour le référencement
  et l'accessibilité.

## Alternatives rejetées

- **Framework JS (React, Vue…)** : étape de build et complexité
  disproportionnées pour un espace de consultation ;
- **Rendu serveur PHP côté site** : couplerait le site au cycle de vie de
  l'API et compliquerait le déploiement mutualisé ;
- **Générateur statique (Astro, Hugo…)** : outillage supplémentaire sans
  gain décisif à cette taille.

## Conséquences

- La duplication des en-têtes et pieds de page entre pages statiques est
  acceptée (pages de petite taille) ;
- le jeton Sanctum vit dans le stockage local du navigateur : la
  déconnexion et la purge du stockage sont documentées dans la politique
  de confidentialité ;
- le `sitemap.xml`, `robots.txt` et les URL canoniques utilisent le domaine
  placeholder `https://korrichess.fr`, à adapter dès que le domaine réel
  sera choisi ;
- toute évolution lourde (rendu serveur, framework) passera par un nouvel ADR.
