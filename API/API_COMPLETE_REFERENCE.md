# SchoolHub API - Complete Reference Guide

**Version:** 2.0.0  
**Last Updated:** February 18, 2026  
**Base URL:** `http://localhost:8000/api`

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication](#authentication)
3. [User Management](#user-management)
4. [Student Management](#student-management)
5. [Teacher Management](#teacher-management)
6. [Parent Management](#parent-management)
7. [Class Management](#class-management)
8. [Subject Management](#subject-management)
9. [Grade Management](#grade-management)
10. [Attendance Management](#attendance-management)
11. [Payment Management](#payment-management)
12. [Schedule Management](#schedule-management)
13. [Dashboard & Analytics](#dashboard--analytics)
14. [Error Handling](#error-handling)
15. [Best Practices](#best-practices)

---

## Getting Started

### Base URL

```
http://localhost:8000/api
```

### Request Headers

All requests must include:

```http
Content-Type: application/json
Accept: application/json
```

Authenticated requests require:

```http
Authorization: Bearer {your_access_token}
```

### Response Format

All API responses follow a consistent structure:

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }  // Validation errors (if applicable)
}
```

**Paginated Response:**
```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | OK - Request successful |
| `201` | Created - Resource created successfully |
| `400` | Bad Request - Invalid parameters |
| `401` | Unauthorized - Authentication required |
| `403` | Forbidden - Insufficient permissions |
| `404` | Not Found - Resource not found |
| `409` | Conflict - Resource conflict (e.g., scheduling) |
| `422` | Unprocessable Entity - Validation failed |
| `500` | Internal Server Error - Server error |

---

## Authentication

### Login

Authenticate a user and receive an access token.

**Endpoint:** `POST /login`  
**Access:** Public

#### Request Body

```json
{
  "email": "admin@schoolmanager.com",
  "password": "password123"
}
```

**Alternative (Phone Login):**
```json
{
  "phone": "+212600000000",
  "password": "password123"
}
```

#### Validation Rules

- `email`: Required if phone not provided, must be valid email
- `phone`: Required if email not provided
- `password`: Required

#### Response (200 OK)

```json
{
  "success": true,
  "token": "1|abcdef123456...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@schoolmanager.com",
    "role": "admin",
    "phone": "+212600000000",
    "address": "123 Main Street",
    "is_active": true,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-02-18T10:00:00.000000Z"
  }
}
```

#### Token Expiration

- **Admin**: 8 hours
- **Teacher**: 7 days
- **Parent**: 7 days

#### Error Responses

**Invalid Credentials (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**Inactive Account (403):**
```json
{
  "success": false,
  "message": "Account is inactive"
}
```

---

### Logout

Revoke the current access token.

**Endpoint:** `POST /logout`  
**Access:** Authenticated

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Get Current User

Retrieve the authenticated user's information.

**Endpoint:** `GET /me`  
**Access:** Authenticated

#### Response (200 OK)

```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "john_teacher",
    "email": "john@school.com",
    "role": "teacher",
    "is_active": true,
    "teacher": {
      "id": 5,
      "first_name": "John",
      "last_name": "Doe",
      "cin": "AB123456",
      "specialization": "Mathematics",
      "hire_date": "2020-09-01",
      "salary": 5000.00
    }
  }
}
```

---

### Change Password

Update the authenticated user's password.

**Endpoint:** `POST /change-password`  
**Access:** Authenticated

#### Request Body

```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

#### Validation Rules

- `current_password`: Required
- `new_password`: Required, minimum 8 characters
- `new_password_confirmation`: Required, must match new_password

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

---

### Register User

Create a new user account (Admin only).

**Endpoint:** `POST /register`  
**Access:** Admin

#### Request Body

```json
{
  "username": "jane_teacher",
  "email": "jane@school.com",
  "password": "password123",
  "role": "teacher",
  "phone": "+212600000001",
  "address": "456 Oak Avenue"
}
```

#### Validation Rules

- `username`: Required, max 255 characters
- `email`: Required, valid email, unique
- `password`: Required, minimum 8 characters
- `role`: Required, one of: admin, teacher, parent
- `phone`: Optional
- `address`: Optional

#### Response (201 Created)

```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 15,
    "username": "jane_teacher",
    "email": "jane@school.com",
    "role": "teacher",
    "is_active": true
  }
}
```

---

## User Management

### List Users

Retrieve a paginated list of all users.

**Endpoint:** `GET /users`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `role` | string | Filter by role | `?role=teacher` |
| `is_active` | boolean | Filter by status | `?is_active=1` |
| `search` | string | Search by username/email | `?search=john` |
| `page` | integer | Page number | `?page=2` |
| `per_page` | integer | Items per page | `?per_page=20` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@school.com",
      "role": "admin",
      "is_active": true,
      "created_at": "2026-01-01T00:00:00.000000Z"
    }
  ],
  "pagination": {
    "total": 45,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

### Get User

Retrieve a specific user by ID.

**Endpoint:** `GET /users/{id}`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 5,
    "username": "john_teacher",
    "email": "john@school.com",
    "role": "teacher",
    "phone": "+212600000000",
    "address": "123 Main St",
    "is_active": true,
    "teacher": {
      "id": 3,
      "first_name": "John",
      "last_name": "Doe",
      "specialization": "Mathematics"
    }
  }
}
```

---

### Update User

Update a user's information.

**Endpoint:** `PUT /users/{id}`  
**Access:** Admin

#### Request Body

```json
{
  "username": "john_updated",
  "email": "john.new@school.com",
  "phone": "+212600000002",
  "is_active": true
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "message": "User updated successfully",
  "data": { ... }
}
```

---

### Delete User

Delete a user account.

**Endpoint:** `DELETE /users/{id}`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

---

## Student Management

### List Students

Retrieve a paginated list of students.

**Endpoint:** `GET /students`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `class_id` | integer | Filter by class | `?class_id=5` |
| `is_active` | boolean | Filter by status | `?is_active=1` |
| `search` | string | Search by name/code | `?search=ahmed` |
| `gender` | string | Filter by gender | `?gender=male` |
| `page` | integer | Page number | `?page=1` |
| `per_page` | integer | Items per page | `?per_page=20` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "code": "STU2026001",
      "birth_date": "2013-05-15",
      "gender": "male",
      "class_id": 1,
      "parent_id": 5,
      "enrollment_date": "2025-09-01",
      "medical_info": "No allergies",
      "is_active": true,
      "class": {
        "id": 1,
        "name": "6ème A",
        "level": "Grade 6"
      },
      "parent": {
        "id": 5,
        "first_name": "Mohamed",
        "last_name": "Hassan"
      }
    }
  ],
  "pagination": { ... }
}
```

---

### Create Student

Create a new student record.

**Endpoint:** `POST /students`  
**Access:** Admin

#### Request Body

```json
{
  "first_name": "Ahmed",
  "last_name": "Hassan",
  "code": "STU2026001",
  "birth_date": "2013-05-15",
  "gender": "male",
  "class_id": 1,
  "parent_id": 5,
  "enrollment_date": "2025-09-01",
  "medical_info": "No allergies",
  "is_active": true
}
```

#### Validation Rules

- `first_name`: Required, max 255 characters
- `last_name`: Required, max 255 characters
- `code`: Required, unique, max 50 characters
- `birth_date`: Required, valid date
- `gender`: Required, one of: male, female
- `class_id`: Required, must exist
- `parent_id`: Optional, must exist if provided
- `enrollment_date`: Required, valid date
- `medical_info`: Optional
- `is_active`: Optional, boolean (default: true)

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Student created successfully",
  "data": { ... }
}
```

---

### Get Student

Retrieve a specific student by ID.

**Endpoint:** `GET /students/{id}`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Hassan",
    "code": "STU2026001",
    "full_name": "Ahmed Hassan",
    "age": 12,
    "class": { ... },
    "parent": { ... },
    "grades": [ ... ],
    "attendances": [ ... ]
  }
}
```

---

### Update Student

Update a student's information.

**Endpoint:** `PUT /students/{id}`  
**Access:** Admin

#### Request Body

```json
{
  "class_id": 2,
  "medical_info": "Updated medical information",
  "is_active": true
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Student updated successfully",
  "data": { ... }
}
```

---

### Delete Student

Delete a student record.

**Endpoint:** `DELETE /students/{id}`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "message": "Student deleted successfully"
}
```

---

### Get My Children (Parent)

Retrieve the authenticated parent's children.

**Endpoint:** `GET /parent/students`  
**Access:** Parent, Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "class": {
        "name": "6ème A"
      }
    }
  ]
}
```

---

## Teacher Management

### List Teachers

Retrieve a paginated list of teachers.

**Endpoint:** `GET /teachers`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `specialization` | string | Filter by specialization | `?specialization=Mathematics` |
| `search` | string | Search by name | `?search=john` |
| `page` | integer | Page number | `?page=1` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "first_name": "John",
      "last_name": "Doe",
      "cin": "AB123456",
      "birth_date": "1985-05-15",
      "specialization": "Mathematics",
      "hire_date": "2020-09-01",
      "salary": 5000.00,
      "user": {
        "email": "john@school.com",
        "phone": "+212600000000"
      }
    }
  ],
  "pagination": { ... }
}
```

---

### Create Teacher

Create a new teacher record.

**Endpoint:** `POST /teachers`  
**Access:** Admin

#### Request Body

```json
{
  "user_id": 10,
  "first_name": "Jane",
  "last_name": "Smith",
  "cin": "CD789012",
  "birth_date": "1990-03-20",
  "specialization": "Physics",
  "hire_date": "2024-09-01",
  "salary": 5500.00
}
```

#### Validation Rules

- `user_id`: Required, must exist, must be teacher role
- `first_name`: Required, max 255 characters
- `last_name`: Required, max 255 characters
- `cin`: Required, unique, max 20 characters
- `birth_date`: Required, valid date
- `specialization`: Required, max 255 characters
- `hire_date`: Required, valid date
- `salary`: Required, numeric, positive

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Teacher created successfully",
  "data": { ... }
}
```

---

### Get My Classes (Teacher)

Retrieve the authenticated teacher's assigned classes.

**Endpoint:** `GET /teacher/classes`  
**Access:** Teacher, Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "6ème A",
      "level": "Grade 6",
      "academic_year": "2025-2026",
      "subject": {
        "name": "Mathematics"
      }
    }
  ]
}
```

---

### Get My Students (Teacher)

Retrieve all students taught by the authenticated teacher.

**Endpoint:** `GET /teacher/students`  
**Access:** Teacher, Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "class": {
        "name": "6ème A"
      }
    }
  ]
}
```

---

## Parent Management

### List Parents

Retrieve a paginated list of parents.

**Endpoint:** `GET /parents`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 15,
      "first_name": "Mohamed",
      "last_name": "Hassan",
      "phone": "+212600000000",
      "email": "mohamed@email.com",
      "cin": "EF345678",
      "profession": "Engineer",
      "students": [
        {
          "id": 1,
          "first_name": "Ahmed",
          "last_name": "Hassan"
        }
      ]
    }
  ],
  "pagination": { ... }
}
```

