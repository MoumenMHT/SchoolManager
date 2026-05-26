import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../providers/children_provider.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../models/attendance.dart';
import '../../theme/app_colors.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  late Future<List<AttendanceRecord>> _attendanceFuture;

  @override
  void initState() {
    super.initState();
    _fetchAttendance();
  }

  void _fetchAttendance() {
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null) {
      final studentService = StudentService(context.read<ApiService>());
      _attendanceFuture = studentService.getStudentAttendances(child.id).then(
          (data) => data.map((a) => AttendanceRecord.fromJson(a)).toList());
    } else {
      _attendanceFuture = Future.value([]);
    }
  }

  @override
  Widget build(BuildContext context) {
    final child = context.watch<ChildrenProvider>().selectedChild;

    if (child == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Attendance')),
        body: const Center(
          child: Text('Please select a child first.'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text('${child.firstName}\'s Attendance'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _fetchAttendance();
              });
            },
          )
        ],
      ),
      body: FutureBuilder<List<AttendanceRecord>>(
        future: _attendanceFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          if (snapshot.hasError) {
            return Center(
              child: Text(
                'Error loading attendance: \${snapshot.error}',
                style: const TextStyle(color: Colors.red),
              ),
            );
          }

          final records = snapshot.data ?? [];
          if (records.isEmpty) {
            return const Center(
              child: Text('No attendance records found.'),
            );
          }

          // Calculate stats
          int total = records.length;
          int present = records.where((r) => r.isPresent).length;
          int absent = records.where((r) => r.isAbsent).length;
          int late = records.where((r) => r.isLate).length;

          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildStatsCard(total, present, absent, late, context),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16.0),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final record = records[index];
                      return _buildAttendanceCard(record, context);
                    },
                    childCount: records.length,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildStatsCard(int total, int present, int absent, int late, BuildContext context) {
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Attendance Overview',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildStatItem('Present', present, AppColors.success),
              _buildStatItem('Absent', absent, AppColors.error),
              _buildStatItem('Late', late, Colors.orange),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatItem(String label, int value, Color color) {
    return Column(
      children: [
        Text(
          value.toString(),
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: const TextStyle(color: Colors.grey, fontSize: 12),
        ),
      ],
    );
  }

  Widget _buildAttendanceCard(AttendanceRecord record, BuildContext context) {
    Color statusColor;
    IconData statusIcon;

    switch (record.status) {
      case 'present':
        statusColor = AppColors.success;
        statusIcon = Icons.check_circle;
        break;
      case 'absent':
        statusColor = AppColors.error;
        statusIcon = Icons.cancel;
        break;
      case 'late':
        statusColor = Colors.orange;
        statusIcon = Icons.access_time_filled;
        break;
      case 'excused':
        statusColor = Colors.blue;
        statusIcon = Icons.info;
        break;
      default:
        statusColor = Colors.grey;
        statusIcon = Icons.help;
    }

    // Format date
    String formattedDate = record.date;
    try {
      final date = DateTime.parse(record.date);
      formattedDate = DateFormat('EEE, MMM d, yyyy').format(date);
    } catch (_) {}

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
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: statusColor.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(statusIcon, color: statusColor),
        ),
        title: Text(
          formattedDate,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              record.subject?.name ?? 'General Attendance',
              style: TextStyle(color: Colors.grey[700]),
            ),
            if (record.reason != null && record.reason!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                'Reason: \${record.reason}',
                style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 12),
              ),
            ]
          ],
        ),
        trailing: Text(
          record.status.toUpperCase(),
          style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 12),
        ),
      ),
    );
  }
}
