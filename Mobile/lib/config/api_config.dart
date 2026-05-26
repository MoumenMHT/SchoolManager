class ApiConfig {
  // ── Production URL ─────────────────────────────────────────────
  // Change this to your actual production API URL
  static const String baseUrl = 'http://10.0.2.2:8000/api'; // Android emulator → localhost
  // static const String baseUrl = 'http://localhost:8000/api'; // iOS simulator
  // static const String baseUrl = 'https://your-domain.com/api'; // Production

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  // Token storage key
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String localeKey = 'app_locale';
}
