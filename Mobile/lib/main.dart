import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';

import 'providers/auth_provider.dart';
import 'providers/children_provider.dart';
import 'providers/locale_provider.dart';
import 'services/api_service.dart';
import 'theme/app_theme.dart';

import 'screens/login_screen.dart';
import 'screens/home_screen.dart';
import 'screens/teacher/teacher_home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final localeProvider = LocaleProvider();
  await localeProvider.loadLocale();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: localeProvider),
        Provider(create: (_) => ApiService()),
        ChangeNotifierProxyProvider<ApiService, AuthProvider>(
          create: (context) => AuthProvider(context.read<ApiService>()),
          update: (context, api, auth) => auth ?? AuthProvider(api),
        ),
        ChangeNotifierProxyProvider<ApiService, ChildrenProvider>(
          create: (context) => ChildrenProvider(context.read<ApiService>()),
          update: (context, api, children) => children ?? ChildrenProvider(api),
        ),
      ],
      child: const SchoolHubApp(),
    ),
  );
}

class SchoolHubApp extends StatefulWidget {
  const SchoolHubApp({super.key});

  @override
  State<SchoolHubApp> createState() => _SchoolHubAppState();
}

class _SchoolHubAppState extends State<SchoolHubApp> {
  @override
  void initState() {
    super.initState();
    // Check authentication on startup
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthProvider>().checkAuth();
    });
  }

  @override
  Widget build(BuildContext context) {
    final localeProvider = context.watch<LocaleProvider>();

    return MaterialApp(
      title: 'SchoolHub Parent',
      debugShowCheckedModeBanner: false,
      
      // Theme
      theme: AppTheme.lightTheme(localeProvider.languageCode),
      darkTheme: AppTheme.darkTheme(localeProvider.languageCode),
      themeMode: ThemeMode.system,

      // Localization
      locale: localeProvider.locale,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('en'),
        Locale('fr'),
        Locale('ar'),
      ],

      // Navigation
      home: Consumer<AuthProvider>(
        builder: (context, auth, _) {
          if (auth.status == AuthStatus.initial || auth.status == AuthStatus.loading) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          
          if (auth.isAuthenticated) {
            if (auth.user?.role == 'teacher') {
              return const TeacherHomeScreen();
            }
            return const HomeScreen();
          }
          
          return const LoginScreen();
        },
      ),
      routes: {
        '/login': (context) => const LoginScreen(),
        '/home': (context) => const HomeScreen(),
        '/teacher': (context) => const TeacherHomeScreen(),
      },
    );
  }
}