---

### Create Parent

Create a new parent record.

**Endpoint:** `POST /parents`  
**Access:** Admin

#### Request Body

```json
{
  "user_id": 20,
  "first_name": "Fatima",
  "last_name": "Alami",
  "phone": "+212600000001",
  "email": "fatima@email.com",
  "cin": "GH901234",
  "profession": "Doctor"
}
```

#### Validation Rules

- `user_id`: Optional, must exist if provided, must be parent role
- `first_name`: Required, max 255 characters
- `last_name`: Required, max 255 characters
- `phone`: Optional, max 20 characters
- `email`: Optional, valid email
- `cin`: Required, unique, max 20 characters
- `profession`: Optional, max 255 characters

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Parent created successfully",
  "data": { ... }
}
```

---

## Class Management

### List Classes

Retrieve a paginated list of classes.

**Endpoint:** `GET /classes`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `level` | string | Filter by level | `?level=Grade 6` |
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |
| `is_active` | boolean | Filter by status | `?is_active=1` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "6ème A",
      "level": "Grade 6",
      "academic_year": "2025-2026",
      "capacity": 30,
      "current_student_count": 25,
      "is_active": true,
      "main_teacher": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      }
    }
  ],
  "pagination": { ... }
}
```

---

### Create Class

Create a new class.

