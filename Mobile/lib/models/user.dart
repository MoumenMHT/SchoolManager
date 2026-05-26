class User {
  final int id;
  final String username;
  final String role;
  final String? phone;
  final String? address;
  final bool isActive;
  final ParentProfile? parent;

  User({
    required this.id,
    required this.username,
    required this.role,
    this.phone,
    this.address,
    required this.isActive,
    this.parent,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      username: json['username'] as String? ?? '',
      role: json['role'] as String? ?? 'parent',
      phone: json['phone'] as String?,
      address: json['address'] as String?,
      isActive: json['is_active'] == true || json['is_active'] == 1,
      parent: json['parent'] != null
          ? ParentProfile.fromJson(json['parent'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'role': role,
    'phone': phone,
    'address': address,
    'is_active': isActive,
    'parent': parent?.toJson(),
  };

  String get displayName {
    if (parent != null) {
      return '${parent!.firstName} ${parent!.lastName}';
    }
    return username;
  }
}

class ParentProfile {
  final int id;
  final int? userId;
  final String firstName;
  final String lastName;
  final String? phone;
  final String? email;
  final String? cin;
  final String? profession;
  final List<StudentBrief>? students;

  ParentProfile({
    required this.id,
    this.userId,
    required this.firstName,
    required this.lastName,
    this.phone,
    this.email,
    this.cin,
    this.profession,
    this.students,
  });

  factory ParentProfile.fromJson(Map<String, dynamic> json) {
    return ParentProfile(
      id: json['id'] as int,
      userId: json['user_id'] as int?,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      phone: json['phone'] as String?,
      email: json['email'] as String?,
      cin: json['cin'] as String?,
      profession: json['profession'] as String?,
      students: json['students'] != null
          ? (json['students'] as List)
              .map((s) => StudentBrief.fromJson(s as Map<String, dynamic>))
              .toList()
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'user_id': userId,
    'first_name': firstName,
    'last_name': lastName,
    'phone': phone,
    'email': email,
    'cin': cin,
    'profession': profession,
    'students': students?.map((s) => s.toJson()).toList(),
  };

  String get fullName => '$firstName $lastName';
}

class StudentBrief {
  final int id;
  final String firstName;
  final String lastName;
  final String? code;
  final int? classId;

  StudentBrief({
    required this.id,
    required this.firstName,
    required this.lastName,
    this.code,
    this.classId,
  });

  factory StudentBrief.fromJson(Map<String, dynamic> json) {
    return StudentBrief(
      id: json['id'] as int,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      code: json['code'] as String?,
      classId: json['class_id'] as int?,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'first_name': firstName,
    'last_name': lastName,
    'code': code,
    'class_id': classId,
  };
}
