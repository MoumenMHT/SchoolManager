class GradeRecord {
  final int id;
  final int studentId;
  final int examId;
  final double grade;
  final String? comment;
  final Exam? exam;
  final List<ExerciseGrade>? exerciseGrades;

  GradeRecord({
    required this.id,
    required this.studentId,
    required this.examId,
    required this.grade,
    this.comment,
    this.exam,
    this.exerciseGrades,
  });

  factory GradeRecord.fromJson(Map<String, dynamic> json) {
    return GradeRecord(
      id: json['id'] as int,
      studentId: json['student_id'] as int,
      examId: json['exam_id'] as int,
      grade: (json['grade'] as num).toDouble(),
      comment: json['comment'] as String?,
      exam: json['exam'] != null
          ? Exam.fromJson(json['exam'] as Map<String, dynamic>)
          : null,
      exerciseGrades: json['exercise_grades'] != null
          ? (json['exercise_grades'] as List)
              .map((e) => ExerciseGrade.fromJson(e as Map<String, dynamic>))
              .toList()
          : null,
    );
  }

  // Helper accessors through exam relation
  String get subjectName => exam?.subject?.name ?? '';
  String get examType => exam?.examType ?? '';
  String get semester => exam?.semester ?? '';
  String get academicYear => exam?.academicYear ?? '';
  double get maxGrade => exam?.maxGrade ?? 20;
  String get teacherName {
    final t = exam?.teacher;
    return t != null ? '${t.firstName} ${t.lastName}'.trim() : '';
  }

  double get normalizedGrade {
    if (maxGrade <= 0) return grade.clamp(0, 20);
    return (grade / maxGrade) * 20;
  }

  double get percentage => maxGrade > 0 ? (grade / maxGrade) * 100 : 0;
}

class Exam {
  final int id;
  final int subjectId;
  final int teacherId;
  final String examType;
  final String semester;
  final String academicYear;
  final double maxGrade;
  final Subject? subject;
  final Teacher? teacher;
  final List<ExamExercise>? exercises;

  Exam({
    required this.id,
    required this.subjectId,
    required this.teacherId,
    required this.examType,
    required this.semester,
    required this.academicYear,
    required this.maxGrade,
    this.subject,
    this.teacher,
    this.exercises,
  });

  factory Exam.fromJson(Map<String, dynamic> json) {
    return Exam(
      id: json['id'] as int,
      subjectId: json['subject_id'] as int,
      teacherId: json['teacher_id'] as int,
      examType: json['exam_type'] as String? ?? '',
      semester: json['semester'] as String? ?? '',
      academicYear: json['academic_year'] as String? ?? '',
      maxGrade: (json['max_grade'] as num?)?.toDouble() ?? 20,
      subject: json['subject'] != null
          ? Subject.fromJson(json['subject'] as Map<String, dynamic>)
          : null,
      teacher: json['teacher'] != null
          ? Teacher.fromJson(json['teacher'] as Map<String, dynamic>)
          : null,
      exercises: json['exercises'] != null
          ? (json['exercises'] as List)
              .map((e) => ExamExercise.fromJson(e as Map<String, dynamic>))
              .toList()
          : null,
    );
  }
}

class Subject {
  final int id;
  final String name;
  final String? code;

  Subject({required this.id, required this.name, this.code});

  factory Subject.fromJson(Map<String, dynamic> json) {
    return Subject(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      code: json['code'] as String?,
    );
  }
}

class Teacher {
  final int id;
  final String firstName;
  final String lastName;

  Teacher({required this.id, required this.firstName, required this.lastName});

  factory Teacher.fromJson(Map<String, dynamic> json) {
    return Teacher(
      id: json['id'] as int,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
    );
  }

  String get fullName => '$firstName $lastName'.trim();
}

class ExamExercise {
  final int id;
  final int examId;
  final String levelName;
  final double maxNote;

  ExamExercise({
    required this.id,
    required this.examId,
    required this.levelName,
    required this.maxNote,
  });

  factory ExamExercise.fromJson(Map<String, dynamic> json) {
    return ExamExercise(
      id: json['id'] as int,
      examId: json['exam_id'] as int,
      levelName: json['level_name'] as String? ?? '',
      maxNote: (json['max_note'] as num).toDouble(),
    );
  }
}

class ExerciseGrade {
  final int id;
  final int gradeId;
  final int examExerciseId;
  final double note;

  ExerciseGrade({
    required this.id,
    required this.gradeId,
    required this.examExerciseId,
    required this.note,
  });

  factory ExerciseGrade.fromJson(Map<String, dynamic> json) {
    return ExerciseGrade(
      id: json['id'] as int,
      gradeId: json['grade_id'] as int,
      examExerciseId: json['exam_exercise_id'] as int,
      note: (json['note'] as num).toDouble(),
    );
  }
}