**Endpoint:** `POST /classes`  
**Access:** Admin

#### Request Body

```json
{
  "name": "6ème B",
  "level": "Grade 6",
  "academic_year": "2025-2026",
  "capacity": 30,
  "main_teacher_id": 1,
  "is_active": true
}
```

#### Validation Rules

- `name`: Required, max 255 characters
- `level`: Required, max 255 characters
- `academic_year`: Required, max 255 characters
- `capacity`: Required, integer, positive
- `main_teacher_id`: Optional, must exist
- `is_active`: Optional, boolean (default: true)

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Class created successfully",
  "data": { ... }
}
```

---

### Get Class Schedule

Retrieve a class's weekly schedule.

**Endpoint:** `GET /classes/{id}/schedule`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "monday": [
      {
        "id": 1,
        "start_time": "08:00",
        "end_time": "09:00",
        "room": "Room 101",
        "subject": {
          "name": "Mathematics"
        },
        "teacher": {
          "first_name": "John",
          "last_name": "Doe"
        }
      }
    ],
    "tuesday": [ ... ],
    "wednesday": [ ... ],
    "thursday": [ ... ],
    "friday": [ ... ]
  },
  "total": 25
}
```

---

## Subject Management

### List Subjects

Retrieve a list of all subjects.

**Endpoint:** `GET /subjects`  
**Access:** Authenticated

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Mathematics",
      "code": "MATH",
      "description": "Mathematics curriculum for all levels"
    }
  ]
}
```

---

### Create Subject

Create a new subject.

**Endpoint:** `POST /subjects`  
**Access:** Admin

#### Request Body

```json
{
  "name": "Physics",
  "code": "PHYS",
  "description": "Physics curriculum"
}
```

#### Validation Rules

- `name`: Required, max 255 characters
- `code`: Required, unique, max 50 characters
- `description`: Optional

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Subject created successfully",
  "data": { ... }
}
```

