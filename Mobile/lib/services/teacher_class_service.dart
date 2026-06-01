import '../models/teacher_class.dart';
import 'api_service.dart';

class TeacherClassService {
  final ApiService _api;

  TeacherClassService(this._api);

  Future<List<TeacherClass>> getMyClasses() async {
    final response = await _api.get('/teacher/classes');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((c) => TeacherClass.fromJson(c as Map<String, dynamic>))
          .toList();
    }

    if (data is Map<String, dynamic>) {
      final list = data['data'] is List ? data['data'] as List : [];
      return list
          .map((c) => TeacherClass.fromJson(c as Map<String, dynamic>))
          .toList();
    }

    return [];
  }
}
