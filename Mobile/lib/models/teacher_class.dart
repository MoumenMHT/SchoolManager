import 'grade.dart';
import 'student.dart';

class TeacherClass {
  final int id;
  final String name;
  final String? level;
  final String? academicYear;
  final int? studentsCount;
  final List<Subject> subjects;
  final List<Student> students;

  TeacherClass({
    required this.id,
    required this.name,
    this.level,
    this.academicYear,
    this.studentsCount,
    this.subjects = const [],
    this.students = const [],
  });

  factory TeacherClass.fromJson(Map<String, dynamic> json) {
    final subjects = (json['subjects'] as List?)
            ?.map((s) => Subject.fromJson(s as Map<String, dynamic>))
            .toList() ??
        <Subject>[];

    final students = (json['students'] as List?)
            ?.map((s) => Student.fromJson(s as Map<String, dynamic>))
            .toList() ??
        <Student>[];

    return TeacherClass(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      level: json['level'] as String?,
      academicYear: json['academic_year'] as String?,
      studentsCount: json['students_count'] as int? ?? students.length,
      subjects: subjects,
      students: students,
    );
  }

  int get subjectCount => subjects.length;
  int get studentCount => studentsCount ?? students.length;
}