---

## Grade Management

### List Grades

Retrieve a list of grades with filtering options.

**Endpoint:** `GET /grades`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `student_id` | integer | Filter by student | `?student_id=1` |
| `subject_id` | integer | Filter by subject | `?subject_id=2` |
| `semester` | string | Filter by semester | `?semester=Semester 1` |
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |
| `exam_type` | string | Filter by exam type | `?exam_type=Midterm` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "subject_id": 2,
      "teacher_id": 3,
      "exam_type": "Midterm",
      "grade": 15.5,
      "max_grade": 20,
      "percentage": 77.5,
      "semester": "Semester 1",
      "academic_year": "2025-2026",
      "comment": "Good performance",
      "student": {
        "first_name": "Ahmed",
        "last_name": "Hassan"
      },
      "subject": {
        "name": "Mathematics"
      },
      "teacher": {
        "first_name": "John",
        "last_name": "Doe"
      }
    }
  ]
}
```

---

### Create Grade

Create a new grade entry.

**Endpoint:** `POST /grades`  
**Access:** Teacher, Admin

#### Request Body

```json
{
  "student_id": 1,
  "subject_id": 2,
  "teacher_id": 3,
  "exam_type": "Final Exam",
  "grade": 18.5,
  "max_grade": 20,
  "semester": "Semester 1",
  "academic_year": "2025-2026",
  "comment": "Excellent work"
}
```

#### Validation Rules

- `student_id`: Required, must exist
- `subject_id`: Required, must exist
- `teacher_id`: Required, must exist
- `exam_type`: Required, max 255 characters
- `grade`: Required, numeric, min 0
- `max_grade`: Required, numeric, min 0, >= grade
- `semester`: Required, max 255 characters
- `academic_year`: Required, max 255 characters
- `comment`: Optional

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Grade created successfully",
  "data": { ... }
}
```

---

### Bulk Create Grades

Create multiple grades at once.

**Endpoint:** `POST /grades/bulk`  
**Access:** Teacher, Admin

#### Request Body

```json
{
  "grades": [
    {
      "student_id": 1,
      "subject_id": 2,
      "teacher_id": 3,
      "exam_type": "Quiz",
      "grade": 16,
      "max_grade": 20,
      "semester": "Semester 1",
      "academic_year": "2025-2026"
    },
    {
      "student_id": 2,
      "subject_id": 2,
      "teacher_id": 3,
      "exam_type": "Quiz",
      "grade": 14,
      "max_grade": 20,
      "semester": "Semester 1",
      "academic_year": "2025-2026"
    }
  ]
}
```

#### Response (201 Created)

```json
{
  "success": true,
  "message": "15 grades created successfully",
  "data": [ ... ],
  "summary": {
    "total": 15,
    "created": 14,
    "failed": 1
  },
  "errors": [
    {
      "index": 5,
      "errors": { ... }
    }
  ]
}
```

---

### Get Student Grades

Retrieve all grades for a specific student.

**Endpoint:** `GET /students/{id}/grades`  
**Access:** Teacher, Admin, Parent (own children only)

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `subject_id` | integer | Filter by subject | `?subject_id=2` |
| `semester` | string | Filter by semester | `?semester=Semester 1` |
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |

#### Response (200 OK)

```json
{
  "success": true,
  "student": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Hassan"
  },
  "grades": [
    {
      "subject": "Mathematics",
      "exam_type": "Midterm",
      "grade": 15.5,
      "max_grade": 20,
      "percentage": 77.5
    }
  ],
  "statistics": {
    "average": 14.8,
    "total_grades": 25
  }
}
```

---

### Get Student Report Card

Generate a comprehensive report card for a student.

**Endpoint:** `GET /students/{id}/report-card`  
**Access:** Teacher, Admin, Parent (own children only)

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `semester` | string | Required | `?semester=Semester 1` |
| `academic_year` | string | Required | `?academic_year=2025-2026` |

#### Response (200 OK)

