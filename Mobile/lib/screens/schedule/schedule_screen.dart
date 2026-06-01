import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../providers/children_provider.dart';
import '../../services/student_service.dart';
import '../../services/api_service.dart';
import '../../models/schedule.dart';
import '../../models/student.dart';
import '../../theme/app_colors.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  late Future<List<ScheduleSlot>> _scheduleFuture;
  int? _lastFetchedChildId;

  @override
  void initState() {
    super.initState();
    _scheduleFuture = Future.value([]);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null && child.id != _lastFetchedChildId) {
      _lastFetchedChildId = child.id;
      _fetchSchedule();
    }
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

  void _fetchSchedule() {
    final child = context.read<ChildrenProvider>().selectedChild;
    if (child != null) {
      final studentService = StudentService(context.read<ApiService>());
      setState(() {
        _scheduleFuture = studentService.getStudentSchedule(child.id).then((data) {
          if (data is List) {
            return data.map((s) => ScheduleSlot.fromJson(s)).toList();
          }
          return [];
        });
      });
    } else {
      setState(() {
        _scheduleFuture = Future.value([]);
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
          title: Text(l10n.schedule),
        ),
        body: Center(
          child: CustomScrollView(
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
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text('${child.firstName} - ${l10n.schedule}'),
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
            return CustomScrollView(
              slivers: [
                SliverToBoxAdapter(
                  child: _buildChildSelector(childrenProvider, context),
                ),
                const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(child: CircularProgressIndicator(color: AppColors.primary)),
                ),
              ],
            );
          }
          if (snapshot.hasError) {
            return CustomScrollView(
              slivers: [
                SliverToBoxAdapter(
                  child: _buildChildSelector(childrenProvider, context),
                ),
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: Text(
                      'Error loading schedule: ${snapshot.error}',
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                ),
              ],
            );
          }

          final schedule = snapshot.data ?? [];
          if (schedule.isEmpty) {
            return CustomScrollView(
              slivers: [
                SliverToBoxAdapter(
                  child: _buildChildSelector(childrenProvider, context),
                ),
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: Text(l10n.noSchedule),
                  ),
                ),
              ],
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

          final days = grouped.keys.toList();

          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildChildSelector(childrenProvider, context),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final day = days[index];
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
                    childCount: days.length,
                  ),
                ),
              ),
            ],
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
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
        border: Border.all(color: Colors.grey[100]!),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
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
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    slot.subjectName,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.person_outline, size: 14, color: Colors.grey),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          slot.teacherName,
                          style: TextStyle(color: Colors.grey[600], fontSize: 12),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  if (slot.room != null && slot.room!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        const Icon(Icons.meeting_room_outlined, size: 14, color: Colors.grey),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            'Room ${slot.room}',
                            style: TextStyle(color: Colors.grey[600], fontSize: 12),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ]
                ],
              ),
            )
          ],
        ),
      ),
    );
  }
}
