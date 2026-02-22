# SchoolHub Dashboard - TypeScript Implementation

This document provides information about the TypeScript implementation of the SchoolHub dashboard.

## Overview

The dashboard has been fully migrated to TypeScript with the following improvements:
- Type-safe API calls
- Comprehensive type definitions
- Better IDE support and autocompletion
- Runtime error prevention
- Improved code maintainability

## Architecture

### Type Definitions (`src/types/index.ts`)
Contains all TypeScript interfaces for:
- Student, Teacher, Class, Subject models
- Payment, Attendance, Grade records
- API responses and pagination
- Dashboard statistics

### Services Layer

#### ApiService (`src/service/ApiService.ts`)
- Centralized HTTP client using Axios
- Automatic token management
- Request/response interceptors
- Error handling and authentication

#### DashboardService (`src/service/DashboardService.ts`)
- Dashboard-specific API calls
- Statistics fetching
- Type-safe data retrieval

### Components

#### Dashboard (`src/views/Dashboard.vue`)
Main dashboard page that:
- Fetches statistics on mount
- Handles loading and error states
- Distributes data to widgets

#### Widgets
1. **StatsWidget** - Overview statistics (students, teachers, classes)
2. **RecentPaymentsWidget** - Recent payment transactions with DataTable
3. **StudentsByClassWidget** - Bar chart showing student distribution
4. **FinancialWidget** - Revenue, pending, and late payments overview
5. **AttendanceWidget** - Attendance rate and breakdown

## Configuration

### Environment Variables
Create a `.env` file based on `.env.example`:

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_NAME=SchoolHub
VITE_APP_VERSION=1.0.0
```

### TypeScript Configuration
- `tsconfig.json` - Main TypeScript configuration
- `tsconfig.node.json` - Node/Vite configuration
- `src/env.d.ts` - Type declarations for `.vue` files

## API Integration

### Authentication
The dashboard requires authentication. Ensure you have a valid token:

```typescript
import apiService from '@/service/ApiService';

// Login
const response = await apiService.login('user@example.com', 'password');
// Token is automatically stored

// Check authentication
if (apiService.isAuthenticated()) {
  // User is logged in
}
```

### Dashboard Data
The dashboard automatically fetches data from `/api/dashboard/stats`:

```typescript
import dashboardService from '@/service/DashboardService';

const stats = await dashboardService.getStats('2026');
```

## Development

### Run Development Server
```bash
npm run dev
```

### Type Checking
```bash
npm run type-check
```

### Build for Production
```bash
npm run build
```

### Linting
```bash
npm run lint
```

## API Endpoints Used

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/dashboard/stats` | GET | Get all dashboard statistics |
| `/students` | GET | Get students list |
| `/teachers` | GET | Get teachers list |
| `/classes` | GET | Get classes list |
| `/payments` | GET | Get payments list |
| `/attendance` | GET | Get attendance records |

## Data Flow

1. User navigates to Dashboard
2. `Dashboard.vue` mounts and calls `loadDashboardData()`
3. `DashboardService.getStats()` makes API call
4. `ApiService` handles the HTTP request with authentication
5. Response is typed as `DashboardStats`
6. Data is passed to child widgets as props
7. Widgets render with real data

## Error Handling

The dashboard implements comprehensive error handling:
- Loading states during API calls
- Error messages for failed requests
- Automatic token refresh/redirect on 401
- Empty state handling for no data

## TypeScript Features Used

- **Interfaces**: Type definitions for all data models
- **Generics**: Reusable API response types
- **Union Types**: Status enums (present|absent|late|excused)
- **Type Guards**: Runtime type checking
- **Strict Mode**: Enabled for maximum type safety
- **Path Aliases**: `@/` for clean imports

## Chart Integration

Uses Chart.js for data visualization:
```typescript
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
```

The `StudentsByClassWidget` displays a bar chart showing student distribution across classes.

## Best Practices

1. **Always use types**: Never use `any` unless absolutely necessary
2. **Validate API responses**: Check for null/undefined
3. **Handle errors gracefully**: Show user-friendly messages
4. **Use composables**: Reusable logic like `useAsync`
5. **Component props**: Always define prop types with interfaces
6. **Async/await**: Modern promise handling
7. **Environment variables**: Configuration through `.env`

## Troubleshooting

### API Connection Issues
- Verify API is running on `http://localhost:8000`
- Check CORS configuration in Laravel API
- Ensure `.env` has correct `VITE_API_BASE_URL`

### Type Errors
- Run `npm run type-check` to see all type errors
- Ensure all dependencies are installed
- Check `tsconfig.json` configuration

### Authentication Issues
- Clear localStorage: `localStorage.clear()`
- Verify token is valid in API
- Check network tab for 401 responses

## Future Enhancements

- Add real-time updates with WebSockets
- Implement data caching with Pinia/Vuex
- Add export functionality (PDF/Excel)
- Implement filters and date range selection
- Add more detailed analytics charts
- Mobile responsive improvements

## Dependencies

### Runtime
- Vue 3.4+
- PrimeVue 4.5+
- Axios 1.13+
- Chart.js 3.3+

### Development
- TypeScript 5.7+
- Vue-tsc (Vue TypeScript compiler)
- Vite 5.3+

## License

MIT License - See LICENSE file for details