```json
{
  "success": true,
  "student": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Hassan",
    "class": "6ème A"
  },
  "report_card": {
    "Mathematics": {
      "grades": [
        {
          "exam_type": "Midterm",
          "grade": 15.5,
          "max_grade": 20
        },
        {
          "exam_type": "Final",
          "grade": 17,
          "max_grade": 20
        }
      ],
      "average": 16.25,
      "coefficient": 3,
      "weighted_average": 48.75
    },
    "Physics": { ... }
  },
  "overall": {
    "average": 15.2,
    "rank": 5,
    "total_students": 30
  }
}
```

---

### Get Class Grades

Retrieve all grades for a specific class.

**Endpoint:** `GET /classes/{id}/grades`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `subject_id` | integer | Filter by subject |
| `semester` | string | Filter by semester |
| `academic_year` | string | Filter by year |

#### Response (200 OK)

```json
{
  "success": true,
  "class": {
    "id": 1,
    "name": "6ème A"
  },
  "grades": [ ... ],
  "statistics": {
    "average": 14.5,
    "highest": 19,
    "lowest": 8,
    "total_grades": 150
  }
}
```

---

### Get Class Ranking

Retrieve student rankings for a class.

**Endpoint:** `GET /classes/{id}/ranking`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `semester` | string | Required |
| `academic_year` | string | Required |

#### Response (200 OK)

```json
{
  "success": true,
  "class": {
    "id": 1,
    "name": "6ème A"
  },
  "ranking": [
    {
      "rank": 1,
      "student": {
        "id": 5,
        "first_name": "Sara",
        "last_name": "Alami"
      },
      "average": 18.5
    },
    {
      "rank": 2,
      "student": {
        "id": 3,
        "first_name": "Ahmed",
        "last_name": "Hassan"
      },
      "average": 17.2
    }
  ]
}
```

---

### Get Subject Statistics

Get statistical analysis for a subject across all classes.

**Endpoint:** `GET /subjects/{id}/statistics`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `academic_year` | string | Filter by year |
| `semester` | string | Filter by semester |

#### Response (200 OK)

```json
{
  "success": true,
  "subject": {
    "id": 2,
    "name": "Mathematics"
  },
  "statistics": {
    "total_students": 120,
    "average": 14.5,
    "highest": 20,
    "lowest": 5,
    "pass_rate": 87.5,
    "grade_distribution": {
      "excellent": 25,
      "good": 45,
      "average": 35,
      "poor": 15
    }
  }
}
```

---

## Attendance Management

### List Attendances

Retrieve a list of attendance records.

**Endpoint:** `GET /attendances`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `student_id` | integer | Filter by student | `?student_id=1` |
| `subject_id` | integer | Filter by subject | `?subject_id=2` |
| `teacher_id` | integer | Filter by teacher | `?teacher_id=3` |
| `date` | date | Filter by date | `?date=2026-02-18` |
| `status` | string | Filter by status | `?status=absent` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "subject_id": 2,
      "teacher_id": 3,
      "date": "2026-02-18",
      "time": "08:00",
      "status": "present",
      "reason": null,
      "student": {
        "first_name": "Ahmed",
        "last_name": "Hassan"
      },
      "subject": {
        "name": "Mathematics"
      }
    }
  ]
}
```

---

### Create Attendance

Mark attendance for a student.

**Endpoint:** `POST /attendances`  
**Access:** Teacher, Admin

#### Request Body

```json
{
  "student_id": 1,
  "subject_id": 2,
  "teacher_id": 3,
  "date": "2026-02-18",
  "status": "present",
  "time": "08:00",
  "reason": null
}
```

#### Validation Rules

- `student_id`: Required, must exist
- `subject_id`: Required, must exist
- `teacher_id`: Required, must exist
- `date`: Required, valid date
- `status`: Required, one of: present, absent, late, excused
- `time`: Required, valid time (H:i format)
- `reason`: Optional, required if status is absent or excused

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Attendance recorded successfully",
  "data": { ... }
}
```

---

### Get Student Attendances

Retrieve attendance records for a specific student.

**Endpoint:** `GET /students/{id}/attendances`  
**Access:** Teacher, Admin, Parent (own children only)

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `date_from` | date | Start date | `?date_from=2026-01-01` |
| `date_to` | date | End date | `?date_to=2026-02-18` |
| `subject_id` | integer | Filter by subject | `?subject_id=2` |

#### Response (200 OK)

```json
{
  "success": true,
  "student": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Hassan"
  },
  "attendances": [ ... ],
  "statistics": {
    "total": 50,
    "present": 45,
    "absent": 3,
    "late": 2,
    "excused": 0,
    "attendance_rate": 90.0
  }
}
```

---

### Get Class Attendances

Retrieve attendance records for a specific class.

**Endpoint:** `GET /classes/{id}/attendances`  
**Access:** Teacher, Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `date` | date | Specific date |
| `date_from` | date | Start date range |
| `date_to` | date | End date range |

