import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../providers/children_provider.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../models/grade.dart';
import '../../theme/app_colors.dart';

class GradesScreen extends StatefulWidget {
  const GradesScreen({super.key});

  @override
  State<GradesScreen> createState() => _GradesScreenState();
}

class _GradesScreenState extends State<GradesScreen> {
  late Future<List<GradeRecord>> _gradesFuture;

  @override
  void initState() {
    super.initState();
    _fetchGrades();
  }

  void _fetchGrades() {
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null) {
      final studentService = StudentService(context.read<ApiService>());
      _gradesFuture = studentService.getStudentGrades(child.id).then(
          (data) => data.map((g) => GradeRecord.fromJson(g)).toList());
    } else {
      _gradesFuture = Future.value([]);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final child = context.watch<ChildrenProvider>().selectedChild;

    if (child == null) {
      return Scaffold(
        body: Center(
          child: Text(
            'Please select a child first.',
            style: Theme.of(context).textTheme.titleMedium,
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text('${child.firstName}\'s Grades'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _fetchGrades();
              });
            },
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
                'Error loading grades: \${snapshot.error}',
                style: const TextStyle(color: Colors.red),
              ),
            );
          }

          final grades = snapshot.data ?? [];
          if (grades.isEmpty) {
            return const Center(
              child: Text('No grades recorded yet.'),
            );
          }

          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildChart(grades, context),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16.0),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final grade = grades[index];
                      return _buildGradeCard(grade, context);
                    },
                    childCount: grades.length,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildChart(List<GradeRecord> grades, BuildContext context) {
    if (grades.isEmpty) return const SizedBox();

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
            color: Colors.black.withOpacity(0.05),
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
            'Performance Overview',
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
    final isPass = grade.normalizedGrade >= 10;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
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
            color: isPass ? AppColors.success.withOpacity(0.1) : AppColors.error.withOpacity(0.1),
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
            Text('\${grade.examType} • \${grade.semester}', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
            if (grade.comment != null && grade.comment!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                '"\${grade.comment}"',
                style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 12),
              ),
            ]
          ],
        ),
        trailing: Text(
          '/\${grade.maxGrade.toInt()}',
          style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}
