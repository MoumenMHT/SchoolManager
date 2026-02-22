# SchoolHub - School Management System

A comprehensive school management system built with Laravel (API) and Vue.js (Web Frontend).

## 📋 Project Structure

```
SchoolHub/
├── API/          # Laravel Backend API
└── Web/          # Vue.js Frontend
```

## 🚀 Getting Started

### Prerequisites

- **PHP** >= 8.1
- **Composer** - PHP dependency manager
- **Node.js** >= 16.x and npm
- **MySQL** or **PostgreSQL** database
- **Git**

### 1. Clone the Repository

```bash
git clone https://github.com/MoumenMHT/SchoolManager.git
cd SchoolHub
```

## 🔧 API Setup (Laravel Backend)

### Installation Steps

1. **Navigate to the API directory:**
   ```bash
   cd API
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

4. **Configure your database in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=schoolhub
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

6. **Run database migrations:**
   ```bash
   php artisan migrate
   ```

7. **Seed the database (optional):**
   ```bash
   php artisan db:seed
   ```

8. **Start the development server:**
   ```bash
   php artisan serve
   ```

   The API will be available at `http://localhost:8000`

### Running Tests

```bash
php artisan test
```

For more details, see [API Documentation](API/README.md)

## 🎨 Web Setup (Vue.js Frontend)

### Installation Steps

1. **Navigate to the Web directory:**
   ```bash
   cd Web
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

4. **Configure API endpoint in `.env`:**
   ```env
   VITE_API_URL=http://localhost:8000/api
   ```

5. **Start the development server:**
   ```bash
   npm run dev
   ```

   The application will be available at `http://localhost:5173`

### Build for Production

```bash
npm run build
```

For more details, see [Web Documentation](Web/README.md)

## 📚 Features

- **Student Management** - Track student information, enrollment, and academic records
- **Teacher Management** - Manage teacher profiles and assignments
- **Parent Portal** - Parent access to student information and communications
- **Class & Schedule Management** - Organize classes, subjects, and schedules
- **Attendance Tracking** - Monitor student attendance
- **Grade Management** - Record and manage student grades
- **Payment Processing** - Track tuition and fee payments
- **Dashboard & Analytics** - Comprehensive overview and insights

## 🛠️ Tech Stack

### Backend (API)
- Laravel 11
- MySQL/PostgreSQL
- Laravel Sanctum (Authentication)
- PHPUnit (Testing)

### Frontend (Web)
- Vue.js 3
- Vue Router
- PrimeVue UI Components
- Vite
- TypeScript
- Tailwind CSS

## 📖 Documentation

- [API Complete Reference](API/API_COMPLETE_REFERENCE.md)
- [API Documentation](API/API_DOCUMENTATION.md)
- [Schedule API Reference](API/SCHEDULE_API_REFERENCE.md)
- [Testing Guide](API/TESTING_GUIDE.md)
- [Web Quick Start](Web/QUICK_START.md)
- [TypeScript Dashboard](Web/TYPESCRIPT_DASHBOARD.md)

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](Web/LICENSE.md) file for details.

## 👥 Authors

- Moumen MHT

## 🐛 Issues

If you encounter any issues, please file them in the [Issues](https://github.com/MoumenMHT/SchoolManager/issues) section.

## 📧 Support

For support, email moumenabdou482@gmail.com or open an issue in this repository.