#### Response (200 OK)

```json
{
  "success": true,
  "class": {
    "id": 1,
    "name": "6ème A"
  },
  "attendances": [ ... ],
  "statistics": {
    "total_records": 500,
    "present": 450,
    "absent": 30,
    "late": 15,
    "excused": 5,
    "attendance_rate": 90.0
  }
}
```

---

## Payment Management

### List Payments

Retrieve a list of payment records.

**Endpoint:** `GET /payments`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `student_id` | integer | Filter by student | `?student_id=1` |
| `status` | string | Filter by status | `?status=pending` |
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |
| `payment_type` | string | Filter by type | `?payment_type=Monthly Fee` |
| `month` | string | Filter by month | `?month=January` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "amount": 1500.00,
      "due_date": "2026-03-01",
      "paid_date": null,
      "status": "pending",
      "payment_type": "Monthly Fee",
      "academic_year": "2025-2026",
      "month": "March",
      "notes": "Monthly tuition",
      "student": {
        "first_name": "Ahmed",
        "last_name": "Hassan"
      }
    }
  ],
  "pagination": { ... }
}
```

---

### Create Payment

Create a new payment record.

**Endpoint:** `POST /payments`  
**Access:** Admin

#### Request Body

```json
{
  "student_id": 1,
  "amount": 1500.00,
  "due_date": "2026-03-01",
  "paid_date": null,
  "status": "pending",
  "payment_type": "Monthly Fee",
  "academic_year": "2025-2026",
  "month": "March",
  "notes": "Monthly tuition"
}
```

#### Validation Rules

- `student_id`: Required, must exist
- `amount`: Required, numeric, positive
- `due_date`: Required, valid date
- `paid_date`: Optional, valid date
- `status`: Required, one of: pending, paid, late, cancelled
- `payment_type`: Required, max 255 characters
- `academic_year`: Required, max 255 characters
- `month`: Optional, max 255 characters
- `notes`: Optional

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Payment created successfully",
  "data": { ... }
}
```

---

### Get Student Payments (Parent)

Retrieve payment history for a student.

**Endpoint:** `GET /parent/students/{id}/payments`  
**Access:** Parent (own children only), Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `academic_year` | string | Filter by year |
| `status` | string | Filter by status |

#### Response (200 OK)

```json
{
  "success": true,
  "student": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Hassan"
  },
  "payments": [ ... ],
  "summary": {
    "total_due": 15000.00,
    "total_paid": 12000.00,
    "total_pending": 3000.00,
    "late_payments": 1
  }
}
```

---

## Schedule Management

### List Schedules

Retrieve a list of schedules with advanced filtering.

