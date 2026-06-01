import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/schedule.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/schedule_service.dart';
import '../../theme/app_colors.dart';

class TeacherScheduleScreen extends StatefulWidget {
  const TeacherScheduleScreen({super.key});

  @override
  State<TeacherScheduleScreen> createState() => _TeacherScheduleScreenState();
}

class _TeacherScheduleScreenState extends State<TeacherScheduleScreen> {
  bool _loading = true;
  List<ScheduleSlot> _slots = [];
  String? _error;

  static const List<String> _orderedDays = [
    'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
  ];

  @override
  void initState() {
    super.initState();
    _loadSchedule();
  }

  Future<void> _loadSchedule() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final api = context.read<ApiService>();
    final teacherId = context.read<AuthProvider>().user?.teacher?.id;

    try {
      List<ScheduleSlot> allSlots = [];

      // Primary: use /my-schedule with teacher_id filter — most reliable
      final mySlots = await ScheduleService(api).getMySchedule(teacherId: teacherId);
      allSlots = mySlots;

      // Fallback: if primary returned nothing and we have a teacherId, try grouped endpoint
      if (allSlots.isEmpty && teacherId != null) {
        final grouped = await ScheduleService(api).getTeacherSchedule(teacherId);
        for (final slots in grouped.values) {
          allSlots.addAll(slots);
        }
      }

      if (mounted) {
        setState(() {
          _slots = allSlots;
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

  /// Group slots by capitalized day name
  Map<String, List<ScheduleSlot>> get _slotsByDay {
    final result = <String, List<ScheduleSlot>>{};
    for (final slot in _slots) {
      final day = slot.day.isNotEmpty
          ? slot.day[0].toUpperCase() + slot.day.substring(1).toLowerCase()
          : slot.day;
      result.putIfAbsent(day, () => []).add(slot);
    }
    // Sort each day's slots by start time
    for (final key in result.keys) {
      result[key]!.sort((a, b) => a.startTime.compareTo(b.startTime));
    }
    return result;
  }

  /// Only return days that actually have slots
  List<String> get _daysWithSlots {
    final grouped = _slotsByDay;
    return _orderedDays.where((d) => grouped.containsKey(d)).toList();
  }

  String _dayLabel(String day, AppLocalizations l10n) {
    switch (day.toLowerCase()) {
      case 'sunday': return l10n.sunday;
      case 'monday': return l10n.monday;
      case 'tuesday': return l10n.tuesday;
      case 'wednesday': return l10n.wednesday;
      case 'thursday': return l10n.thursday;
      case 'friday': return l10n.friday;
      case 'saturday': return l10n.saturday;
      default: return day;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(l10n.teacherMySchedule),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _loadSchedule),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? _buildError()
              : _slots.isEmpty
                  ? _buildEmpty(l10n)
                  : _buildSchedule(l10n),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, color: AppColors.error, size: 48),
            const SizedBox(height: 16),
            Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.error)),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _loadSchedule,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty(AppLocalizations l10n) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.calendar_today_outlined, size: 64, color: AppColors.textSecondary),
          const SizedBox(height: 16),
          Text(l10n.teacherNoSchedule,
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 16)),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: _loadSchedule,
            icon: const Icon(Icons.refresh),
            label: const Text('Refresh'),
          ),
        ],
      ),
    );
  }

  Widget _buildSchedule(AppLocalizations l10n) {
    final grouped = _slotsByDay;
    final days = _daysWithSlots;

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: days.length,
      itemBuilder: (context, index) {
        final day = days[index];
        final slots = grouped[day]!;

        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Day header
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(16),
                    topRight: Radius.circular(16),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today, size: 16, color: AppColors.primary),
                    const SizedBox(width: 8),
                    Text(
                      _dayLabel(day, l10n),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${slots.length}',
                        style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ),
              // Sessions
              Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  children: slots.map((slot) => _buildSlotCard(slot)).toList(),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSlotCard(ScheduleSlot slot) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          // Time badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Column(
              children: [
                Text(slot.startTime,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppColors.primary)),
                Text(slot.endTime,
                    style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          // Subject & Class
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(slot.subjectName,
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                Text(slot.className,
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
              ],
            ),
          ),
          // Room
          if (slot.room != null && slot.room!.isNotEmpty)
            Row(
              children: [
                const Icon(Icons.place, size: 14, color: AppColors.textSecondary),
                const SizedBox(width: 2),
                Text(slot.room!, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
              ],
            ),
        ],
      ),
    );
  }
}
