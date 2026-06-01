import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/schedule.dart';
import '../../models/teacher_class.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/schedule_service.dart';
import '../../services/teacher_class_service.dart';
import '../../theme/app_colors.dart';
import 'teacher_class_detail_screen.dart';

class TeacherDashboardScreen extends StatefulWidget {
  final VoidCallback onOpenSchedule;
  final VoidCallback onOpenClasses;

  const TeacherDashboardScreen({
    super.key,
    required this.onOpenSchedule,
    required this.onOpenClasses,
  });

  @override
  State<TeacherDashboardScreen> createState() => _TeacherDashboardScreenState();
}

class _TeacherDashboardScreenState extends State<TeacherDashboardScreen> {
  bool _loading = true;
  bool _sessionsLoading = true;
  List<TeacherClass> _classes = [];
  List<ScheduleSlot> _todaySessions = [];
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _loading = true;
      _sessionsLoading = true;
      _error = null;
    });

    final api = context.read<ApiService>();
    final teacherId = context.read<AuthProvider>().user?.teacher?.id;

    try {
      final classService = TeacherClassService(api);
      final classes = await classService.getMyClasses();
      if (mounted) {
        setState(() {
          _classes = classes;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
        });
      }
    }

    try {
      final scheduleService = ScheduleService(api);
      final sessions = await scheduleService.getMySchedule(teacherId: teacherId);
      final today = _weekdayName(DateTime.now());
      final filtered = sessions.where((s) => s.day.toLowerCase() == today.toLowerCase()).toList();
      filtered.sort((a, b) => a.startTime.compareTo(b.startTime));
      if (mounted) {
        setState(() {
          _todaySessions = filtered;
          _sessionsLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _sessionsLoading = false);
      }
    }
  }

  String _weekdayName(DateTime date) {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[date.weekday % 7];
  }

  int _totalStudents() {
    return _classes.fold(0, (sum, c) => sum + c.studentCount);
  }

  int _totalSubjects() {
    final subjectIds = <int>{};
    for (final c in _classes) {
      for (final s in c.subjects) {
        subjectIds.add(s.id);
      }
    }
    return subjectIds.length;
  }

  void _openSession(ScheduleSlot slot) {
    final classId = slot.assignment?.classId ?? slot.assignment?.schoolClass?.id ?? slot.schoolClass?.id;
    final subjectId = slot.assignment?.subjectId ?? slot.assignment?.subject?.id ?? slot.subject?.id;
    if (classId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Class not found for this session.')),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeacherClassDetailScreen(
          classId: classId,
          initialSubjectId: subjectId,
        ),
      ),
    );
  }

  void _openClass(TeacherClass cls) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeacherClassDetailScreen(
          classId: cls.id,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final teacherName = context.watch<AuthProvider>().user?.displayName ?? '';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              expandedHeight: 180,
              pinned: true,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  decoration: const BoxDecoration(gradient: AppColors.headerGradient),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              CircleAvatar(
                                radius: 22,
                                backgroundColor: Colors.white.withValues(alpha: 0.2),
                                child: Text(
                                  teacherName.isNotEmpty ? teacherName[0].toUpperCase() : 'T',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 18,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      l10n.teacherDashboardSubtitle,
                                      style: TextStyle(
                                        color: Colors.white.withValues(alpha: 0.75),
                                        fontSize: 12,
                                      ),
                                    ),
                                    Text(
                                      l10n.teacherDashboardTitle,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 22,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    if (teacherName.isNotEmpty)
                                      Text(
                                        teacherName,
                                        style: TextStyle(
                                          color: Colors.white.withValues(alpha: 0.9),
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              TextButton.icon(
                                onPressed: widget.onOpenSchedule,
                                icon: const Icon(Icons.calendar_today, color: Colors.white, size: 18),
                                label: Text(
                                  l10n.teacherMySchedule,
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),
                          Text(
                            l10n.teacherDashboardHint,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.8),
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                child: _buildStatsRow(context, l10n),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: _buildTodaySessions(context, l10n),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(l10n.myClasses, style: Theme.of(context).textTheme.titleLarge),
                    TextButton(
                      onPressed: widget.onOpenClasses,
                      child: Text(l10n.viewAll),
                    ),
                  ],
                ),
              ),
            ),

            if (_loading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 32),
                  child: Center(child: CircularProgressIndicator(color: AppColors.primary)),
                ),
              )
            else if (_error != null)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_error!, style: const TextStyle(color: AppColors.error)),
                ),
              )
            else if (_classes.isEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: _buildEmptyState(l10n.teacherNoClassesAssigned),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final cls = _classes[index];
                      return _buildClassCard(context, cls, l10n);
                    },
                    childCount: _classes.length,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatsRow(BuildContext context, AppLocalizations l10n) {
    final totalClasses = _classes.length;
    final totalStudents = _totalStudents();
    final totalSubjects = _totalSubjects();

    return Row(
      children: [
        Expanded(
          child: _StatCard(
            label: l10n.classes,
            value: totalClasses.toString(),
            icon: Icons.class_rounded,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _StatCard(
            label: l10n.teacherStudentsLabel,
            value: totalStudents.toString(),
            icon: Icons.people,
            color: AppColors.success,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _StatCard(
            label: l10n.teacherSubjectsLabel,
            value: totalSubjects.toString(),
            icon: Icons.menu_book,
            color: AppColors.accent,
          ),
        ),
      ],
    );
  }

  Widget _buildTodaySessions(BuildContext context, AppLocalizations l10n) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l10n.teacherTodaySessions, style: Theme.of(context).textTheme.titleMedium),
              IconButton(
                icon: const Icon(Icons.refresh, size: 20),
                onPressed: _loadData,
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_sessionsLoading)
            const Center(child: CircularProgressIndicator(color: AppColors.primary))
          else if (_todaySessions.isEmpty)
            _buildEmptyState(l10n.teacherNoSessionsToday)
          else
            SizedBox(
              height: 150,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _todaySessions.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final slot = _todaySessions[index];
                  return _SessionCard(
                    title: slot.subjectName,
                    subtitle: slot.className,
                    timeRange: slot.timeRange,
                    room: slot.room,
                    onTap: () => _openSession(slot),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildClassCard(BuildContext context, TeacherClass cls, AppLocalizations l10n) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(cls.name, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                  if (cls.level != null)
                    Text('${l10n.levelLabel}: ${cls.level}', style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
              if (cls.academicYear != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceVariant,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(cls.academicYear!, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: cls.subjects
                .map((s) => Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(s.name, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                    ))
                .toList(),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _MiniStat(label: l10n.teacherStudentsLabel, value: cls.studentCount.toString()),
              const SizedBox(width: 16),
              _MiniStat(label: l10n.teacherSubjectsLabel, value: cls.subjectCount.toString()),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _openClass(cls),
              icon: const Icon(Icons.grade, size: 18),
              label: Text(l10n.grades),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(String message) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Center(
        child: Text(message, style: const TextStyle(color: AppColors.textSecondary)),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color),
          const SizedBox(height: 8),
          Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final String label;
  final String value;

  const _MiniStat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _SessionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final String timeRange;
  final String? room;
  final VoidCallback onTap;

  const _SessionCard({
    required this.title,
    required this.subtitle,
    required this.timeRange,
    required this.room,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 220,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.cardBorder),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(subtitle, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
            const Spacer(),
            Text(timeRange, style: const TextStyle(fontWeight: FontWeight.w600)),
            if (room != null && room!.isNotEmpty)
              Text('${room!}', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
