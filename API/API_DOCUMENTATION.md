# SchoolHub API - Quick Reference

**Version:** 2.0.0 | **Base URL:** `http://localhost:8000/api` | **Last Updated:** February 18, 2026

> 💡 **New!** For complete endpoint documentation with examples, see [API_COMPLETE_REFERENCE.md](./API_COMPLETE_REFERENCE.md)

---

## 📚 Table of Contents

- [Authentication](#authentication)
- [User Management](#user-management)
- [Student Management](#student-management)
- [Teacher Management](#teacher-management)
- [Class Management](#class-management)
- [Subject Management](#subject-management)
- [Grade Management](#grade-management)
- [Attendance Management](#attendance-management)
- [Payment Management](#payment-management)
- [Schedule Management](#schedule-management)
- [Dashboard](#dashboard--analytics)
- [Request/Response Format](#request--response-format)

---

## Authentication

All protected endpoints require a Bearer token:
```http
Authorization: Bearer {your_access_token}
```

### Endpoints

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| POST | `/login` | User login | Public |
| POST | `/logout` | Revoke token | Authenticated |
| GET | `/me` | Get current user | Authenticated |
| POST | `/change-password` | Update password | Authenticated |
| POST | `/register` | Register new user | Admin |

### Login Example

**Request:**
```json
POST /login
{
  "email": "admin@schoolmanager.com",
  "password": "password123"
}
```

**Response:**
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
    "is_active": true
  }
}
```

**Token Expiration:**
- Admin: 8 hours
- Teacher: 7 days
- Parent: 7 days


---

## User Management

**Access:** Admin only

| Method | Endpoint | Description | Query Params |
|--------|----------|-------------|--------------|
| GET | `/users` | List all users | `role`, `is_active`, `search`, `page`, `per_page` |
| POST | `/users` | Create user | - |
| GET | `/users/{id}` | Get user details | - |
| PUT | `/users/{id}` | Update user | - |
| DELETE | `/users/{id}` | Delete user | - |

**Create User Request:**
```json
{
  "username": "jane_teacher",
  "email": "jane@school.com",
  "password": "password123",
  "role": "admin|teacher|parent",
  "phone": "+212600000000",
  "address": "123 Main St"
}
```

---

## Student Management

**Access:** Admin (full access), Parent (own children only)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/students` | List students | Admin |
| POST | `/students` | Create student | Admin |
| GET | `/students/{id}` | Get student details | Admin |
| PUT | `/students/{id}` | Update student | Admin |
| DELETE | `/students/{id}` | Delete student | Admin |
| GET | `/parent/students` | Get my children | Parent, Admin |

**Query Parameters:**
- `class_id` - Filter by class
- `is_active` - Filter by status
- `search` - Search by name/code
- `gender` - Filter by gender

**Create Student Request:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Hassan",
  "code": "STU2026001",
  "birth_date": "2013-05-15",
  "gender": "male|female",
  "class_id": 1,
  "parent_id": 5,
  "enrollment_date": "2025-09-01",
  "medical_info": "No allergies",
  "is_active": true
}
```

---

## Teacher Management

**Access:** Admin (full access), Teacher (own data only)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/teachers` | List teachers | Admin |
| POST | `/teachers` | Create teacher | Admin |
| GET | `/teachers/{id}` | Get teacher details | Admin |
| PUT | `/teachers/{id}` | Update teacher | Admin |
| DELETE | `/teachers/{id}` | Delete teacher | Admin |
| GET | `/teacher/classes` | Get my classes | Teacher, Admin |
| GET | `/teacher/students` | Get my students | Teacher, Admin |

**Create Teacher Request:**
```json
{
  "user_id": 10,
  "first_name": "John",
  "last_name": "Doe",
  "cin": "AB123456",
  "birth_date": "1985-05-15",
  "specialization": "Mathematics",
  "hire_date": "2020-09-01",
  "salary": 5000.00
}
```

---

## Class Management

**Access:** Admin only

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/classes` | List classes |
| POST | `/classes` | Create class |
| GET | `/classes/{id}` | Get class details |
| PUT | `/classes/{id}` | Update class |
| DELETE | `/classes/{id}` | Delete class |
| GET | `/classes/{id}/schedule` | Get class schedule |

**Create Class Request:**
```json
{
  "name": "6ème A",
  "level": "Grade 6",
  "academic_year": "2025-2026",
  "capacity": 30,
  "main_teacher_id": 1,
  "is_active": true
}
```

---

## Subject Management

**Access:** All authenticated users (list), Admin (create/update/delete)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/subjects` | List all subjects | Authenticated |
| POST | `/subjects` | Create subject | Admin |
| GET | `/subjects/{id}` | Get subject details | Authenticated |
| PUT | `/subjects/{id}` | Update subject | Admin |
| DELETE | `/subjects/{id}` | Delete subject | Admin |

**Create Subject Request:**
```json
{
  "name": "Mathematics",
  "code": "MATH",
  "description": "Mathematics curriculum"
}
```

---

## Grade Management

**Access:** Teacher/Admin (full), Parent (own children only)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/grades` | List grades | Teacher, Admin |
| POST | `/grades` | Create grade | Teacher, Admin |
| POST | `/grades/bulk` | Bulk create grades | Teacher, Admin |
| GET | `/grades/{id}` | Get grade details | Teacher, Admin |
| PUT | `/grades/{id}` | Update grade | Teacher, Admin |
| DELETE | `/grades/{id}` | Delete grade | Teacher, Admin |
| GET | `/students/{id}/grades` | Student grades | Teacher, Admin, Parent |
| GET | `/students/{id}/report-card` | Student report card | Teacher, Admin, Parent |
| GET | `/classes/{id}/grades` | Class grades | Teacher, Admin |
| GET | `/classes/{id}/ranking` | Class ranking | Teacher, Admin |
| GET | `/subjects/{id}/statistics` | Subject statistics | Teacher, Admin |

**Query Parameters:**
- `student_id` - Filter by student
- `subject_id` - Filter by subject
- `semester` - Filter by semester
- `academic_year` - Filter by academic year
- `exam_type` - Filter by exam type

**Create Grade Request:**
```json
{
  "student_id": 1,
  "subject_id": 2,
  "teacher_id": 3,
  "exam_type": "Midterm|Final|Quiz|Assignment",
  "grade": 15.5,
  "max_grade": 20,
  "semester": "Semester 1",
  "academic_year": "2025-2026",
  "comment": "Good performance"
}
```

**Bulk Create Request:**
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
    }
  ]
}
```

---

## Attendance Management

**Access:** Teacher/Admin (full), Parent (own children only)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/attendances` | List attendances | Teacher, Admin |
| POST | `/attendances` | Mark attendance | Teacher, Admin |
| GET | `/attendances/{id}` | Get attendance details | Teacher, Admin |
| PUT | `/attendances/{id}` | Update attendance | Teacher, Admin |
| DELETE | `/attendances/{id}` | Delete attendance | Teacher, Admin |
| GET | `/students/{id}/attendances` | Student attendance | Teacher, Admin, Parent |
| GET | `/classes/{id}/attendances` | Class attendance | Teacher, Admin |
| GET | `/teachers/{id}/attendances` | Teacher attendance | Teacher, Admin |
| GET | `/subjects/{id}/attendances` | Subject attendance | Teacher, Admin |

**Query Parameters:**
- `student_id` - Filter by student
- `subject_id` - Filter by subject
- `teacher_id` - Filter by teacher
- `date` - Filter by date
- `status` - Filter by status

**Create Attendance Request:**
```json
{
  "student_id": 1,
  "subject_id": 2,
  "teacher_id": 3,
  "date": "2026-02-18",
  "status": "present|absent|late|excused",
  "time": "08:00",
  "reason": "Medical appointment"
}
```

**Status Options:**
- `present` - Student was present
- `absent` - Student was absent
- `late` - Student arrived late
- `excused` - Absence was excused

---

## Payment Management

**Access:** Admin (full), Parent (own children only)

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/payments` | List payments | Admin |
| POST | `/payments` | Create payment | Admin |
| GET | `/payments/{id}` | Get payment details | Admin |
| PUT | `/payments/{id}` | Update payment | Admin |
| DELETE | `/payments/{id}` | Delete payment | Admin |
| GET | `/parent/students/{id}/payments` | Student payments | Parent, Admin |

**Query Parameters:**
- `student_id` - Filter by student
- `status` - Filter by status
- `academic_year` - Filter by academic year
- `payment_type` - Filter by payment type
- `month` - Filter by month

**Create Payment Request:**
```json
{
  "student_id": 1,
  "amount": 1500.00,
  "due_date": "2026-03-01",
  "paid_date": null,
  "status": "pending|paid|late|cancelled",
  "payment_type": "Monthly Fee|Registration|Book Fee|Activity Fee",
  "academic_year": "2025-2026",
  "month": "March",
  "notes": "Monthly tuition"
}
```

---

## Schedule Management

**Access:** Admin only

For detailed schedule API documentation, see [SCHEDULE_API_REFERENCE.md](./SCHEDULE_API_REFERENCE.md)

### Core Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/schedules` | List schedules |
| POST | `/schedules` | Create schedule |
| POST | `/schedules/bulk` | Bulk create schedules |
| GET | `/schedules/{id}` | Get schedule details |
| PUT | `/schedules/{id}` | Update schedule |
| DELETE | `/schedules/{id}` | Delete schedule |

### Specialized Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/schedules/check-conflicts` | Check for scheduling conflicts |
| GET | `/schedules/available-slots` | Get available time slots |
| GET | `/schedules/weekly-overview` | Weekly schedule overview |
| GET | `/classes/{id}/schedule` | Class weekly schedule |
| GET | `/teachers/{id}/schedule` | Teacher weekly schedule |
| GET | `/subjects/{id}/schedule` | Subject schedules |
| GET | `/schedules/day/{day}` | Day-specific schedules |
| GET | `/schedules/room/{room}` | Room schedule |

**Query Parameters:**
- `class_id` - Filter by class
- `teacher_id` - Filter by teacher
- `subject_id` - Filter by subject
- `day` - Filter by day (monday-sunday)
- `room` - Filter by room
- `academic_year` - Filter by academic year
- `date` - Filter by specific date
- `start_time_after` - Schedules starting after time
- `end_time_before` - Schedules ending before time
- `time_from` & `time_to` - Time range filter
- `sort_by` - Sort field
- `sort_order` - asc or desc
- `per_page` - Pagination (or 'all')
- `with_relations` - Include related data

**Create Schedule Request:**
```json
{
  "class_subject_teacher_id": 5,
  "day": "monday|tuesday|wednesday|thursday|friday|saturday|sunday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101"
}
```

**Conflict Detection:**
The system automatically checks for:
- ✅ Teacher time conflicts
- ✅ Room conflicts
- ✅ Class conflicts

**Bulk Create Request:**
```json
{
  "schedules": [
    {
      "class_subject_teacher_id": 5,
      "day": "monday",
      "start_time": "08:00",
      "end_time": "09:00",
      "room": "Room 101"
    }
  ]
}
```

---

## Dashboard & Analytics

**Access:** Admin only

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard/stats` | Dashboard statistics |

**Query Parameters:**
- `academic_year` - Filter by academic year

**Response Includes:**
- **Overview**: Total students, teachers, classes
- **Financial**: Revenue, pending payments, payment rate
- **Academic**: Average grades, attendance rate
- **Attendance Breakdown**: Present, absent, late, excused
- **Students by Class**: Distribution across classes
- **Recent Payments**: Latest payment records
- **Grade Distribution**: Performance breakdown

---

## Request & Response Format

### Standard Headers

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}  # For protected endpoints
```

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }  # Validation errors (if applicable)
}
```

### Paginated Response

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
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 409 | Conflict - Resource conflict |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error |

---

## Role-Based Access Control

### Roles

- **Admin**: Full system access
- **Teacher**: Grades, attendance, own classes/students
- **Parent**: Own children's information only

### Middleware Examples

```php
// Admin only
Route::middleware('role:admin')->group(function () { ... });

// Teacher and Admin
Route::middleware('role:teacher,admin')->group(function () { ... });

// Parent and Admin
Route::middleware('role:parent,admin')->group(function () { ... });
```

---

## Mobile App Integration

### Recommended Authentication Flow

1. **Login** → `POST /login` → Receive token
2. **Store Token** → Secure storage (Keychain/Keystore)
3. **Include Token** → All subsequent requests
4. **Check Role** → Display appropriate UI
5. **Refresh Data** → Periodic updates
6. **Logout** → `POST /logout` → Clear token

### Parent Endpoints

```
GET /parent/students                      # List children
GET /parent/students/{id}/grades         # Child's grades
GET /parent/students/{id}/report-card    # Report card
GET /parent/students/{id}/attendances    # Attendance history
GET /parent/students/{id}/payments       # Payment history
GET /parent/students/{id}/schedule       # Class schedule
```

### Teacher Endpoints

```
GET /teacher/classes                     # My classes
GET /teacher/students                    # All my students
POST /grades                             # Enter grades
POST /attendances                        # Mark attendance
GET /teachers/{id}/schedule              # My schedule
```

---

## Test Credentials

```plaintext
👑 Admin
Email: admin@schoolmanager.com
Password: password123

👨‍🏫 Teacher
Email: teacher@schoolmanager.com
Password: password123

👪 Parent
Email: parent@schoolmanager.com
Password: password123
```

---

## Development Server

```bash
# Start server
php artisan serve

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

API available at: `http://localhost:8000/api`

---

## Additional Resources

- 📖 **[API_COMPLETE_REFERENCE.md](./API_COMPLETE_REFERENCE.md)** - Complete endpoint documentation with examples
- 📅 **[SCHEDULE_API_REFERENCE.md](./SCHEDULE_API_REFERENCE.md)** - Detailed schedule system documentation
- 🚀 **[PROJECT_SETUP.md](./PROJECT_SETUP.md)** - Development environment setup
- 📝 **[CHANGELOG.md](./CHANGELOG.md)** - Version history

---

**Version:** 2.0.0  
**Last Updated:** February 18, 2026  
**Maintained By:** SchoolHub Development Team