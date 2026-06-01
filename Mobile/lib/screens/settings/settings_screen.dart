import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/locale_provider.dart';
import '../../theme/app_colors.dart';
import '../attendance/attendance_screen.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final locale = context.watch<LocaleProvider>();
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.settings)),
      body: ListView(
        children: [
          if (auth.user?.role == 'parent') ...[
            ListTile(
              title: Text(l10n.attendance),
              subtitle: Text(l10n.viewAttendance),
              trailing: const Icon(Icons.arrow_forward_ios, size: 16),
              leading: const Icon(Icons.fact_check_outlined, color: AppColors.primary),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const AttendanceScreen()),
                );
              },
            ),
            const Divider(),
          ],
          ListTile(
            title: Text(l10n.language),
            subtitle: Text(locale.languageCode.toUpperCase()),
            trailing: const Icon(Icons.language),
            leading: const Icon(Icons.g_translate_outlined),
            onTap: () {
              // Toggle language for demo purposes
              final newCode = locale.isEnglish ? 'fr' : (locale.isFrench ? 'ar' : 'en');
              locale.setLocale(Locale(newCode));
            },
          ),
          ListTile(
            title: Text(l10n.logout),
            trailing: const Icon(Icons.logout, color: Colors.red),
            leading: const Icon(Icons.exit_to_app, color: Colors.red),
            onTap: () async {
              await auth.logout();
              if (context.mounted) {
                Navigator.of(context).pushReplacementNamed('/login');
              }
            },
          ),
        ],
      ),
    );
  }
}
