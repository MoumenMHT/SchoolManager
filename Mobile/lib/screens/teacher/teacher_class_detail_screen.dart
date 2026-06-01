import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/grade.dart';
import '../../models/student.dart';
import '../../models/teacher_class.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/grade_service.dart';
import '../../services/teacher_class_service.dart';
import '../../theme/app_colors.dart';

class TeacherClassDetailScreen extends StatefulWidget {
  final int classId;
  final int? initialSubjectId;

  const TeacherClassDetailScreen({
    super.key,
    required this.classId,
    this.initialSubjectId,
  });

  @override
  State<TeacherClassDetailScreen> createState() => _TeacherClassDetailScreenState();
}

class _TeacherClassDetailScreenState extends State<TeacherClassDetailScreen> {
  TeacherClass? _classData;
  bool _classLoading = true;
  String? _classError;

  // Grades state
  int? _gradeSubjectId;
  String _gradeSemester = 'Trimester 1';
  late String _gradeAcademicYear;
  bool _gradeLoading = false;
  bool _gradeSaving = false;
  List<Exam> _availableExams = [];
  Exam? _selectedExam;
  final Map<int, GradeRecord> _existingGrades = {};
  final Map<int, Map<int, double?>> _exerciseValues = {};
  final Map<String, TextEditingController> _gradeControllers = {};

