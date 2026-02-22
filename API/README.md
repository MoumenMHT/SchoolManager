<div align="center">

# 🏫 SchoolHub Management System

### Comprehensive School Management REST API

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

*A modern, scalable API for managing educational institutions with role-based access control, real-time scheduling, comprehensive student tracking, and administrative tools.*

[Features](#-features) • [Quick Start](#-quick-start) • [Documentation](#-documentation) • [API Reference](#-api-endpoints) • [Architecture](#-system-architecture)

</div>

---

## 📋 Overview

SchoolHub is a production-ready RESTful API designed for comprehensive school management. Built with Laravel 12 and following industry best practices, it provides a complete backend solution for educational institutions to manage students, teachers, classes, grades, attendance, payments, and schedules.

### Key Highlights

- 🔐 **Secure Authentication** - Token-based auth with Laravel Sanctum
- 👥 **Multi-Role System** - Admin, Teacher, and Parent roles with granular permissions
- 📊 **Real-time Analytics** - Dashboard with comprehensive statistics
- 📅 **Advanced Scheduling** - Conflict detection and smart time slot management
- 💰 **Payment Tracking** - Complete financial management with status tracking
- 📈 **Grade Management** - Report cards, rankings, and academic analytics
- ✅ **Attendance System** - Daily tracking with multiple status types
- 🚀 **Mobile-Ready** - Optimized endpoints for mobile applications

---

## ✨ Features

### Core Modules

#### 👤 User & Authentication Management
- Multi-factor authentication with email/phone login
- Role-based access control (RBAC)
- Secure password management
- Token expiration based on user role
- Account activation/deactivation

#### 👨‍🎓 Student Management
- Complete student profiles with medical information
- Class enrollment and assignment
- Parent-student relationships
- Student code generation
- Activity status tracking

#### 👨‍🏫 Teacher Management
- Comprehensive teacher profiles
- Subject specialization tracking
- Salary and employment information
- Class assignments
- Teaching schedule management

#### 🏫 Class & Subject Management
- Multi-level class organization
- Student capacity management
- Main teacher assignment
- Subject-class-teacher relationships
- Coefficient management per subject/level

#### 📝 Academic Operations
- **Grades**: Multiple exam types, weighted scoring, report cards
- **Attendance**: Daily tracking, absence reasons, statistics
- **Schedules**: Conflict detection, room management, weekly overviews
- **Reports**: Student rankings, class performance, subject statistics

#### 💳 Financial Management
- Payment tracking and history
- Due date management
- Late payment detection
- Payment type categorization
- Monthly/semester billing

#### 📊 Analytics & Reporting
- Dashboard statistics
- Financial reports
- Academic performance metrics
- Attendance analytics
- Custom date range filtering

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.3 or higher
- Composer
- SQLite (or MySQL/PostgreSQL)
- Laravel 12.x

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd SchoolHub/API

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations and seed database
php artisan migrate --seed

# Start development server
php artisan serve
```

The API will be available at `http://localhost:8000/api`

### Test Credentials

```plaintext
👑 Admin Account
Email: admin@schoolmanager.com
Password: password123

👨‍🏫 Teacher Account
Email: teacher@schoolmanager.com
Password: password123

👪 Parent Account
Email: parent@schoolmanager.com
Password: password123
```

---

## 📚 Documentation

### Available Documentation

- **[API_DOCUMENTATION.md](./API_DOCUMENTATION.md)** - Complete API endpoint reference
- **[SCHEDULE_API_REFERENCE.md](./SCHEDULE_API_REFERENCE.md)** - Detailed schedule system documentation
- **[PROJECT_SETUP.md](./PROJECT_SETUP.md)** - Development environment setup guide
- **[CHANGELOG.md](./CHANGELOG.md)** - Version history and updates

### API Base URL

```
http://localhost:8000/api
```

### Authentication

All protected endpoints require a Bearer token:

```http
Authorization: Bearer {your_token_here}
```

---

## 🔌 API Endpoints

### Authentication

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| POST | `/login` | User login | Public |
| POST | `/logout` | User logout | Authenticated |
| GET | `/me` | Get current user | Authenticated |
| POST | `/change-password` | Change password | Authenticated |
| POST | `/register` | Register new user | Admin |

### User Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/users` | List all users | Admin |
| POST | `/users` | Create user | Admin |
| GET | `/users/{id}` | Get user details | Admin |
| PUT | `/users/{id}` | Update user | Admin |
| DELETE | `/users/{id}` | Delete user | Admin |

### Student Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/students` | List students | Admin |
| POST | `/students` | Create student | Admin |
| GET | `/students/{id}` | Get student details | Admin |
| PUT | `/students/{id}` | Update student | Admin |
| DELETE | `/students/{id}` | Delete student | Admin |
| GET | `/parent/students` | Get my children | Parent |

### Teacher Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/teachers` | List teachers | Admin |
| POST | `/teachers` | Create teacher | Admin |
| GET | `/teachers/{id}` | Get teacher details | Admin |
| PUT | `/teachers/{id}` | Update teacher | Admin |
| DELETE | `/teachers/{id}` | Delete teacher | Admin |
| GET | `/teacher/classes` | Get my classes | Teacher |
| GET | `/teacher/students` | Get my students | Teacher |

### Class Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/classes` | List classes | Admin |
| POST | `/classes` | Create class | Admin |
| GET | `/classes/{id}` | Get class details | Admin |
| PUT | `/classes/{id}` | Update class | Admin |
| DELETE | `/classes/{id}` | Delete class | Admin |
| GET | `/classes/{id}/schedule` | Get class schedule | Admin |

### Grade Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/grades` | List grades | Teacher/Admin |
| POST | `/grades` | Create grade | Teacher/Admin |
| POST | `/grades/bulk` | Bulk create grades | Teacher/Admin |
| GET | `/students/{id}/grades` | Get student grades | Teacher/Admin/Parent |
| GET | `/students/{id}/report-card` | Get report card | Teacher/Admin/Parent |
| GET | `/classes/{id}/grades` | Get class grades | Teacher/Admin |
| GET | `/classes/{id}/ranking` | Get class ranking | Teacher/Admin |

### Attendance Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/attendances` | List attendances | Teacher/Admin |
| POST | `/attendances` | Mark attendance | Teacher/Admin |
| GET | `/students/{id}/attendances` | Student attendance | Teacher/Admin/Parent |
| GET | `/classes/{id}/attendances` | Class attendance | Teacher/Admin |
| GET | `/teachers/{id}/attendances` | Teacher attendance | Teacher/Admin |

### Schedule Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/schedules` | List schedules | Admin |
| POST | `/schedules` | Create schedule | Admin |
| POST | `/schedules/bulk` | Bulk create | Admin |
| PUT | `/schedules/{id}` | Update schedule | Admin |
| DELETE | `/schedules/{id}` | Delete schedule | Admin |
| POST | `/schedules/check-conflicts` | Check conflicts | Admin |
| GET | `/schedules/available-slots` | Get available slots | Admin |
| GET | `/schedules/weekly-overview` | Weekly overview | Admin |
| GET | `/classes/{id}/schedule` | Class schedule | Admin |
| GET | `/teachers/{id}/schedule` | Teacher schedule | Admin |

### Payment Management

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/payments` | List payments | Admin |
| POST | `/payments` | Create payment | Admin |
| GET | `/parent/students/{id}/payments` | Student payments | Parent/Admin |

### Dashboard & Analytics

| Method | Endpoint | Description | Access |
|--------|----------|-------------|--------|
| GET | `/dashboard/stats` | Dashboard statistics | Admin |

---

## 🏗️ System Architecture

### Database Schema

```
users
├── teachers
│   └── teacher_subjects (many-to-many with subjects)
├── parents
    └── students
        ├── grades
        ├── attendances
        └── payments

classes
└── class_subject_teacher (pivot)
    ├── teacher
    ├── subject
    └── schedules

subjects
├── subject_coefficients
└── teacher_subjects
```

### Key Relationships

- **User** → has one Teacher or Parent profile
- **Teacher** → teaches many Subjects through TeacherSubject
- **Teacher** → assigned to Classes through ClassSubjectTeacher
- **Parent** → has many Students
- **Student** → belongs to one Class
- **Student** → has many Grades, Attendances, Payments
- **Class** → has many Students
- **ClassSubjectTeacher** → has many Schedules

---

## 🔒 Security Features

- ✅ Laravel Sanctum token-based authentication
- ✅ Role-based access control middleware
- ✅ Password hashing with bcrypt
- ✅ CSRF protection
- ✅ SQL injection prevention via Eloquent ORM
- ✅ Input validation and sanitization
- ✅ Account activation control
- ✅ Token expiration management

---

## 🛠️ Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 12.x |
| Language | PHP 8.3 |
| Database | SQLite (MySQL/PostgreSQL compatible) |
| Authentication | Laravel Sanctum |
| ORM | Eloquent |
| Validation | Laravel Validator |
| API Architecture | RESTful |

---

## 📱 Mobile Integration

The API is optimized for mobile applications with dedicated endpoints:

### For Parents
```php
GET /parent/students                      // List children
GET /parent/students/{id}/grades         // Child's grades
GET /parent/students/{id}/report-card    // Report card
GET /parent/students/{id}/attendances    // Attendance history
GET /parent/students/{id}/payments       // Payment history
GET /parent/students/{id}/schedule       // Class schedule
```

### For Teachers
```php
GET /teacher/classes                     // My assigned classes
GET /teacher/students                    // All my students
POST /grades                             // Enter grades
POST /attendances                        // Mark attendance
GET /teachers/{id}/schedule              // My teaching schedule
```

---

## 🧪 Testing

```bash
# Run tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Generate coverage report
php artisan test --coverage
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📞 Support

For questions, issues, or feature requests:

- 📧 Email: support@schoolhub.com
- 🐛 Issues: [GitHub Issues](https://github.com/yourrepo/issues)
- 📖 Documentation: [Full API Documentation](./API_DOCUMENTATION.md)

---

<div align="center">

**Built with ❤️ using Laravel**

[Back to Top](#-schoolhub-management-system)

</div>
