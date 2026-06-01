import 'package:flutter/material.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../settings/settings_screen.dart';
import 'teacher_dashboard_screen.dart';
import 'teacher_classes_screen.dart';
import 'teacher_exams_screen.dart';
import 'teacher_schedule_screen.dart';

class TeacherHomeScreen extends StatefulWidget {
  const TeacherHomeScreen({super.key});

  @override
  State<TeacherHomeScreen> createState() => _TeacherHomeScreenState();
}

class _TeacherHomeScreenState extends State<TeacherHomeScreen> {
  int _currentIndex = 0;

  void _setIndex(int index) {
    setState(() => _currentIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    final screens = [
      TeacherDashboardScreen(onOpenSchedule: () => _setIndex(3), onOpenClasses: () => _setIndex(1)),
      const TeacherClassesScreen(),
      const TeacherExamsScreen(),
      const TeacherScheduleScreen(),
      const SettingsScreen(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
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
          onTap: _setIndex,
          items: [
            BottomNavigationBarItem(
              icon: const Icon(Icons.dashboard_outlined),
              activeIcon: const Icon(Icons.dashboard),
              label: l10n.dashboard,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.class_outlined),
              activeIcon: const Icon(Icons.class_rounded),
              label: l10n.classes,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.fact_check_outlined),
              activeIcon: const Icon(Icons.fact_check),
              label: l10n.exams,
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.calendar_today_outlined),
              activeIcon: const Icon(Icons.calendar_today),
              label: l10n.schedule,
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
