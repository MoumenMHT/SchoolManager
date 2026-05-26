import '../models/schedule.dart';
import 'api_service.dart';

class ScheduleService {
  final ApiService _api;

  ScheduleService(this._api);

  /// Get weekly schedule for a student
  /// Returns a map of day → list of schedule slots
  Future<Map<String, List<ScheduleSlot>>> getStudentSchedule(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/schedule');
    final data = _api.extractData(response);

    final result = <String, List<ScheduleSlot>>{};

    if (data is Map<String, dynamic>) {
      // API returns { "Sunday": [...], "Monday": [...], ... }
      // or nested under data key
      final scheduleMap = data['data'] is Map ? data['data'] as Map<String, dynamic> : data;

      scheduleMap.forEach((day, slots) {
        if (slots is List) {
          result[day] = slots
              .map((s) => ScheduleSlot.fromJson(s as Map<String, dynamic>))
              .toList();
          // Sort by start time
          result[day]!.sort((a, b) => a.startTime.compareTo(b.startTime));
        }
      });
    }

    return result;
  }
}
