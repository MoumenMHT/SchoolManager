import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/grade.dart';
import '../../models/teacher_class.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/grade_service.dart';
import '../../services/teacher_class_service.dart';
import '../../theme/app_colors.dart';

class TeacherExamsScreen extends StatefulWidget {
  const TeacherExamsScreen({super.key});

  @override
  State<TeacherExamsScreen> createState() => _TeacherExamsScreenState();
}

class _TeacherExamsScreenState extends State<TeacherExamsScreen> {
  bool _loading = true;
  bool _saving = false;
  List<TeacherClass> _classes = [];

  String? _selectedLevel;
  List<int> _selectedClassIds = [];
  int? _selectedSubjectId;
  String? _selectedExamType;
  String? _selectedSemester;
  List<_ExamExerciseInput> _exercises = [
    _ExamExerciseInput(name: 'Exercise 1', maxNote: 5),
  ];

  bool _loadingExams = false;
  List<Exam> _exams = [];
  int? _manageClassId;
  String? _manageExamType;
  String? _manageSemester;

  @override
  void initState() {
    super.initState();
    _loadClasses();
  }

  Future<void> _loadClasses() async {
    setState(() => _loading = true);
    final api = context.read<ApiService>();
    try {
      final data = await TeacherClassService(api).getMyClasses();
      if (mounted) {
        setState(() {
          _classes = data;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    }
  }

  List<String> get _levels {
    final set = <String>{};
    for (final c in _classes) {
      if (c.level != null && c.level!.isNotEmpty) {
        set.add(c.level!);
      }
    }
    return set.toList()..sort();
  }

  List<TeacherClass> get _filteredClasses {
    if (_selectedLevel == null) return [];
    return _classes.where((c) => c.level == _selectedLevel).toList();
  }

  List<Subject> get _availableSubjects {
    final map = <int, Subject>{};
    for (final c in _classes) {
      if (_selectedClassIds.contains(c.id)) {
        for (final s in c.subjects) {
          map[s.id] = s;
        }
      }
    }
    return map.values.toList();
  }

  double get _maxGrade {
    return _exercises.fold(0, (sum, e) => sum + e.maxNote);
  }

  String _academicYear() {
    final now = DateTime.now();
    final year = now.year;
    return now.month >= 9 ? '$year-${year + 1}' : '${year - 1}-$year';
  }

  Future<void> _saveExam() async {
    final l10n = AppLocalizations.of(context)!;
    final teacherId = context.read<AuthProvider>().user?.teacher?.id;

    if (teacherId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.teacherNoProfile)),
      );
      return;
    }
    if (_selectedLevel == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.teacherSelectLevel)),
      );
      return;
    }
    if (_selectedClassIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.teacherSelectClasses)),
      );
      return;
    }
    if (_selectedSubjectId == null || _selectedExamType == null || _selectedSemester == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.teacherExamDetailsRequired)),
      );
      return;
    }
    if (_exercises.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.teacherExerciseRequired)),
      );
      return;
    }

    setState(() => _saving = true);

    try {
      final payload = {
        'subject_id': _selectedSubjectId,
        'teacher_id': teacherId,
        'exam_type': _selectedExamType,
        'semester': _selectedSemester,
        'academic_year': _academicYear(),
        'max_grade': _maxGrade,
        'class_ids': _selectedClassIds,
        'exercises': _exercises
            .map((e) => {
                  'level_name': e.name,
                  'max_note': e.maxNote,
                })
            .toList(),
      };

      await GradeService(context.read<ApiService>()).createExam(payload);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.teacherExamSaved)),
        );
        setState(() {
          _selectedLevel = null;
          _selectedClassIds = [];
          _selectedSubjectId = null;
          _selectedExamType = null;
          _selectedSemester = null;
          _exercises = [
            _ExamExerciseInput(name: '${l10n.exercise} 1', maxNote: 5),
          ];
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _loadExams() async {
    final teacherId = context.read<AuthProvider>().user?.teacher?.id;
    setState(() => _loadingExams = true);

    try {
      final exams = await GradeService(context.read<ApiService>()).getExams(
        teacherId: teacherId,
        classId: _manageClassId,
        examType: _manageExamType,
        semester: _manageSemester,
      );
      if (mounted) {
        setState(() {
          _exams = exams;
          _loadingExams = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _loadingExams = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> _openEditDialog(Exam exam) async {
    final l10n = AppLocalizations.of(context)!;
    final List<_ExamExerciseInput> editExercises = (exam.exercises ?? [])
        .map((e) => _ExamExerciseInput(name: e.levelName, maxNote: e.maxNote))
        .toList();

    String? examType = exam.examType;
    String? semester = exam.semester;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.of(context).viewInsets.bottom + 16,
              ),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l10n.teacherEditExam, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String?>(
                      value: examType,
                      decoration: InputDecoration(labelText: l10n.examType),
                      items: _examTypeItems(l10n),
                      onChanged: (v) => setSheetState(() => examType = v),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String?>(
                      value: semester,
                      decoration: InputDecoration(labelText: l10n.semester),
                      items: _semesterItems(l10n),
                      onChanged: (v) => setSheetState(() => semester = v),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.teacherExercises, style: Theme.of(context).textTheme.titleSmall),
                        TextButton.icon(
                          onPressed: () => setSheetState(() => editExercises.add(_ExamExerciseInput(name: l10n.exercise, maxNote: 5))),
                          icon: const Icon(Icons.add),
                          label: Text(l10n.add),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ...editExercises.asMap().entries.map((entry) {
                      final idx = entry.key;
                      final ex = entry.value;
                      return Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceVariant,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          children: [
                            TextFormField(
                              initialValue: ex.name,
                              decoration: InputDecoration(labelText: l10n.teacherExerciseName),
                              onChanged: (v) => ex.name = v,
                            ),
                            const SizedBox(height: 8),
                            TextFormField(
                              initialValue: ex.maxNote.toString(),
                              decoration: InputDecoration(labelText: l10n.teacherMaxNote),
                              keyboardType: TextInputType.number,
                              onChanged: (v) => ex.maxNote = double.tryParse(v) ?? 0,
                            ),
                            const SizedBox(height: 6),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed: () => setSheetState(() => editExercises.removeAt(idx)),
                                icon: const Icon(Icons.delete, color: AppColors.error),
                                label: Text(l10n.delete, style: const TextStyle(color: AppColors.error)),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () async {
                          final payload = {
                            'exam_type': examType,
                            'semester': semester,
                            'exercises': editExercises
                                .map((e) => {
                                      'level_name': e.name,
                                      'max_note': e.maxNote,
                                    })
                                .toList(),
                            'max_grade': editExercises.fold(0.0, (sum, e) => sum + e.maxNote),
                          };
                          await GradeService(context.read<ApiService>()).updateExam(exam.id, payload);
                          if (mounted) {
                            Navigator.of(context).pop();
                            _loadExams();
                          }
                        },
                        child: Text(l10n.save),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _confirmDelete(Exam exam) async {
    final l10n = AppLocalizations.of(context)!;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.teacherDeleteExamTitle),
        content: Text(l10n.teacherDeleteExamMessage),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.cancel)),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.confirm)),
        ],
      ),
    );

    if (confirmed == true) {
      await GradeService(context.read<ApiService>()).deleteExam(exam.id);
      _loadExams();
    }
  }

  List<DropdownMenuItem<String?>> _examTypeItems(AppLocalizations l10n) {
    return [
      DropdownMenuItem<String?>(value: 'evaluation_continue', child: Text(l10n.examType_evaluation_continue)),
      DropdownMenuItem<String?>(value: 'devoir_1', child: Text(l10n.examType_devoir_1)),
      DropdownMenuItem<String?>(value: 'devoir_2', child: Text(l10n.examType_devoir_2)),
      DropdownMenuItem<String?>(value: 'composition', child: Text(l10n.examType_composition)),
    ];
  }

  List<DropdownMenuItem<String?>> _semesterItems(AppLocalizations l10n) {
    return [
      DropdownMenuItem<String?>(value: 'Trimester 1', child: Text(l10n.semester1)),
      DropdownMenuItem<String?>(value: 'Trimester 2', child: Text(l10n.semester2)),
      DropdownMenuItem<String?>(value: 'Trimester 3', child: Text(l10n.semester3)),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: Text(l10n.exams),
          bottom: TabBar(
            onTap: (index) {
              if (index == 1) _loadExams();
            },
            tabs: [
              Tab(text: l10n.teacherCreateExam),
              Tab(text: l10n.teacherManageExams),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _loading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : _buildCreateExam(context, l10n),
            _buildManageExams(context, l10n),
          ],
        ),
      ),
    );
  }

  Widget _buildCreateExam(BuildContext context, AppLocalizations l10n) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(l10n.teacherStepLevel, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          value: _selectedLevel,
          decoration: InputDecoration(labelText: l10n.teacherSelectLevel),
          items: _levels.map((lvl) => DropdownMenuItem(value: lvl, child: Text(lvl))).toList(),
          onChanged: (v) {
            setState(() {
              _selectedLevel = v;
              _selectedClassIds = [];
              _selectedSubjectId = null;
            });
          },
        ),
        const SizedBox(height: 16),
        if (_selectedLevel != null) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l10n.teacherStepClasses, style: Theme.of(context).textTheme.titleMedium),
              TextButton(
                onPressed: () {
                  final allIds = _filteredClasses.map((c) => c.id).toList();
                  setState(() {
                    _selectedClassIds = _selectedClassIds.length == allIds.length ? [] : allIds;
                  });
                },
                child: Text(l10n.teacherSelectAll),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _filteredClasses.map((cls) {
              final selected = _selectedClassIds.contains(cls.id);
              return FilterChip(
                label: Text(cls.name),
                selected: selected,
                onSelected: (v) {
                  setState(() {
                    if (v) {
                      _selectedClassIds.add(cls.id);
                    } else {
                      _selectedClassIds.remove(cls.id);
                    }
                  });
                },
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          Text(l10n.teacherStepConfig, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
                    DropdownButtonFormField<int?>(
            value: _selectedSubjectId,
            decoration: InputDecoration(labelText: l10n.subject),
            items: _availableSubjects
                .map((s) => DropdownMenuItem<int?>(value: s.id, child: Text(s.name)))
                .toList(),
            onChanged: (v) => setState(() => _selectedSubjectId = v),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String?>(
            value: _selectedExamType,
            decoration: InputDecoration(labelText: l10n.examType),
            items: _examTypeItems(l10n),
            onChanged: (v) => setState(() => _selectedExamType = v),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String?>(
            value: _selectedSemester,
            decoration: InputDecoration(labelText: l10n.semester),
            items: _semesterItems(l10n),
            onChanged: (v) => setState(() => _selectedSemester = v),
          ),
          const SizedBox(height: 16),
          Text(l10n.teacherExercises, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          ..._exercises.asMap().entries.map((entry) {
            final idx = entry.key;
            final ex = entry.value;
            return Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  TextFormField(
                    initialValue: ex.name,
                    decoration: InputDecoration(labelText: l10n.teacherExerciseName),
                    onChanged: (v) => ex.name = v,
                  ),
                  const SizedBox(height: 8),
                  TextFormField(
                    initialValue: ex.maxNote.toString(),
                    decoration: InputDecoration(labelText: l10n.teacherMaxNote),
                    keyboardType: TextInputType.number,
                    onChanged: (v) => ex.maxNote = double.tryParse(v) ?? 0,
                  ),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: _exercises.length <= 1
                          ? null
                          : () => setState(() => _exercises.removeAt(idx)),
                      icon: const Icon(Icons.delete, color: AppColors.error),
                      label: Text(l10n.delete, style: const TextStyle(color: AppColors.error)),
                    ),
                  ),
                ],
              ),
            );
          }),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () => setState(() => _exercises.add(_ExamExerciseInput(name: '${l10n.exercise} ${_exercises.length + 1}', maxNote: 5))),
            icon: const Icon(Icons.add),
            label: Text(l10n.add),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(l10n.teacherOverallGrade, style: const TextStyle(fontWeight: FontWeight.w600)),
                Text(_maxGrade.toStringAsFixed(1), style: const TextStyle(fontWeight: FontWeight.bold)),
              ],
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _saveExam,
              child: _saving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : Text(l10n.teacherSubmitExam),
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildManageExams(BuildContext context, AppLocalizations l10n) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(l10n.teacherManageExams, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        DropdownButtonFormField<int?>(
          value: _manageClassId,
          decoration: InputDecoration(labelText: l10n.classes),
          items: [
            DropdownMenuItem<int?>(value: null, child: Text(l10n.allClasses)),
            ..._classes.map((c) => DropdownMenuItem<int?>(value: c.id, child: Text(c.name))),
          ],
          onChanged: (v) {
            setState(() => _manageClassId = v);
            _loadExams();
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String?>(
          value: _manageExamType,
          decoration: InputDecoration(labelText: l10n.examType),
          items: [
            DropdownMenuItem<String?>(value: null, child: Text(l10n.allExamTypes)),
            ..._examTypeItems(l10n),
          ],
          onChanged: (v) {
            setState(() => _manageExamType = v);
            _loadExams();
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String?>(
          value: _manageSemester,
          decoration: InputDecoration(labelText: l10n.semester),
          items: [
            DropdownMenuItem<String?>(value: null, child: Text(l10n.allSemesters)),
            ..._semesterItems(l10n),
          ],
          onChanged: (v) {
            setState(() => _manageSemester = v);
            _loadExams();
          },
        ),
        const SizedBox(height: 16),
        if (_loadingExams)
          const Center(child: CircularProgressIndicator(color: AppColors.primary))
        else if (_exams.isEmpty)
          Center(child: Text(l10n.teacherNoExams))
        else
          ..._exams.map((exam) {
            final classNames = exam.classes?.map((c) => c.name).join(', ') ?? '';
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
                  Text(exam.subject?.name ?? l10n.subject, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Text('${_examTypeLabel(exam.examType, l10n)} • ${exam.semester}', style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 6),
                  Text('${l10n.teacherMaxNote}: ${exam.maxGrade}', style: Theme.of(context).textTheme.bodySmall),
                  if (classNames.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(classNames, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      OutlinedButton.icon(
                        onPressed: () => _openEditDialog(exam),
                        icon: const Icon(Icons.edit, size: 18),
                        label: Text(l10n.edit),
                      ),
                      const SizedBox(width: 12),
                      TextButton.icon(
                        onPressed: () => _confirmDelete(exam),
                        icon: const Icon(Icons.delete, color: AppColors.error, size: 18),
                        label: Text(l10n.delete, style: const TextStyle(color: AppColors.error)),
                      ),
                    ],
                  ),
                ],
              ),
            );
          }),
      ],
    );
  }

  String _examTypeLabel(String type, AppLocalizations l10n) {
    switch (type) {
      case 'evaluation_continue':
        return l10n.examType_evaluation_continue;
      case 'devoir_1':
        return l10n.examType_devoir_1;
      case 'devoir_2':
        return l10n.examType_devoir_2;
      case 'composition':
        return l10n.examType_composition;
      default:
        return type;
    }
  }
}

class _ExamExerciseInput {
  String name;
  double maxNote;

  _ExamExerciseInput({required this.name, required this.maxNote});
}
