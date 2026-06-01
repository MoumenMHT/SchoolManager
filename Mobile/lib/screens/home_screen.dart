import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/children_provider.dart';
import 'children/children_list_screen.dart';
import 'grades/grades_screen.dart';
import 'schedule/schedule_screen.dart';
import 'payments/payment_dashboard_screen.dart';
import 'settings/settings_screen.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;

  final List<Widget> _screens = const [
    ChildrenListScreen(),
    GradesScreen(),
    ScheduleScreen(),
    PaymentDashboardScreen(),
    SettingsScreen(),
  ];

  @override
  void initState() {
    super.initState();
    // Fetch children when home screen loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ChildrenProvider>().fetchChildren();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, -5),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (i) => setState(() => _currentIndex = i),
          items: [
            BottomNavigationBarItem(
              icon: const Icon(Icons.people_outline),
              activeIcon: const Icon(Icons.people),
              label: l10n.myChildren,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.grade_outlined),
              activeIcon: const Icon(Icons.grade),
              label: l10n.grades,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.calendar_today_outlined),
              activeIcon: const Icon(Icons.calendar_today),
              label: l10n.schedule,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.payment_outlined),
              activeIcon: const Icon(Icons.payment),
              label: l10n.payments,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.more_horiz_outlined),
              activeIcon: const Icon(Icons.more_horiz),
              label: l10n.more,
            ),
          ],
        ),
      ),
    );
  }
}
