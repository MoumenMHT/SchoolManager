import 'grade.dart';

class AttendanceRecord {
  final int id;
  final int studentId;
  final int? subjectId;
  final int? teacherId;
  final int? scheduleId;
  final String date;
  final String? time;
  final String status; // present, absent, late, excused
  final String? reason;
  final Subject? subject;
  final Teacher? teacher;

  AttendanceRecord({
    required this.id,
    required this.studentId,
    this.subjectId,
    this.teacherId,
    this.scheduleId,
    required this.date,
    this.time,
    required this.status,
    this.reason,
    this.subject,
    this.teacher,
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      id: json['id'] as int,
      studentId: json['student_id'] as int,
      subjectId: json['subject_id'] as int?,
      teacherId: json['teacher_id'] as int?,
      scheduleId: json['schedule_id'] as int?,
      date: json['date'] as String? ?? '',
      time: json['time'] as String?,
      status: json['status'] as String? ?? 'present',
      reason: json['reason'] as String?,
      subject: json['subject'] != null
          ? Subject.fromJson(json['subject'] as Map<String, dynamic>)
          : null,
      teacher: json['teacher'] != null
          ? Teacher.fromJson(json['teacher'] as Map<String, dynamic>)
          : null,
    );
  }

  bool get isPresent => status == 'present';
  bool get isAbsent => status == 'absent';
  bool get isLate => status == 'late';
  bool get isExcused => status == 'excused';
}
