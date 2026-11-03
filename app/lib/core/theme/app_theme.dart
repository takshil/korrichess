import 'package:flutter/material.dart';

/// Thèmes Korrichess (clair et sombre), définis dès le premier écran.
/// Les couleurs du korrigan sont volontairement chaudes et lisibles,
/// y compris sur échiquier (contraste des cases et des pièces).
class AppTheme {
  static const _brand = Color(0xFFB45309);
  static const _brandDark = Color(0xFFF59E0B);

  static ThemeData get light => _base(
        colorScheme: ColorScheme.fromSeed(
          seedColor: _brand,
          brightness: Brightness.light,
        ),
      );

  static ThemeData get dark => _base(
        colorScheme: ColorScheme.fromSeed(
          seedColor: _brandDark,
          brightness: Brightness.dark,
        ),
      );

  static ThemeData _base({required ColorScheme colorScheme}) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: colorScheme.surface,
      appBarTheme: AppBarTheme(
        backgroundColor: colorScheme.surface,
        foregroundColor: colorScheme.onSurface,
      ),
    );
  }
}
