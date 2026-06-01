import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../providers/children_provider.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../models/grade.dart';
import '../../models/student.dart';
import '../../theme/app_colors.dart';

class GradesScreen extends StatefulWidget {
  const GradesScreen({super.key});

  @override
  State<GradesScreen> createState() => _GradesScreenState();
}

class _GradesScreenState extends State<GradesScreen> {
  late Future<List<GradeRecord>> _gradesFuture;
  int? _lastFetchedChildId;
  int? _selectedSemesterKey;
  final Set<String> _selectedExamTypes = {};
  final Set<String> _selectedSubjects = {};

  @override
  void initState() {
    super.initState();
    _gradesFuture = Future.value([]);
  }

  Widget _buildChildSelector(ChildrenProvider childrenProvider, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final children = childrenProvider.children;
    if (children.length <= 1) return const SizedBox();

    final selectedChild = childrenProvider.selectedChild;
    final selectedValue = children.contains(selectedChild) ? selectedChild : null;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
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
          Text(
            l10n.selectChild,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<Student>(
            value: selectedValue,
            isExpanded: true,
            decoration: InputDecoration(
              filled: true,
              fillColor: Colors.grey[50],
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.grey[300]!),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.grey[300]!),
              ),
            ),
            hint: Text(l10n.selectChild),
            items: children
                .map(
                  (child) => DropdownMenuItem<Student>(
                    value: child,
                    child: Text(child.fullName),
                  ),
                )
                .toList(),
            onChanged: (child) {
              if (child == null) return;
              childrenProvider.selectChild(child);
            },
          ),
        ],
      ),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final child = context.read<ChildrenProvider>().selectedChild;
    // Re-fetch whenever the selected child changes (including the first time
    // it becomes non-null after login, which fixes the race condition).
    if (child != null && child.id != _lastFetchedChildId) {
      _lastFetchedChildId = child.id;
      _selectedSemesterKey = null;
      _selectedExamTypes.clear();
      _selectedSubjects.clear();
      _fetchGrades();
    }
  }

  void _fetchGrades() {
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null) {
      final studentService = StudentService(context.read<ApiService>());
      setState(() {
        _gradesFuture = studentService.getStudentGrades(child.id).then(
            (data) => data.map((g) => GradeRecord.fromJson(g)).toList());
      });
    } else {
      setState(() {
        _gradesFuture = Future.value([]);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final childrenProvider = context.watch<ChildrenProvider>();
    final child = childrenProvider.selectedChild;
    final hasChildren = childrenProvider.children.isNotEmpty;

    if (child == null) {
      return Scaffold(
        backgroundColor: Colors.grey[50],
        appBar: AppBar(
          title: Text(l10n.grades),
        ),
        body: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: _buildChildSelector(childrenProvider, context),
            ),
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Text(
                  hasChildren ? l10n.selectChild : l10n.noChildren,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text('${child.firstName} - ${l10n.grades}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _fetchGrades,
          )
        ],
      ),
      body: FutureBuilder<List<GradeRecord>>(
        future: _gradesFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          if (snapshot.hasError) {
            return Center(
              child: Text(
                'Error loading grades: ${snapshot.error}',
                style: const TextStyle(color: Colors.red),
              ),
            );
          }

          final grades = snapshot.data ?? [];
          if (grades.isEmpty) {
            return Center(
              child: Text(l10n.noGrades),
            );
          }

          final filteredGrades = _applyFilters(grades);

          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildChildSelector(childrenProvider, context),
              ),
              SliverToBoxAdapter(
                child: _buildFilters(grades, context),
              ),
              if (filteredGrades.isNotEmpty) ...[
                SliverToBoxAdapter(
                  child: _buildChart(filteredGrades, context),
                ),
                SliverPadding(
                  padding: const EdgeInsets.all(16.0),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final grade = filteredGrades[index];
                        return _buildGradeCard(grade, context);
                      },
                      childCount: filteredGrades.length,
                    ),
                  ),
                ),
              ] else
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: Text(l10n.noData),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  List<GradeRecord> _applyFilters(List<GradeRecord> grades) {
    return grades.where((grade) {
      final semesterKey = _semesterKeyFromValue(grade.semester);
      if (_selectedSemesterKey != null && semesterKey != _selectedSemesterKey) {
        return false;
      }
      if (_selectedExamTypes.isNotEmpty && !_selectedExamTypes.contains(grade.examType)) {
        return false;
      }
      if (_selectedSubjects.isNotEmpty && !_selectedSubjects.contains(grade.subjectName)) {
        return false;
      }
      return true;
    }).toList();
  }

  int? _semesterKeyFromValue(String semester) {
    if (semester.contains('1')) return 1;
    if (semester.contains('2')) return 2;
    if (semester.contains('3')) return 3;
    return null;
  }

  String _examTypeLabel(String type, AppLocalizations l10n) {
    switch (type) {
      case 'exam':
        return l10n.examType_exam;
      case 'quiz':
        return l10n.examType_quiz;
      case 'devoir_1':
        return l10n.examType_devoir_1;
      case 'devoir_2':
        return l10n.examType_devoir_2;
      case 'composition':
        return l10n.examType_composition;
      case 'evaluation_continue':
        return l10n.examType_evaluation_continue;
      default:
        return type;
    }
  }

  String _semesterLabel(String semester, AppLocalizations l10n) {
    final key = _semesterKeyFromValue(semester);
    if (key == 1) return l10n.semester1;
    if (key == 2) return l10n.semester2;
    if (key == 3) return l10n.semester3;
    return semester;
  }

  Widget _buildFilters(List<GradeRecord> grades, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final allExamTypes = grades
        .map((g) => g.examType)
        .where((type) => type.trim().isNotEmpty)
        .toSet()
        .toList()
      ..sort((a, b) => _examTypeLabel(a, l10n).compareTo(_examTypeLabel(b, l10n)));
    final allSubjects = grades
        .map((g) => g.subjectName)
        .where((subject) => subject.trim().isNotEmpty)
        .toSet()
        .toList()
      ..sort();

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
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
          Text(
            l10n.semester,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ChoiceChip(
                label: Text(l10n.allSemesters),
                selected: _selectedSemesterKey == null,
                onSelected: (_) {
                  setState(() {
                    _selectedSemesterKey = null;
                  });
                },
              ),
              ChoiceChip(
                label: Text(l10n.semester1),
                selected: _selectedSemesterKey == 1,
                onSelected: (selected) {
                  setState(() {
                    _selectedSemesterKey = selected ? 1 : null;
                  });
                },
              ),
              ChoiceChip(
                label: Text(l10n.semester2),
                selected: _selectedSemesterKey == 2,
                onSelected: (selected) {
                  setState(() {
                    _selectedSemesterKey = selected ? 2 : null;
                  });
                },
              ),
              ChoiceChip(
                label: Text(l10n.semester3),
                selected: _selectedSemesterKey == 3,
                onSelected: (selected) {
                  setState(() {
                    _selectedSemesterKey = selected ? 3 : null;
                  });
                },
              ),
            ],
          ),
          if (allExamTypes.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              l10n.examType,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: allExamTypes.map((type) {
                final isSelected = _selectedExamTypes.contains(type);
                return FilterChip(
                  label: Text(_examTypeLabel(type, l10n)),
                  selected: isSelected,
                  onSelected: (selected) {
                    setState(() {
                      if (selected) {
                        _selectedExamTypes.add(type);
                      } else {
                        _selectedExamTypes.remove(type);
                      }
                    });
                  },
                );
              }).toList()
                ..insert(
                  0,
                  FilterChip(
                    label: Text(l10n.allExamTypes),
                    selected: _selectedExamTypes.isEmpty,
                    onSelected: (_) {
                      setState(() {
                        _selectedExamTypes.clear();
                      });
                    },
                  ),
                ),
            ),
          ],
          if (allSubjects.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              l10n.subject,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: allSubjects.map((subject) {
                final isSelected = _selectedSubjects.contains(subject);
                return FilterChip(
                  label: Text(subject),
                  selected: isSelected,
                  onSelected: (selected) {
                    setState(() {
                      if (selected) {
                        _selectedSubjects.add(subject);
                      } else {
                        _selectedSubjects.remove(subject);
                      }
                    });
                  },
                );
              }).toList()
                ..insert(
                  0,
                  FilterChip(
                    label: Text(l10n.allSubjects),
                    selected: _selectedSubjects.isEmpty,
                    onSelected: (_) {
                      setState(() {
                        _selectedSubjects.clear();
                      });
                    },
                  ),
                ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildChart(List<GradeRecord> grades, BuildContext context) {
    if (grades.isEmpty) return const SizedBox();
    final l10n = AppLocalizations.of(context)!;

    // Take up to last 7 grades for the chart
    final displayGrades = grades.take(7).toList().reversed.toList();
    
    return Container(
      margin: const EdgeInsets.all(16),
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
      height: 250,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.performanceOverview,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 24),
          Expanded(
            child: BarChart(
              BarChartData(
                alignment: BarChartAlignment.spaceAround,
                maxY: 20, // Assumes grades are out of 20
                barTouchData: BarTouchData(enabled: false),
                titlesData: FlTitlesData(
                  show: true,
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (value, meta) {
                        if (value.toInt() >= 0 && value.toInt() < displayGrades.length) {
                          final subject = displayGrades[value.toInt()].subjectName;
                          return Padding(
                            padding: const EdgeInsets.only(top: 8.0),
                            child: Text(
                              subject.length > 3 ? subject.substring(0, 3) : subject,
                              style: const TextStyle(fontSize: 10, color: Colors.grey),
                            ),
                          );
                        }
                        return const SizedBox();
                      },
                    ),
                  ),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: false),
                  ),
                  topTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: false),
                  ),
                  rightTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: false),
                  ),
                ),
                gridData: FlGridData(
                  show: true,
                  drawVerticalLine: false,
                  horizontalInterval: 5,
                  getDrawingHorizontalLine: (value) => FlLine(
                    color: Colors.grey[200],
                    strokeWidth: 1,
                  ),
                ),
                borderData: FlBorderData(show: false),
                barGroups: displayGrades.asMap().entries.map((entry) {
                  final index = entry.key;
                  final grade = entry.value;
                  final normalizedGrade = grade.normalizedGrade;
                  
                  return BarChartGroupData(
                    x: index,
                    barRods: [
                      BarChartRodData(
                        toY: normalizedGrade,
                        color: normalizedGrade >= 10 ? AppColors.success : AppColors.error,
                        width: 16,
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(4),
                          topRight: Radius.circular(4),
                        ),
                      ),
                    ],
                  );
                }).toList(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGradeCard(GradeRecord grade, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isPass = grade.normalizedGrade >= 10;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
        border: Border.all(color: Colors.grey[100]!),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: isPass ? AppColors.success.withValues(alpha: 0.1) : AppColors.error.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Center(
            child: Text(
              grade.grade.toStringAsFixed(1),
              style: TextStyle(
                color: isPass ? AppColors.success : AppColors.error,
                fontWeight: FontWeight.bold,
                fontSize: 16,
              ),
            ),
          ),
        ),
        title: Text(
          grade.subjectName,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text('${_examTypeLabel(grade.examType, l10n)} • ${_semesterLabel(grade.semester, l10n)}', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
            if (grade.comment != null && grade.comment!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                '"${grade.comment}"',
                style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 12),
              ),
            ]
          ],
        ),
        trailing: Text(
          '/${grade.maxGrade.toInt()}',
          style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}
