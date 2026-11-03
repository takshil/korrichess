# app/ — Application mobile Korrichess (Flutter)

Base unique Android + iOS.

## Périmètre actuel (issue #14)

- Squelette Flutter avec **thèmes clair et sombre** (`lib/core/theme/app_theme.dart`),
  basculés automatiquement selon le réglage système.
- **i18n** : français au lancement, aucune chaîne en dur dans les écrans
  (`lib/core/i18n/`). Prêt pour l'ajout de langues en V4.
- Écran d'accueil provisoire (`lib/main.dart`).

## Conventions

- Null safety stricte ; lints `flutter_lints`.
- État : pattern simple type Riverpod à introduire avec la première feature
  métier (issue dédiée) — pas d' state management dans ce squelette.
- Toute chaîne utilisateur passe par `core/i18n`.
- Toute couleur passe par le `ColorScheme` du thème.

## Lancer le projet

```bash
flutter pub get
flutter run
```

> Le SDK Flutter n'est pas requis pour relire ce squelette, mais nécessaire
> pour compiler et exécuter.
