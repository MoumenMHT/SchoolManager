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

  // ── Teacher: Exams & Grades ─────────────────────────────────────────
  Future<List<Exam>> getExams({
    int? subjectId,
    int? teacherId,
    int? classId,
    String? semester,
    String? academicYear,
    String? examType,
  }) async {
    final params = <String, dynamic>{};
    if (subjectId != null) params['subject_id'] = subjectId;
    if (teacherId != null) params['teacher_id'] = teacherId;
    if (classId != null) params['class_id'] = classId;
    if (semester != null) params['semester'] = semester;
    if (academicYear != null) params['academic_year'] = academicYear;
    if (examType != null) params['exam_type'] = examType;

    final response = await _api.get('/exams', params: params.isNotEmpty ? params : null);
    final data = _api.extractData(response);

    if (data is List) {
      return data.map((e) => Exam.fromJson(e as Map<String, dynamic>)).toList();
    }
    if (data is Map<String, dynamic> && data['data'] is List) {
      return (data['data'] as List)
          .map((e) => Exam.fromJson(e as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  Future<Exam> createExam(Map<String, dynamic> payload) async {
    final response = await _api.post('/exams', data: payload);
    final data = _api.extractData(response);
    return Exam.fromJson(data as Map<String, dynamic>);
  }

  Future<Exam> updateExam(int examId, Map<String, dynamic> payload) async {
    final response = await _api.put('/exams/$examId', data: payload);
    final data = _api.extractData(response);
    return Exam.fromJson(data as Map<String, dynamic>);
  }

  Future<void> deleteExam(int examId) async {
    await _api.delete('/exams/$examId');
  }

  Future<List<GradeRecord>> getClassGrades(int classId, {
    int? subjectId,
    String? semester,
    String? academicYear,
  }) async {
    final params = <String, dynamic>{};
    if (subjectId != null) params['subject_id'] = subjectId;
    if (semester != null) params['semester'] = semester;
    if (academicYear != null) params['academic_year'] = academicYear;

    final response = await _api.get(
      '/classes/$classId/grades',
      params: params.isNotEmpty ? params : null,
    );
    final data = _api.extractData(response);

    if (data is List) {
      return data.map((g) => GradeRecord.fromJson(g as Map<String, dynamic>)).toList();
    }
    if (data is Map<String, dynamic>) {
      if (data['grades'] is List) {
        return (data['grades'] as List)
            .map((g) => GradeRecord.fromJson(g as Map<String, dynamic>))
            .toList();
      }
      if (data['data'] is List) {
        return (data['data'] as List)
            .map((g) => GradeRecord.fromJson(g as Map<String, dynamic>))
            .toList();
      }
      if (data['data'] is Map<String, dynamic> && data['data']['data'] is List) {
        return (data['data']['data'] as List)
            .map((g) => GradeRecord.fromJson(g as Map<String, dynamic>))
            .toList();
      }
    }
    return [];
  }

  Future<void> bulkCreateGrades(List<Map<String, dynamic>> grades) async {
    await _api.post('/grades/bulk', data: {'grades': grades});
  }
}
