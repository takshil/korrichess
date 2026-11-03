import 'package:flutter/material.dart';

/// Chaînes françaises. Aucun texte affiché ne doit être en dur dans les
/// écrans : toute nouvelle chaîne passe par ici (puis par les fichiers
/// ARB quand l'internationalisation s'élargira).
class Strings {
  static const appTitle = 'Korrichess';
  static const homeWelcome = "Bienvenue dans Korrichess !";
}

/// Accès aux chaînes : `context.strings.homeWelcome`.
extension StringsX on BuildContext {
  Strings get strings => Strings();
}
