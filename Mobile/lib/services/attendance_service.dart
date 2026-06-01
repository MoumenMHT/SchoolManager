import '../models/attendance.dart';
import 'api_service.dart';

class AttendanceService {
  final ApiService _api;

  AttendanceService(this._api);

  /// Get attendance records for a student
  Future<List<AttendanceRecord>> getStudentAttendances(int studentId, {
    String? startDate,
    String? endDate,
    String? status,
    int? subjectId,
    int? month,
    String? semester,
  }) async {
    final params = <String, dynamic>{};
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    if (status != null) params['status'] = status;
    if (subjectId != null) params['subject_id'] = subjectId;
    if (month != null) params['month'] = month;
    if (semester != null) params['semester'] = semester;

    final response = await _api.get(
      '/parent/students/$studentId/attendances',
      params: params.isNotEmpty ? params : null,
    );

    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((a) => AttendanceRecord.fromJson(a as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get attendance records for a class (teacher view)
  Future<List<AttendanceRecord>> getClassAttendances(int classId, {
    String? date,
    String? startDate,
    String? endDate,
    int? subjectId,
    int? scheduleId,
  }) async {
    final params = <String, dynamic>{};

    if (date != null) {
      params['start_date'] = date;
      params['end_date'] = date;
    } else {
      if (startDate != null) params['start_date'] = startDate;
      if (endDate != null) params['end_date'] = endDate;
    }
    if (subjectId != null) params['subject_id'] = subjectId;
    if (scheduleId != null) params['schedule_id'] = scheduleId;

    final response = await _api.get(
      '/classes/$classId/attendances',
      params: params.isNotEmpty ? params : null,
    );

    final data = _api.extractData(response);
    if (data is List) {
      return data
          .map((a) => AttendanceRecord.fromJson(a as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Save attendance for a class in bulk
  Future<void> saveClassAttendance(
    List<Map<String, dynamic>> records,
  ) async {
    await _api.post('/attendances/bulk', data: { 'records': records });
  }
}
