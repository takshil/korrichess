# core/i18n

Internationalisation de l'application. Le français est la langue de lancement ;
la structure (clés de traduction, pas de texte en dur) doit permettre
l'ajout d'autres langues (V4) sans refactoring. Les clés vivent dans
`strings.fr.dart` et s'utilisent via `context.strings.<cle>`.