  @override
  void initState() {
    super.initState();
    _gradeAcademicYear = _computeAcademicYear();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadClass());
  }

  @override
  void dispose() {
    for (final c in _gradeControllers.values) c.dispose();
    super.dispose();
  }

  String _computeAcademicYear() {
    final now = DateTime.now();
    final year = now.year;
    return now.month >= 9 ? '$year-${year + 1}' : '${year - 1}-$year';
  }

  Future<void> _loadClass() async {
    setState(() {
      _classLoading = true;
      _classError = null;
    });

    try {
      final service = TeacherClassService(context.read<ApiService>());
      final classes = await service.getMyClasses();
      if (classes.isEmpty) {
        setState(() {
          _classData = null;
          _classLoading = false;
        });
        return;
      }
      final cls = classes.firstWhere(
        (c) => c.id == widget.classId,
        orElse: () => classes.first,
      );

      setState(() {
        _classData = cls;
        _classLoading = false;
      });

      if (_classData != null) {
        if (_classData!.subjects.isNotEmpty) {
          _gradeSubjectId = widget.initialSubjectId ?? _classData!.subjects.first.id;
        }
        _gradeAcademicYear = _classData!.academicYear ?? _gradeAcademicYear;
        await _loadExams();
      }
    } catch (e) {
      setState(() {
        _classError = e.toString();
        _classLoading = false;
      });
    }
  }

  // ── Grades ───────────────────────────────────────────────
  Future<void> _loadExams() async {
    if (_gradeSubjectId == null) return;
    setState(() => _gradeLoading = true);

    final teacherId = context.read<AuthProvider>().user?.teacher?.id;
    try {
      final exams = await GradeService(context.read<ApiService>()).getExams(
        classId: widget.classId,
        subjectId: _gradeSubjectId,
        semester: _gradeSemester,
        academicYear: _gradeAcademicYear,
        teacherId: teacherId,
      );
      setState(() {
        _availableExams = exams;
        _selectedExam = exams.isNotEmpty ? exams.first : null;
      });
      await _loadGrades();
    } catch (_) {
      setState(() {
        _availableExams = [];
        _selectedExam = null;
      });
    } finally {
      if (mounted) setState(() => _gradeLoading = false);
    }
  }

  Future<void> _loadGrades() async {
    if (_selectedExam == null || _gradeSubjectId == null || _classData == null) return;
    setState(() => _gradeLoading = true);

    try {
      final records = await GradeService(context.read<ApiService>()).getClassGrades(
        widget.classId,
        subjectId: _gradeSubjectId,
        semester: _gradeSemester,
        academicYear: _gradeAcademicYear,
      );

      _existingGrades.clear();
      for (final rec in records) {
        if (rec.examId == _selectedExam!.id) {
          _existingGrades[rec.studentId] = rec;
        }
      }

      _exerciseValues.clear();
      for (final student in _classData!.students) {
        _exerciseValues[student.id] = {};
        final record = _existingGrades[student.id];
        for (final ex in _selectedExam!.exercises ?? []) {
          double? value = 0;
          if (record != null && record.exerciseGrades != null) {
            final exGrade = record.exerciseGrades!.firstWhere(
              (g) => g.examExerciseId == ex.id,
              orElse: () => ExerciseGrade(id: 0, gradeId: 0, examExerciseId: ex.id, note: 0),
            );
            value = exGrade.note;
          }
          _exerciseValues[student.id]![ex.id] = value;

          final key = _controllerKey(student.id, ex.id);
          if (_gradeControllers.containsKey(key)) {
            _gradeControllers[key]!.text = value?.toString() ?? '';
          } else {
            _gradeControllers[key] = TextEditingController(text: value?.toString() ?? '');
          }
        }
      }
    } catch (_) {
      // ignore
    } finally {
      if (mounted) setState(() => _gradeLoading = false);
    }
  }

  void _updateExerciseValue(int studentId, int exerciseId, String raw) {
    final parsed = double.tryParse(raw.replaceAll(',', '.'));
    _exerciseValues[studentId]?[exerciseId] = parsed;
    setState(() {});
  }

  TextEditingController _getGradeController(int studentId, int exerciseId) {
    final key = _controllerKey(studentId, exerciseId);
    return _gradeControllers.putIfAbsent(key, () => TextEditingController());
  }

  void _clampExerciseValue(int studentId, int exerciseId, double maxNote) {
    final value = _exerciseValues[studentId]?[exerciseId];
    if (value == null) return;
    if (value < 0) {
      _exerciseValues[studentId]?[exerciseId] = 0;
      _gradeControllers[_controllerKey(studentId, exerciseId)]?.text = '0';
      return;
    }
    if (value > maxNote) {
      _exerciseValues[studentId]?[exerciseId] = maxNote;
      _gradeControllers[_controllerKey(studentId, exerciseId)]?.text = maxNote.toString();
      _showSnack('Max note is $maxNote.');
    }
  }

  Future<void> _saveGrades() async {
    if (_selectedExam == null || _classData == null) return;

    final grades = <Map<String, dynamic>>[];

    for (final student in _classData!.students) {
      final values = _exerciseValues[student.id] ?? {};
      final exGrades = <Map<String, dynamic>>[];
      double total = 0;
      bool hasAny = false;

      for (final ex in _selectedExam!.exercises ?? []) {
        final value = values[ex.id];
        if (value != null) {
          if (value > ex.maxNote) {
            _showSnack('${ex.levelName} cannot exceed ${ex.maxNote}.');
            return;
          }
          exGrades.add({'exam_exercise_id': ex.id, 'note': value});
          total += value;
          hasAny = true;
        }
      }

      if (!hasAny) continue;

      final payload = <String, dynamic>{
        'student_id': student.id,
        'exam_id': _selectedExam!.id,
        'grade': total,
        'exercise_grades': exGrades,
      };

      final existing = _existingGrades[student.id];
      if (existing != null) payload['id'] = existing.id;

      grades.add(payload);
    }

    if (grades.isEmpty) {
      _showSnack('No grades to save.');
      return;
    }

    setState(() => _gradeSaving = true);
    try {
      await GradeService(context.read<ApiService>()).bulkCreateGrades(grades);
      if (mounted) _showSnack('Grades saved successfully.');
      await _loadGrades();
    } catch (e) {
      if (mounted) _showSnack('Failed: ${e.toString().replaceFirst('Exception: ', '')}');
    } finally {
      if (mounted) setState(() => _gradeSaving = false);
    }
  }

  double _studentTotal(int studentId) {
    final values = _exerciseValues[studentId] ?? {};
    return values.values.whereType<double>().fold(0, (sum, v) => sum + v);
  }

  int _filledCount() {
    int count = 0;
    for (final student in _classData?.students ?? []) {
      final values = _exerciseValues[student.id] ?? {};
      if (values.values.any((v) => v != null)) count++;
    }
    return count;
  }

  double? _average() {
    int count = 0;
    double sum = 0;
    for (final student in _classData?.students ?? []) {
      final values = _exerciseValues[student.id] ?? {};
      if (values.values.any((v) => v != null)) {
        sum += _studentTotal(student.id);
        count++;
      }
    }
    if (count == 0) return null;
    return sum / count;
  }

  // ── Helpers ───────────────────────────────────────────────
  String _controllerKey(int studentId, int exerciseId) => '${studentId}_$exerciseId';

  void _showSnack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  String _examTypeLabel(String type, AppLocalizations l10n) {
    switch (type) {
      case 'evaluation_continue': return l10n.examType_evaluation_continue;
      case 'devoir_1': return l10n.examType_devoir_1;
      case 'devoir_2': return l10n.examType_devoir_2;
      case 'composition': return l10n.examType_composition;
      default: return type;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(_classData?.name ?? l10n.classDetails),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _loadClass),
        ],
      ),
      body: _classLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _classError != null
              ? Center(child: Text(_classError!, style: const TextStyle(color: AppColors.error)))
              : _classData == null
                  ? Center(child: Text(l10n.teacherClassNotFound))
                  : _buildGradesView(l10n),
    );
  }

  Widget _buildGradesView(AppLocalizations l10n) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Class header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_classData!.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    if (_classData!.academicYear != null)
                      Text(_classData!.academicYear!, style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
                Row(
                  children: [
                    const Icon(Icons.people_alt_outlined, size: 16, color: AppColors.textSecondary),
                    const SizedBox(width: 4),
                    Text('${_classData!.studentCount} ${l10n.teacherStudentsLabel}'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Filters
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int>(
                  value: _gradeSubjectId,
                  decoration: InputDecoration(labelText: l10n.subject),
                  items: _classData!.subjects
                      .map((s) => DropdownMenuItem<int>(value: s.id, child: Text(s.name)))
                      .toList(),
                  onChanged: (value) {
                    setState(() => _gradeSubjectId = value);
                    _loadExams();
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: _gradeSemester,
                  decoration: InputDecoration(labelText: l10n.semester),
                  items: [
                    DropdownMenuItem(value: 'Trimester 1', child: Text(l10n.semester1)),
                    DropdownMenuItem(value: 'Trimester 2', child: Text(l10n.semester2)),
                    DropdownMenuItem(value: 'Trimester 3', child: Text(l10n.semester3)),
                  ],
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() => _gradeSemester = value);
                    _loadExams();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<Exam>(
            value: _selectedExam,
            decoration: InputDecoration(labelText: l10n.teacherSelectExam),
            items: _availableExams
                .map((e) => DropdownMenuItem<Exam>(
                      value: e,
                      child: Text('${_examTypeLabel(e.examType, l10n)} (/${e.maxGrade})'),
                    ))
                .toList(),
            onChanged: (value) {
              setState(() => _selectedExam = value);
              _loadGrades();
            },
          ),
          const SizedBox(height: 16),

          // Grades content
          if (_gradeLoading)
            const Expanded(child: Center(child: CircularProgressIndicator(color: AppColors.primary)))
          else if (_selectedExam == null)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.fact_check_outlined, size: 48, color: AppColors.textSecondary),
                    const SizedBox(height: 12),
                    Text(l10n.teacherNoExams, style: const TextStyle(color: AppColors.textSecondary)),
                  ],
                ),
              ),
            )
          else
            Expanded(child: _buildGradesTable(l10n)),

          const SizedBox(height: 12),

          // Footer
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('${l10n.average}: ${_average()?.toStringAsFixed(1) ?? '—'}',
                  style: const TextStyle(fontWeight: FontWeight.w600)),
              Text('${l10n.teacherFilledLabel}: ${_filledCount()} / ${_classData!.students.length}',
                  style: const TextStyle(color: AppColors.textSecondary)),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _gradeSaving ? null : _saveGrades,
              icon: _gradeSaving
                  ? const SizedBox(
                      width: 18, height: 18,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(l10n.teacherSaveGrades),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGradesTable(AppLocalizations l10n) {
    final exercises = _selectedExam?.exercises ?? [];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        headingRowColor: WidgetStateProperty.all(AppColors.primary.withValues(alpha: 0.08)),
        columns: [
          DataColumn(label: Text('#')),
          DataColumn(label: Text(l10n.students)),
          ...exercises.map((ex) => DataColumn(label: Text('${ex.levelName}\n/${ex.maxNote}'))),
          DataColumn(label: Text(l10n.total)),
        ],
        rows: _classData!.students.asMap().entries.map((entry) {
          final index = entry.key;
          final student = entry.value;

          final cells = <DataCell>[
            DataCell(Text('${index + 1}')),
            DataCell(Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(student.fullName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                Text(student.code, style: Theme.of(context).textTheme.bodySmall),
              ],
            )),
          ];

          for (final ex in exercises) {
            final controller = _getGradeController(student.id, ex.id);
            cells.add(DataCell(
              SizedBox(
                width: 72,
                child: TextField(
                  controller: controller,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  onChanged: (value) => _updateExerciseValue(student.id, ex.id, value),
                  onEditingComplete: () => _clampExerciseValue(student.id, ex.id, ex.maxNote),
                  decoration: const InputDecoration(isDense: true),
                ),
              ),
            ));
          }

          cells.add(DataCell(Text(
            _studentTotal(student.id).toStringAsFixed(1),
            style: const TextStyle(fontWeight: FontWeight.bold),
          )));

          return DataRow(cells: cells);
        }).toList(),
      ),
    );
  }
}
