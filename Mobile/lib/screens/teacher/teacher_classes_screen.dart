import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/teacher_class.dart';
import '../../services/api_service.dart';
import '../../services/teacher_class_service.dart';
import '../../theme/app_colors.dart';
import 'teacher_class_detail_screen.dart';

class TeacherClassesScreen extends StatefulWidget {
  const TeacherClassesScreen({super.key});

  @override
  State<TeacherClassesScreen> createState() => _TeacherClassesScreenState();
}

class _TeacherClassesScreenState extends State<TeacherClassesScreen> {
  bool _loading = true;
  String? _error;
  List<TeacherClass> _classes = [];

  @override
  void initState() {
    super.initState();
    _loadClasses();
  }

  Future<void> _loadClasses() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final service = TeacherClassService(context.read<ApiService>());
      final data = await service.getMyClasses();
      if (mounted) {
        setState(() {
          _classes = data;
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
  }

  void _openClass(TeacherClass cls) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeacherClassDetailScreen(classId: cls.id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(l10n.myClasses),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadClasses,
          )
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.error)))
              : _classes.isEmpty
                  ? Center(child: Text(l10n.teacherNoClassesAssigned))
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _classes.length,
                      itemBuilder: (context, index) {
                        final cls = _classes[index];
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
                      },
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