**Endpoint:** `GET /schedules`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `class_id` | integer | Filter by class | `?class_id=1` |
| `teacher_id` | integer | Filter by teacher | `?teacher_id=3` |
| `subject_id` | integer | Filter by subject | `?subject_id=2` |
| `day` | string | Filter by day | `?day=monday` |
| `room` | string | Filter by room | `?room=101` |
| `academic_year` | string | Filter by year | `?academic_year=2025-2026` |
| `date` | date | Convert to day | `?date=2026-02-18` |
| `start_time_after` | time | After time | `?start_time_after=08:00` |
| `end_time_before` | time | Before time | `?end_time_before=16:00` |
| `time_from` | time | Time range start | `?time_from=08:00` |
| `time_to` | time | Time range end | `?time_to=12:00` |
| `sort_by` | string | Sort field | `?sort_by=start_time` |
| `sort_order` | string | asc or desc | `?sort_order=asc` |
| `per_page` | integer/all | Pagination | `?per_page=20` |
| `with_relations` | string | Relations | `?with_relations=class,subject,teacher` |

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "class_subject_teacher_id": 5,
      "day": "monday",
      "start_time": "08:00",
      "end_time": "09:00",
      "room": "Room 101",
      "assignment": {
        "class": {
          "name": "6ème A"
        },
        "subject": {
          "name": "Mathematics"
        },
        "teacher": {
          "first_name": "John",
          "last_name": "Doe"
        }
      }
    }
  ],
  "pagination": {
    "total": 45,
    "per_page": 20,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

### Create Schedule

Create a new schedule entry.

**Endpoint:** `POST /schedules`  
**Access:** Admin

#### Request Body

```json
{
  "class_subject_teacher_id": 5,
  "day": "monday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101"
}
```

#### Validation Rules

- `class_subject_teacher_id`: Required, must exist
- `day`: Required, one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday
- `start_time`: Required, valid time (H:i format)
- `end_time`: Required, valid time (H:i format), must be after start_time
- `room`: Optional, max 50 characters

#### Conflict Detection

The system automatically checks for:
- **Teacher conflicts**: Same teacher, same day/time
- **Room conflicts**: Same room, same day/time
- **Class conflicts**: Same class, same day/time

#### Response (201 Created)

```json
{
  "success": true,
  "message": "Schedule created successfully",
  "data": { ... }
}
```

#### Response (409 Conflict)

```json
{
  "success": false,
  "message": "Teacher already has a class scheduled at this time",
  "conflict": "teacher_time_conflict"
}
```

---

### Bulk Create Schedules

Create multiple schedules at once.

**Endpoint:** `POST /schedules/bulk`  
**Access:** Admin

#### Request Body

```json
{
  "schedules": [
    {
      "class_subject_teacher_id": 5,
      "day": "monday",
      "start_time": "08:00",
      "end_time": "09:00",
      "room": "Room 101"
    },
    {
      "class_subject_teacher_id": 6,
      "day": "monday",
      "start_time": "09:00",
      "end_time": "10:00",
      "room": "Room 102"
    }
  ]
}
```

#### Response (201 Created)

```json
{
  "success": true,
  "message": "5 schedules created successfully",
  "data": [ ... ],
  "errors": [
    {
      "index": 3,
      "data": { ... },
      "conflicts": ["teacher_conflict"]
    }
  ],
  "summary": {
    "total_submitted": 10,
    "created": 5,
    "failed": 5
  }
}
```

---

### Check Schedule Conflicts

Check for conflicts before creating/updating a schedule.

**Endpoint:** `POST /schedules/check-conflicts`  
**Access:** Admin

#### Request Body

```json
{
  "class_subject_teacher_id": 5,
  "day": "monday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101",
  "exclude_schedule_id": 10
}
```

#### Response (200 OK)

```json
{
  "success": true,
  "has_conflicts": true,
  "conflicts": [
    {
      "type": "teacher",
      "message": "Teacher has another class at this time",
      "schedule": {
        "id": 5,
        "day": "monday",
        "start_time": "08:00",
        "end_time": "09:00"
      }
    }
  ]
}
```

---

### Get Available Time Slots

Get occupied time slots for scheduling assistance.

**Endpoint:** `GET /schedules/available-slots`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `teacher_id` | integer | Filter by teacher | - |
| `class_id` | integer | Filter by class | - |
| `room` | string | Filter by room | - |
| `day` | string | Required | - |
| `start_hour` | integer | Start hour | 8 |
| `end_hour` | integer | End hour | 18 |

#### Response (200 OK)

```json
{
  "success": true,
  "day": "monday",
  "time_range": {
    "start": "08:00",
    "end": "16:00"
  },
  "occupied_slots": [
    {
      "start_time": "08:00",
      "end_time": "09:00",
      "room": "Room 101",
      "subject": "Mathematics"
    }
  ],
  "total_occupied": 8
}
```

---

### Get Class Schedule

Retrieve a class's weekly schedule.

**Endpoint:** `GET /classes/{id}/schedule`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `academic_year` | string | Filter by year |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "monday": [ ... ],
    "tuesday": [ ... ],
    "wednesday": [ ... ],
    "thursday": [ ... ],
    "friday": [ ... ]
  },
  "total": 25
}
```

---

### Get Teacher Schedule

Retrieve a teacher's weekly schedule.

**Endpoint:** `GET /teachers/{id}/schedule`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "monday": [
      {
        "id": 1,
        "start_time": "08:00",
        "end_time": "09:00",
        "room": "Room 101",
        "class": {
          "name": "6ème A"
        },
        "subject": {
          "name": "Mathematics"
        }
      }
    ],
    "tuesday": [ ... ]
  },
  "total": 18
}
```

---

### Get Subject Schedule

Retrieve all schedules for a specific subject.

