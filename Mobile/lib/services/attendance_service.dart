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
}
