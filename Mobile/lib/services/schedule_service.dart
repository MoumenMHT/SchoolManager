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

  /// Get schedule slots for the current teacher (flat list, normalized days)
  Future<List<ScheduleSlot>> getMySchedule({int? teacherId}) async {
    final params = <String, dynamic>{
      'per_page': 200,
      'with_relations': 'class,subject,teacher',
    };
    if (teacherId != null) params['teacher_id'] = teacherId;

    final response = await _api.get('/my-schedule', params: params);
    final body = response.data;

    List<dynamic> items = [];

    // Handle: { success, data: [...], pagination: {...} }
    if (body is Map<String, dynamic>) {
      final d = body['data'];
      if (d is List) {
        items = d;
      } else if (d is Map<String, dynamic> && d['data'] is List) {
        // Laravel paginator nested under data.data
        items = d['data'] as List;
      }
    } else if (body is List) {
      items = body;
    }

    return items
        .map((s) => ScheduleSlot.fromJson(_normalizeDay(s as Map<String, dynamic>)))
        .toList();
  }

  /// Get teacher weekly schedule grouped by day (capitalized day keys)
  Future<Map<String, List<ScheduleSlot>>> getTeacherSchedule(int teacherId, {String? academicYear}) async {
    final response = await _api.get(
      '/teachers/$teacherId/schedule',
      params: academicYear != null ? {'academic_year': academicYear} : null,
    );

    final body = response.data;
    final result = <String, List<ScheduleSlot>>{};

    Map<String, dynamic>? groupedMap;

    if (body is Map<String, dynamic>) {
      final d = body['data'];
      if (d is Map<String, dynamic>) {
        // API returned { success, data: { "Monday": [...] } }
        groupedMap = d;
      } else if (d is List) {
        // API returned a flat list — group manually
        final slots = d
            .map((s) => ScheduleSlot.fromJson(_normalizeDay(s as Map<String, dynamic>)))
            .toList();
        for (final slot in slots) {
          result.putIfAbsent(slot.day, () => []).add(slot);
        }
        for (final day in result.keys) {
          result[day]!.sort((a, b) => a.startTime.compareTo(b.startTime));
        }
        return result;
      } else if (body is Map<String, dynamic>) {
        // Grouped at root level (no wrapper)
        groupedMap = body;
      }
    }

    if (groupedMap != null) {
      groupedMap.forEach((day, slots) {
        if (slots is List) {
          final capitalizedDay = _capitalizeDay(day);
          result[capitalizedDay] = slots
              .map((s) => ScheduleSlot.fromJson(_normalizeDay(s as Map<String, dynamic>)))
              .toList();
          result[capitalizedDay]!.sort((a, b) => a.startTime.compareTo(b.startTime));
        }
      });
    }

    return result;
  }

  /// Capitalize day name to match the screen's weekday list
  String _capitalizeDay(String day) {
    if (day.isEmpty) return day;
    return day[0].toUpperCase() + day.substring(1).toLowerCase();
  }

  /// Ensure the slot's 'day' field is capitalized
  Map<String, dynamic> _normalizeDay(Map<String, dynamic> json) {
    final day = json['day'];
    if (day is String && day.isNotEmpty) {
      return {...json, 'day': _capitalizeDay(day)};
    }
    return json;
  }
}