**Endpoint:** `GET /subjects/{id}/schedule`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "day": "monday",
      "start_time": "08:00",
      "end_time": "09:00",
      "class": {
        "name": "6ème A"
      },
      "teacher": {
        "first_name": "John",
        "last_name": "Doe"
      }
    }
  ],
  "total": 12
}
```

---

### Get Day Schedule

Retrieve all schedules for a specific day.

**Endpoint:** `GET /schedules/day/{day}`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `class_id` | integer | Optional filter |
| `teacher_id` | integer | Optional filter |

#### Response (200 OK)

```json
{
  "success": true,
  "day": "Monday",
  "data": [ ... ],
  "total": 35
}
```

---

### Get Room Schedule

Retrieve a room's weekly schedule.

**Endpoint:** `GET /schedules/room/{room}`  
**Access:** Admin

#### Response (200 OK)

```json
{
  "success": true,
  "room": "Room 101",
  "data": {
    "monday": [ ... ],
    "tuesday": [ ... ]
  },
  "total": 30
}
```

---

### Get Weekly Overview

Get a complete weekly schedule overview with statistics.

**Endpoint:** `GET /schedules/weekly-overview`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `class_id` | integer | Optional filter |
| `teacher_id` | integer | Optional filter |
| `academic_year` | string | Optional filter |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "monday": [ ... ],
    "tuesday": [ ... ],
    "wednesday": [ ... ],
    "thursday": [ ... ],
    "friday": [ ... ]
  },
  "statistics": {
    "total_sessions": 45,
    "days_with_classes": 5,
    "sessions_per_day": {
      "monday": 10,
      "tuesday": 9,
      "wednesday": 8,
      "thursday": 10,
      "friday": 8
    }
  }
}
```

---

## Dashboard & Analytics

### Get Dashboard Statistics

Retrieve comprehensive dashboard statistics.

**Endpoint:** `GET /dashboard/stats`  
**Access:** Admin

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `academic_year` | string | Filter by year |

#### Response (200 OK)

```json
{
  "success": true,
  "data": {
    "overview": {
      "total_students": 150,
      "total_teachers": 25,
      "total_classes": 12,
      "active_students": 145,
      "active_teachers": 24
    },
    "financial": {
      "total_revenue": 450000.00,
      "pending_payments": 75000.00,
      "late_payments": 15000.00,
      "paid_amount": 360000.00,
      "payment_rate": 82.76
    },
    "academic": {
      "average_grade": 14.5,
      "attendance_rate": 92.3,
      "total_grades": 2500,
      "total_attendance_records": 5000
    },
    "attendance_breakdown": {
      "present": 4615,
      "absent": 250,
      "late": 100,
      "excused": 35
    },
    "students_by_class": [
      {
        "class_name": "6ème A",
        "student_count": 28
      }
    ],
    "recent_payments": [ ... ],
    "grade_distribution": {
      "excellent": 450,
      "good": 1200,
      "average": 750,
      "poor": 100
    }
  }
}
```

---

## Error Handling

### Standard Error Responses

#### Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

#### Not Found (404)

```json
{
  "success": false,
  "message": "Resource not found"
}
```

#### Unauthorized (401)

```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

#### Forbidden (403)

```json
{
  "success": false,
  "message": "You do not have permission to perform this action"
}
```

#### Server Error (500)

```json
{
  "success": false,
  "message": "An error occurred while processing your request",
  "error": "Detailed error message"
}
```

---

## Best Practices

### Authentication Flow

1. **Login**: Call `POST /login` with credentials
2. **Store Token**: Save the received token securely
3. **Include Token**: Add token to all subsequent requests
4. **Handle Expiration**: Implement token refresh or re-login logic
5. **Logout**: Call `POST /logout` to revoke token

### Pagination

- Use `per_page` parameter to control page size
- Default page size is 15 items
- Use `per_page=all` to get all results (use cautiously)
- Always check pagination metadata in responses

### Filtering

- Combine multiple filters for precise results
- Use exact matches for IDs
- Use partial matches for text search
- Date filters support ISO 8601 format (YYYY-MM-DD)

### Performance Optimization

- Use `with_relations` parameter selectively
- Request only needed fields when possible
- Implement client-side caching
- Use pagination for large datasets
- Combine related requests when possible

### Error Handling

- Always check `success` field in responses
- Handle all HTTP status codes appropriately
- Display user-friendly error messages
- Log errors for debugging
- Implement retry logic for transient errors

### Security

- Never expose tokens in URLs
- Store tokens securely (e.g., httpOnly cookies, secure storage)
- Implement HTTPS in production
- Validate all user inputs client-side
- Handle sensitive data appropriately
- Implement rate limiting on client side

### Mobile Integration

- Implement offline mode with local caching
- Sync data when connectivity is restored
- Handle token expiration gracefully
- Optimize payload sizes
- Use compression when possible

---

## Support & Resources

### Additional Documentation

- **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)** - Quick reference guide
- **[SCHEDULE_API_REFERENCE.md](./SCHEDULE_API_REFERENCE.md)** - Schedule system details
- **[PROJECT_SETUP.md](./PROJECT_SETUP.md)** - Development setup

### Getting Help

- 📧 **Email**: support@schoolhub.com
- 🐛 **Issues**: GitHub Issues
- 📖 **Wiki**: Project Wiki
- 💬 **Discord**: Community Server

### Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history and updates.

---

**Document Version:** 2.0.0  
**Last Updated:** February 18, 2026  
**Maintained By:** SchoolHub Development Team
