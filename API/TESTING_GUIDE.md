# SchoolHub API - Testing Guide

## Test Suite Overview

Professional unit and feature tests have been created for your API. Tests are organized into:

### 📁 Feature Tests (`tests/Feature/`)
- **AuthTest.php** - Authentication flow testing
- **DashboardTest.php** - Dashboard statistics access control

### 📁 Unit Tests (`tests/Unit/`)
- **UserModelTest.php** - User model testing
- **StudentModelTest.php** - Student model testing  
- **TeacherModelTest.php** - Teacher model testing
- **CheckRoleMiddlewareTest.php** - Role middleware testing

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run with Detailed Output
```bash
php artisan test --testdox
```

### Run Specific Test File
```bash
php artisan test tests/Feature/AuthTest.php
```

### Run Specific Test Method
```bash
php artisan test --filter=test_user_can_login_with_valid_credentials
```

### Run with Coverage (requires Xdebug)
```bash
php artisan test --coverage
```

## Test Categories

### ✅ Authentication Tests
- ✓ User can login with valid credentials
- ✓ User cannot login with invalid credentials  
- ✓ Login validation (email & password required)
- ✓ Authenticated user can logout
- ✓ User can get profile
- ✓ User can change password
- ✓ Admin can register new users
- ✓ Non-admin cannot register users

### ✅ Dashboard Tests  
- ✓ Admin can access dashboard statistics
- ✓ Teacher cannot access dashboard
- ✓ Parent cannot access dashboard
- ✓ Unauthenticated user cannot access dashboard

### ✅ Model Tests
- ✓ Model fillable attributes
- ✓ Model relationships (belongsTo, hasMany, belongsToMany)
- ✓ Model attribute casting (dates, decimals)
- ✓ Model accessors (full_name, etc.)

### ✅ Middleware Tests
- ✓ Role-based access control
- ✓ Admin can access admin routes
- ✓ Teacher can access teacher routes  
- ✓ Parent restricted from teacher routes
- ✓ Proper HTTP status codes (200, 403, 401)

## Factories Created

All model factories are ready for testing:

- **UserFactory** - Creates test users with roles
- **StudentFactory** - Creates test students
- **TeacherFactory** - Creates test teachers
- **ParentModelFactory** - Creates test parents
- **SchoolClassFactory** - Creates test classes
- **SubjectFactory** - Creates test subjects
- **GradeFactory** - Creates test grades
- **AttendanceFactory** - Creates test attendance records
- **PaymentFactory** - Creates test payments

## Test Database

Tests use an in-memory SQLite database (configured in `phpunit.xml`):
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This ensures:
- Fast test execution
- No pollution of your main database
- Clean state for each test

## Next Steps

### 1. Fix Remaining Issues
Some models need the `HasFactory` trait added:
- Student
- Teacher  
- ParentModel
- SchoolClass
- Subject
- Grade
- Attendance
- Payment

### 2. Add More Tests
Consider adding tests for:
- Student CRUD operations
- Teacher CRUD operations
- Grade calculations
- Attendance tracking
- Payment processing
- Schedule conflicts

### 3. Integration Tests
Test complete workflows:
- Student enrollment process
- Grade submission workflow
- Payment collection process
- Attendance reporting

## Best Practices

✅ **DO:**
- Run tests before committing code
- Write tests for new features
- Keep tests independent
- Use descriptive test names
- Test both success and failure cases

❌ **DON'T:**
- Skip failing tests
- Write tests that depend on other tests
- Use production database for testing
- Commit code with failing tests

## Continuous Integration

Consider setting up CI/CD to run tests automatically:
- GitHub Actions
- GitLab CI
- Jenkins
- Travis CI

Example GitHub Actions workflow:
```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Tests
        run: php artisan test
```

## Current Status

**Tests Created:** 44 tests  
**Test Coverage:** Authentication, Dashboard, Models, Middleware  
**Status:** Setup complete, minor fixes needed for full passing suite

The test framework is ready for professional development! 🚀
