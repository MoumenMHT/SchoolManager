# Schedule Controller API Reference

## Overview
The Schedule Controller manages class schedules with comprehensive filtering, conflict detection, and reporting capabilities following REST API best practices.

## Features Implemented ✅

### 1. **CRUD Operations**
- ✅ List all schedules with advanced filtering
- ✅ Create single schedule
- ✅ Get schedule by ID
- ✅ Update schedule
- ✅ Delete schedule
- ✅ Bulk create schedules

### 2. **Advanced Filtering**
The `index()` method supports extensive query parameters:

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `class_id` | Integer | Filter by class | `?class_id=1` |
| `teacher_id` | Integer | Filter by teacher | `?teacher_id=5` |
| `subject_id` | Integer | Filter by subject | `?subject_id=3` |
| `day` | String | Filter by day of week | `?day=monday` |
| `room` | String | Filter by room (partial match) | `?room=101` |
| `academic_year` | String | Filter by academic year | `?academic_year=2025-2026` |
| `date` | Date | Convert date to day and filter | `?date=2026-02-18` |
| `start_time_after` | Time | Schedules starting after time | `?start_time_after=08:00` |
| `end_time_before` | Time | Schedules ending before time | `?end_time_before=16:00` |
| `time_from` & `time_to` | Time | Schedules within time range | `?time_from=08:00&time_to=12:00` |
| `sort_by` | String | Sort field | `?sort_by=start_time` |
| `sort_order` | String | Sort order (asc/desc) | `?sort_order=desc` |
| `per_page` | Integer/String | Pagination (or 'all') | `?per_page=20` |
| `with_relations` | String | Comma-separated relations | `?with_relations=class,subject,teacher` |

### 3. **Conflict Detection**
Automatically detects and prevents:
- ✅ **Teacher conflicts** - Same teacher, same day/time
- ✅ **Room conflicts** - Same room, same day/time
- ✅ **Class conflicts** - Same class, same day/time

Returns specific conflict types in error responses with HTTP 409 status.

### 4. **Specialized Endpoints**

#### Class Schedule
```
GET /classes/{classId}/schedule
```
Returns weekly schedule grouped by day for a specific class.

#### Teacher Schedule
```
GET /teachers/{teacherId}/schedule
```
Returns weekly schedule for a specific teacher.

#### Subject Schedule
```
GET /subjects/{subjectId}/schedule
```
Returns all schedules for a specific subject across all classes.

#### Day Schedule
```
GET /schedules/day/{day}
```
Returns all schedules for a specific day with optional class/teacher filters.

#### Room Schedule
```
GET /schedules/room/{room}
```
Returns weekly schedule for a specific room.

#### Weekly Overview
```
GET /schedules/weekly-overview
```
Returns complete weekly overview with statistics.

#### Available Slots
```
GET /schedules/available-slots
```
Shows occupied time slots for scheduling assistance.

#### Check Conflicts
```
POST /schedules/check-conflicts
```
Validates potential schedule before creation/update.

### 5. **Validation**

#### Create/Update Validation
- `class_subject_teacher_id` - Must exist in database
- `day` - Must be valid weekday name (monday-sunday)
- `start_time` - Must be valid time (H:i format)
- `end_time` - Must be after start_time
- `room` - Optional, max 50 characters

### 6. **Response Format**

All endpoints follow consistent JSON structure:

**Success Response:**
```json
{
  "success": true,
  "data": {...},
  "message": "Optional success message"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "error": "Technical error details",
  "errors": {...}  // Validation errors
}
```

**Paginated Response:**
```json
{
  "success": true,
  "data": [...],
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

### 7. **HTTP Status Codes**

| Code | Usage |
|------|-------|
| 200 | Successful GET/PUT/PATCH |
| 201 | Successful POST (created) |
| 400 | Bad request (invalid parameters) |
| 404 | Resource not found |
| 409 | Conflict (scheduling conflict) |
| 422 | Validation error |
| 500 | Server error |

### 8. **Relationship Loading**

Schedules are automatically loaded with nested relationships:
- `assignment` → `ClassSubjectTeacher`
  - `class` → Class details
  - `subject` → Subject details
  - `teacher` → Teacher and user details

### 9. **Sorting Features**

- Custom day sorting maintains proper week order (Monday-Sunday)
- Secondary sorting by start_time when sorting by day
- Supports ascending/descending order

### 10. **Bulk Operations**

The `bulkStore()` method:
- Creates multiple schedules in a single request
- Validates each schedule individually
- Checks conflicts for each schedule
- Uses database transactions for data integrity
- Returns summary with successful creations and failures

## Implementation Best Practices Used

1. ✅ **Exception Handling** - Try-catch blocks in all methods
2. ✅ **Validation** - Laravel Validator with detailed rules
3. ✅ **Eager Loading** - Prevents N+1 query problems
4. ✅ **Query Optimization** - Efficient database queries
5. ✅ **Conflict Prevention** - Business logic validation
6. ✅ **Pagination** - Configurable with sensible defaults
7. ✅ **Filtering** - Multiple independent filters
8. ✅ **Consistent Responses** - Standardized JSON structure
9. ✅ **HTTP Status Codes** - Proper RESTful status codes
10. ✅ **Code Reusability** - Helper methods for conflict checking
11. ✅ **Documentation** - Comprehensive inline comments
12. ✅ **Database Transactions** - For bulk operations

## Route Protection

All schedule routes are protected by:
- `auth:sanctum` middleware - Requires authentication
- `role:admin` middleware - Admin-only access for modifications
- Viewing endpoints can be opened to teachers/students as needed

## Example Usage

### Create a Schedule
```bash
POST /api/schedules
Content-Type: application/json
Authorization: Bearer {token}

{
  "class_subject_teacher_id": 5,
  "day": "monday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101"
}
```

### Get Class Schedule with Filters
```bash
GET /api/schedules?class_id=1&day=monday&per_page=all&with_relations=class,subject,teacher
Authorization: Bearer {token}
```

### Bulk Create Schedules
```bash
POST /api/schedules/bulk
Content-Type: application/json
Authorization: Bearer {token}

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

### Check for Conflicts Before Scheduling
```bash
POST /api/schedules/check-conflicts
Content-Type: application/json
Authorization: Bearer {token}

{
  "class_subject_teacher_id": 5,
  "day": "monday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101"
}
```

## Testing Recommendations

1. Test conflict detection for all three types (teacher, room, class)
2. Test bulk creation with mixed valid/invalid schedules
3. Test all filter combinations
4. Test pagination edge cases
5. Test time overlap scenarios
6. Test invalid day names
7. Test invalid time formats
8. Test with non-existent IDs
9. Test sorting by different fields
10. Test weekly overview statistics accuracy

## Future Enhancement Possibilities

- [ ] Recurring schedule templates
- [ ] Schedule import/export (CSV, Excel)
- [ ] Schedule printing/PDF generation
- [ ] Email notifications for schedule changes
- [ ] Schedule version history
- [ ] Copy schedule from previous academic year
- [ ] Automatic room assignment suggestions
- [ ] Schedule optimization algorithms
- [ ] Mobile calendar integration
- [ ] Break/lunch period management
- [ ] Holiday/vacation period handling
- [ ] Substitute teacher scheduling
- [ ] Room capacity validation
- [ ] Equipment/resource scheduling
- [ ] Schedule analytics and reports

---

**Last Updated:** February 18, 2026
**Version:** 1.0.0
**Author:** AI Assistant
