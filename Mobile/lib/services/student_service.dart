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
    }
    return [];
  }

  /// Get attendance records for a specific student
  Future<List<dynamic>> getStudentAttendances(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/attendances');
    final data = _api.extractData(response);
    
    if (data is List) {
      return data;
    }
    return [];
  }

  /// Get schedule for a specific student
  Future<dynamic> getStudentSchedule(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/schedule');
    return _api.extractData(response);
  }
}
