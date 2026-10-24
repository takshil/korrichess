import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/theme/app_theme.dart';

void main() => runApp(const KorrichessApp());

class KorrichessApp extends StatelessWidget {
  const KorrichessApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Korrichess',
      themeMode: ThemeMode.system,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      supportedLocales: const [Locale('fr')],
      localizationsDelegates: const [
        ...GlobalMaterialLocalizations.delegates,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: const HomePage(),
    );
  }
}

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Korrichess')),
      body: const Center(child: Text('Bienvenue dans Korrichess !')),
    );
  }
}
