# 🏫 School Manager API

A comprehensive school management REST API built with Laravel for managing students, teachers, classes, grades, attendance, and payments.

## 📋 Features

### ✅ Completed Features

- **Authentication System**
  - Login/Logout with Laravel Sanctum
  - Role-based access control (Admin, Teacher, Parent)
  - Password change functionality
  - Secure token-based authentication

- **Database Structure**
  - Users management with roles
  - Students with class assignments
  - Teachers with subject specializations
  - Classes with capacity management
  - Subjects with coefficients
  - Grades with exam types
  - Attendance tracking
  - Payment management
  - Class schedules
  - Pivot tables for many-to-many relationships

- **Eloquent Models**
  - Complete relationships (hasMany, belongsTo, belongsToMany)
  - Helper methods and scopes
  - Proper casting for dates, booleans, and decimals

- **API Endpoints**
  - RESTful architecture
  - Role-based route protection
  - Authentication endpoints
  - Dashboard with statistics
  - Mobile-friendly parent and teacher endpoints

## 🛠️ Technologies

- **Laravel 12** - Backend framework
- **Laravel Sanctum** - API authentication
- **SQLite** - Database (easily switchable to MySQL)
- **PHP 8.3** - Programming language

## 🔑 Test Credentials

```
Admin:
Email: admin@schoolmanager.com
Password: password123

Teacher:
Email: teacher@schoolmanager.com
Password: password123

Parent:
Email: parent@schoolmanager.com
Password: password123
```

## 📖 API Documentation

Full API documentation is available in [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)

## 🚀 Next Development Steps

### Priority 1: Complete Core Controllers
- [ ] Implement StudentController CRUD
- [ ] Implement TeacherController CRUD
- [ ] Implement ClassController CRUD
- [ ] Implement GradeController CRUD
- [ ] Implement AttendanceController CRUD
- [ ] Implement PaymentController CRUD

### Priority 2: Validation & Business Logic
- [ ] Create Form Request classes
- [ ] Add business logic (payment status updates, late fee calculation)
- [ ] Add data export functionality (PDF, Excel)

### Priority 3: Advanced Features
- [ ] File uploads (student photos, documents)
- [ ] Email notifications (payment reminders, grade reports)
- [ ] SMS notifications integration
- [ ] Report generation (transcripts, certificates)
- [ ] Bulk operations (import students, export data)

**Status:** ✅ Core structure completed, ready for controller implementation
