# Dashboard Testing Guide

## Prerequisites

1. **API Server Running**
   ```bash
   cd API
   php artisan serve
   ```
   The API should be running on `http://localhost:8000`

2. **Database Seeded**
   Ensure you have test data:
   ```bash
   cd API
   php artisan migrate:fresh --seed
   ```

3. **Web Server Running**
   ```bash
   cd Web
   npm run dev
   ```
   Access the dashboard at `http://localhost:5174`

## Test Credentials

Use one of the seeded users to login:

### Admin User
- **Email**: `admin@schoolmanager.com`
- **Password**: `password123`
- **Access**: Full dashboard statistics

### Teacher User
- **Email**: `teacher@schoolmanager.com`
- **Password**: `password123`
- **Access**: Limited to own classes

### Parent User
- **Email**: `parent@schoolmanager.com`
- **Password**: `password123`
- **Access**: Limited to own children

## Testing Steps

### 1. Authentication
- [ ] Navigate to login page
- [ ] Enter credentials
- [ ] Verify successful login and token storage
- [ ] Check redirect to dashboard

### 2. Dashboard Loading
- [ ] Verify loading spinner appears
- [ ] Check all widgets load
- [ ] Confirm no TypeScript errors in console
- [ ] Verify data displays correctly

### 3. Statistics Widget
- [ ] Verify total students count
- [ ] Verify total teachers count
- [ ] Verify total classes count
- [ ] Check average students per class calculation

### 4. Financial Widget
- [ ] Check total revenue amount
- [ ] Verify pending payments
- [ ] Verify late payments
- [ ] Check payment rate percentage and color
- [ ] Verify progress bar displays correctly

### 5. Recent Payments Widget
- [ ] Verify table displays payments
- [ ] Check student names appear
- [ ] Verify payment types show correct tags
- [ ] Check amounts are formatted correctly
- [ ] Verify dates are formatted properly
- [ ] Check pagination works (if > 5 records)

### 6. Students by Class Widget
- [ ] Verify bar chart renders
- [ ] Check all classes are displayed
- [ ] Verify student counts are accurate
- [ ] Check class list below chart
- [ ] Verify colors are distinct

### 7. Attendance Widget
- [ ] Check overall attendance rate
- [ ] Verify percentage is correct
- [ ] Check progress bar color (green/orange/red)
- [ ] Verify attendance breakdown:
  - Present count and percentage
  - Absent count and percentage
  - Late count and percentage
  - Excused count and percentage

### 8. Error Handling
- [ ] Stop API server
- [ ] Refresh dashboard
- [ ] Verify error message appears
- [ ] Check error is user-friendly
- [ ] Start API server
- [ ] Reload page
- [ ] Verify dashboard loads correctly

### 9. Empty States
- [ ] Test with no payment data
- [ ] Test with no attendance data
- [ ] Verify empty state messages appear
- [ ] Check icons display correctly

### 10. Responsive Design
- [ ] Test on desktop (1920x1080)
- [ ] Test on tablet (768px)
- [ ] Test on mobile (375px)
- [ ] Verify all widgets stack correctly
- [ ] Check charts are responsive

## Expected API Responses

### Dashboard Stats Endpoint
**GET** `/api/dashboard/stats`

**Response:**
```json
{
  "success": true,
  "data": {
    "overview": {
      "total_students": 150,
      "total_teachers": 25,
      "total_classes": 12
    },
    "financial": {
      "total_revenue": 125000.50,
      "pending_payments": 15000.00,
      "late_payments": 5000.00,
      "payment_rate": 89.23
    },
    "academic": {
      "average_grade": 14.5,
      "attendance_rate": 92.5
    },
    "attendance_breakdown": {
      "present": 450,
      "absent": 25,
      "late": 15,
      "excused": 10
    },
    "students_by_class": [
      {
        "class_name": "6ème A",
        "student_count": 28
      }
    ],
    "recent_payments": [...]
  }
}
```

## Debugging

### Check Network Requests
1. Open Browser DevTools (F12)
2. Go to Network tab
3. Filter by XHR/Fetch
4. Look for `/api/dashboard/stats` request
5. Check status code (should be 200)
6. Verify response data

### Check Console Errors
1. Open Browser DevTools (F12)
2. Go to Console tab
3. Look for TypeScript errors (red)
4. Check API error messages
5. Verify no CORS errors

### Check Local Storage
1. Open Browser DevTools (F12)
2. Go to Application > Local Storage
3. Verify `auth_token` exists
4. Verify `user` object exists
5. Check token format

### Common Issues

#### CORS Error
**Problem**: CORS policy blocking requests

**Solution**: 
1. Check API `config/cors.php`
2. Ensure `allowed_origins` includes frontend URL
3. Restart API server

#### 401 Unauthorized
**Problem**: Token expired or invalid

**Solution**:
1. Clear localStorage
2. Login again
3. Check token expiration settings

#### Empty Data
**Problem**: No data showing on dashboard

**Solution**:
1. Check API has seeded data
2. Run `php artisan db:seed`
3. Verify API returns data in Postman

#### TypeScript Errors
**Problem**: Type errors in console

**Solution**:
1. Run `npm run type-check`
2. Check component prop types
3. Verify API response matches interfaces

## Performance Metrics

Expected loading times:
- **Initial page load**: < 2 seconds
- **API call**: < 500ms
- **Chart rendering**: < 300ms
- **Total time to interactive**: < 3 seconds

## Browser Compatibility

Tested and working on:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

## Next Steps After Testing

1. [ ] Implement real-time updates
2. [ ] Add date range filters
3. [ ] Create export functionality
4. [ ] Add more detailed analytics
5. [ ] Implement caching for better performance
6. [ ] Add unit tests for components
7. [ ] Add E2E tests with Cypress/Playwright

## Reporting Issues

If you find bugs, please report with:
1. Browser and version
2. Steps to reproduce
3. Expected vs actual behavior
4. Console errors
5. Network request details
6. Screenshots

## Success Criteria

The dashboard is working correctly when:
- ✅ All widgets display real data
- ✅ No TypeScript errors in console
- ✅ No network errors
- ✅ Responsive on all screen sizes
- ✅ Loading and error states work
- ✅ Charts render correctly
- ✅ Data is accurate and up-to-date
