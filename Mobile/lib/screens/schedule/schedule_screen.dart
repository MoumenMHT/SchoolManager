import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/children_provider.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../models/schedule.dart';
import '../../theme/app_colors.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  late Future<List<ScheduleSlot>> _scheduleFuture;

  @override
  void initState() {
    super.initState();
    _fetchSchedule();
  }

  void _fetchSchedule() {
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null) {
      final studentService = StudentService(context.read<ApiService>());
      _scheduleFuture = studentService.getStudentSchedule(child.id).then((data) {
        if (data is List) {
          return data.map((s) => ScheduleSlot.fromJson(s)).toList();
        }
        return [];
      });
    } else {
      _scheduleFuture = Future.value([]);
    }
  }

  @override
  Widget build(BuildContext context) {
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
        title: Text('${child.firstName}\'s Schedule'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _fetchSchedule();
              });
            },
          )
        ],
      ),
      body: FutureBuilder<List<ScheduleSlot>>(
        future: _scheduleFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          if (snapshot.hasError) {
            return Center(
              child: Text(
                'Error loading schedule: \${snapshot.error}',
                style: const TextStyle(color: Colors.red),
              ),
            );
          }

          final schedule = snapshot.data ?? [];
          if (schedule.isEmpty) {
            return const Center(
              child: Text('No schedule available.'),
            );
          }

          // Group by day
          final Map<String, List<ScheduleSlot>> grouped = {};
          for (var slot in schedule) {
            if (!grouped.containsKey(slot.day)) {
              grouped[slot.day] = [];
            }
            grouped[slot.day]!.add(slot);
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: grouped.keys.length,
            itemBuilder: (context, index) {
              final day = grouped.keys.elementAt(index);
              final slots = grouped[day]!;
              slots.sort((a, b) => a.startTime.compareTo(b.startTime));

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      day.toUpperCase(),
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  ...slots.map((slot) => _buildSlotCard(slot, context)),
                ],
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildSlotCard(ScheduleSlot slot, BuildContext context) {
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
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: AppColors.primary.withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                slot.startTime.length >= 5 ? slot.startTime.substring(0, 5) : slot.startTime,
                style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primary),
              ),
              const Text('|', style: TextStyle(color: Colors.grey, fontSize: 10)),
              Text(
                slot.endTime.length >= 5 ? slot.endTime.substring(0, 5) : slot.endTime,
                style: const TextStyle(color: Colors.grey, fontSize: 12),
              ),
            ],
          ),
        ),
        title: Text(
          slot.subjectName,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.person_outline, size: 14, color: Colors.grey),
                const SizedBox(width: 4),
                Text(slot.teacherName, style: TextStyle(color: Colors.grey[600], fontSize: 12)),
              ],
            ),
            if (slot.room != null && slot.room!.isNotEmpty) ...[
              const SizedBox(height: 2),
              Row(
                children: [
                  const Icon(Icons.meeting_room_outlined, size: 14, color: Colors.grey),
                  const SizedBox(width: 4),
                  Text('Room \${slot.room}', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                ],
              ),
            ]
          ],
        ),
      ),
    );
  }
}
