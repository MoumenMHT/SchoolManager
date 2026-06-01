import '../models/student.dart';
import 'api_service.dart';

class StudentService {
  final ApiService _api;

  StudentService(this._api);

  /// Get all children for the authenticated parent
  Future<List<Student>> getMyChildren() async {
    final response = await _api.get('/parent/students');
    final data = _api.extractData(response);

    if (data is List) {
      return data.map((s) => Student.fromJson(s as Map<String, dynamic>)).toList();
    }
    return [];
  }

  /// Get grades for a specific student
  Future<List<dynamic>> getStudentGrades(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/grades');
    final data = _api.extractData(response);
    
    if (data is List) {
      return data;
    } else if (data is Map<String, dynamic> && data['grades'] != null) {
      return data['grades'] as List;
    }
    return [];
  }

  /// Get attendance records for a specific student
  Future<List<dynamic>> getStudentAttendances(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/attendances');
    final data = _api.extractData(response);
    
    if (data is List) {
      return data;
    } else if (data is Map<String, dynamic> && data['data'] != null) {
      return data['data'] as List;
    }
    return [];
  }

  /// Get schedule for a specific student
  Future<dynamic> getStudentSchedule(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/schedule');
    final data = _api.extractData(response);

    if (data is List) {
      return data;
    }

    // API may return a grouped map: { "monday": [..], "tuesday": [..] }
    if (data is Map<String, dynamic>) {
      if (data['data'] is List) {
        return data['data'] as List;
      }
      if (data['schedule'] is List) {
        return data['schedule'] as List;
      }

      final flattened = <dynamic>[];
      for (final entry in data.entries) {
        final value = entry.value;
        if (value is List) {
          flattened.addAll(value);
        }
      }
      return flattened;
    }

    return [];
  }
}
