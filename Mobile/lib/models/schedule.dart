class ScheduleSlot {
  final int id;
  final String day;
  final String startTime;
  final String endTime;
  final String? room;
  final String? academicYear;
  final ScheduleAssignment? assignment;

  ScheduleSlot({
    required this.id,
    required this.day,
    required this.startTime,
    required this.endTime,
    this.room,
    this.academicYear,
    this.assignment,
  });

  factory ScheduleSlot.fromJson(Map<String, dynamic> json) {
    return ScheduleSlot(
      id: json['id'] as int,
      day: json['day'] as String? ?? '',
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      room: json['room'] as String?,
      academicYear: json['academic_year'] as String?,
      assignment: json['assignment'] != null
          ? ScheduleAssignment.fromJson(json['assignment'] as Map<String, dynamic>)
          : null,
    );
  }

  String get subjectName => assignment?.subject?.name ?? '—';
  String get teacherName {
    final t = assignment?.teacher;
    return t != null ? '${t.firstName} ${t.lastName}'.trim() : '—';
  }
  String get timeRange => '$startTime - $endTime';
}

class ScheduleAssignment {
  final int id;
  final int? teacherId;
  final int? subjectId;
  final int? classId;
  final ScheduleSubject? subject;
  final ScheduleTeacher? teacher;
  final ScheduleClass? schoolClass;

  ScheduleAssignment({
    required this.id,
    this.teacherId,
    this.subjectId,
    this.classId,
    this.subject,
    this.teacher,
    this.schoolClass,
  });

  factory ScheduleAssignment.fromJson(Map<String, dynamic> json) {
    return ScheduleAssignment(
      id: json['id'] as int,
      teacherId: json['teacher_id'] as int?,
      subjectId: json['subject_id'] as int?,
      classId: json['class_id'] as int?,
      subject: json['subject'] != null
          ? ScheduleSubject.fromJson(json['subject'] as Map<String, dynamic>)
          : null,
      teacher: json['teacher'] != null
          ? ScheduleTeacher.fromJson(json['teacher'] as Map<String, dynamic>)
          : null,
      schoolClass: json['class'] != null
          ? ScheduleClass.fromJson(json['class'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ScheduleSubject {
  final int id;
  final String name;

  ScheduleSubject({required this.id, required this.name});

  factory ScheduleSubject.fromJson(Map<String, dynamic> json) {
    return ScheduleSubject(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
    );
  }
}

class ScheduleTeacher {
  final int id;
  final String firstName;
  final String lastName;

  ScheduleTeacher({required this.id, required this.firstName, required this.lastName});

  factory ScheduleTeacher.fromJson(Map<String, dynamic> json) {
    return ScheduleTeacher(
      id: json['id'] as int,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
    );
  }

  String get fullName => '$firstName $lastName'.trim();
}

class ScheduleClass {
  final int id;
  final String name;
  final String? level;

  ScheduleClass({required this.id, required this.name, this.level});

  factory ScheduleClass.fromJson(Map<String, dynamic> json) {
    return ScheduleClass(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      level: json['level'] as String?,
    );
  }
}
