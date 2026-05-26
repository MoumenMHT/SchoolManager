class Student {
  final int id;
  final String firstName;
  final String lastName;
  final String code;
  final String? birthDate;
  final String? gender;
  final int? classId;
  final int? parentId;
  final String? enrollmentDate;
  final String? medicalInfo;
  final bool isActive;
  final SchoolClass? schoolClass;

  Student({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.code,
    this.birthDate,
    this.gender,
    this.classId,
    this.parentId,
    this.enrollmentDate,
    this.medicalInfo,
    this.isActive = true,
    this.schoolClass,
  });

  factory Student.fromJson(Map<String, dynamic> json) {
    return Student(
      id: json['id'] as int,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      birthDate: json['birth_date'] as String?,
      gender: json['gender'] as String?,
      classId: json['class_id'] as int?,
      parentId: json['parent_id'] as int?,
      enrollmentDate: json['enrollment_date'] as String?,
      medicalInfo: json['medical_info'] as String?,
      isActive: json['is_active'] == true || json['is_active'] == 1,
      schoolClass: json['class'] != null
          ? SchoolClass.fromJson(json['class'] as Map<String, dynamic>)
          : null,
    );
  }

  String get fullName => '$firstName $lastName';

  String get className => schoolClass?.name ?? '—';

  String get initials {
    final f = firstName.isNotEmpty ? firstName[0] : '';
    final l = lastName.isNotEmpty ? lastName[0] : '';
    return '$f$l'.toUpperCase();
  }
}

class SchoolClass {
  final int id;
  final String name;
  final String? level;
  final String? academicYear;
  final int? capacity;
  final int? studentsCount;

  SchoolClass({
    required this.id,
    required this.name,
    this.level,
    this.academicYear,
    this.capacity,
    this.studentsCount,
  });

  factory SchoolClass.fromJson(Map<String, dynamic> json) {
    return SchoolClass(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      level: json['level'] as String?,
      academicYear: json['academic_year'] as String?,
      capacity: json['capacity'] as int?,
      studentsCount: json['students_count'] as int?,
    );
  }
}
