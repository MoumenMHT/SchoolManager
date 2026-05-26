import '../models/grade.dart';
import 'api_service.dart';

class GradeService {
  final ApiService _api;

  GradeService(this._api);

  /// Get grades for a specific student
  Future<List<GradeRecord>> getStudentGrades(int studentId, {
    String? semester,
    String? academicYear,
    String? examType,
  }) async {
    final params = <String, dynamic>{};
    if (semester != null) params['semester'] = semester;
    if (academicYear != null) params['academic_year'] = academicYear;
    if (examType != null) params['exam_type'] = examType;

    final response = await _api.get(
      '/parent/students/$studentId/grades',
      params: params.isNotEmpty ? params : null,
    );

    final data = _api.extractData(response);

    if (data is List) {
      return data.map((g) => GradeRecord.fromJson(g as Map<String, dynamic>)).toList();
    }
    // Handle nested { data: [...] }
    if (data is Map && data['data'] is List) {
      return (data['data'] as List)
          .map((g) => GradeRecord.fromJson(g as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get report card for a student
  Future<Map<String, dynamic>> getStudentReportCard(
    int studentId, {
    required String semester,
    required String academicYear,
  }) async {
    final response = await _api.get(
      '/parent/students/$studentId/report-card',
      params: {
        'semester': semester,
        'academic_year': academicYear,
      },
    );

    final body = response.data;
    if (body is Map<String, dynamic>) {
      return body['data'] as Map<String, dynamic>? ?? body;
    }
    return {};
  }
}
