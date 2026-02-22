# Quick Start Guide - SchoolHub Dashboard

## 🚀 Get Started in 3 Minutes

### Step 1: Start the API Server (Terminal 1)
```bash
cd API
php artisan serve
```
✅ API should be running at `http://localhost:8000`

### Step 2: Start the Web Server (Terminal 2)
```bash
cd Web
npm run dev
```
✅ Web should be running at `http://localhost:5174`

### Step 3: Login to Dashboard
1. Open browser: `http://localhost:5174`
2. Click "Login" or navigate to login page
3. Use these credentials:
   - **Email**: `admin@schoolmanager.com`
   - **Password**: `password123`
4. You'll be redirected to the dashboard

## 🎯 What You'll See

### Dashboard Overview
The dashboard displays 4 main sections:

#### 1. Statistics Cards (Top Row)
- **Students**: Total active students
- **Teachers**: Total faculty members
- **Classes**: Total active classes
- **Avg Students/Class**: Calculated average

#### 2. Recent Payments (Left Column)
- Table showing last 10 payments
- Student names, payment types, amounts
- Payment dates and status
- Pagination if more than 5 records

#### 3. Students by Class Chart (Left Column)
- Bar chart showing student distribution
- Color-coded bars for each class
- Class breakdown grid below

#### 4. Financial Overview (Right Column)
- Total revenue (green)
- Pending payments (orange)
- Late payments (red)
- Payment collection rate with progress bar

#### 5. Attendance Overview (Right Column)
- Overall attendance rate (last 30 days)
- Breakdown by status:
  - Present (green)
  - Absent (red)
  - Late (orange)
  - Excused (blue)

## 🔧 Troubleshooting

### Dashboard Not Loading?
**Check:**
1. Is API running? → `http://localhost:8000/api/dashboard/stats`
2. Are you logged in? → Check for token in localStorage
3. CORS issues? → Check browser console

**Fix:**
```bash
# In API folder
php artisan config:clear
php artisan cache:clear
```

### No Data Showing?
**Check:**
```bash
# Seed the database
cd API
php artisan migrate:fresh --seed
```

### Login Not Working?
**Default credentials:**
- Email: `admin@schoolmanager.com`
- Password: `password123`

**Clear cache:**
```javascript
// In browser console
localStorage.clear()
// Then refresh and login again
```

## 📱 Features to Test

### ✅ Check These Work:
- [ ] Login with admin credentials
- [ ] Dashboard loads without errors
- [ ] All 4 stat cards show numbers
- [ ] Recent payments table appears
- [ ] Bar chart displays
- [ ] Financial cards show amounts
- [ ] Attendance breakdown displays
- [ ] Charts are responsive
- [ ] No console errors (F12 → Console)
- [ ] No TypeScript errors

### 🎨 Responsive Design
Test at different screen sizes:
- Desktop: 1920px
- Tablet: 768px
- Mobile: 375px

**How to test:**
1. Press F12
2. Click device toolbar icon
3. Select different devices

## 🔍 Verify API Connection

### Test API Directly
```bash
# Login (replace with your credentials)
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@schoolmanager.com","password":"password123"}'

# Get dashboard stats (replace TOKEN with actual token)
curl -X GET http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer TOKEN"
```

### Or use Browser
1. Login to dashboard
2. Open DevTools (F12)
3. Go to Network tab
4. Refresh page
5. Look for `dashboard/stats` request
6. Check Status (should be 200)
7. Click to see Response data

## 📊 Sample Data

After seeding, you should see:
- **~50-150 students**
- **~10-25 teachers**
- **~5-12 classes**
- **Payment rate: 80-95%**
- **Attendance rate: 85-95%**
- **Recent payments: 5-10 records**

## 🎯 Next Steps

### For Development
1. Read `TYPESCRIPT_DASHBOARD.md` for architecture
2. Read `TESTING_GUIDE.md` for testing checklist
3. Read `IMPLEMENTATION_SUMMARY.md` for overview

### For Customization
1. Modify colors in widgets
2. Add more charts
3. Customize card layouts
4. Add filters/date pickers
5. Add export functionality

### For Production
1. Update `.env` with production URL
2. Run `npm run build`
3. Deploy `dist/` folder
4. Configure web server (nginx/apache)
5. Set up SSL certificate

## 📞 Need Help?

### Check Logs
**API Logs:**
```bash
cd API
tail -f storage/logs/laravel.log
```

**Browser Console:**
1. Press F12
2. Check Console tab for errors
3. Check Network tab for API calls

### Common Errors

**Error: "Failed to load dashboard data"**
- API is not running
- Token expired
- CORS issue

**Error: "Network Error"**
- API server stopped
- Wrong API URL in `.env`
- Firewall blocking

**Error: Empty dashboard**
- No data in database
- Run: `php artisan db:seed`

## ✨ Tips

1. **Keep both terminals open** (API + Web)
2. **Use admin account** for full access
3. **Seed fresh data** if issues occur
4. **Check console** for errors
5. **Clear localStorage** if login fails
6. **Restart servers** if things break

## 🎉 You're Ready!

If you see:
- ✅ No errors in console
- ✅ Dashboard loads quickly
- ✅ All widgets display data
- ✅ Charts render correctly
- ✅ Numbers make sense

**Congratulations! Your dashboard is working perfectly!** 🎊

For detailed documentation, see:
- `TYPESCRIPT_DASHBOARD.md` - Technical details
- `TESTING_GUIDE.md` - Complete testing
- `IMPLEMENTATION_SUMMARY.md` - Feature overview
