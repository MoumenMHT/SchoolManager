import 'package:flutter/foundation.dart';

class ApiConfig {
  // ── API URL ────────────────────────────────────────────────────
  // Override with: flutter run --dart-define=API_BASE_URL=http://your-ip:8000/api
  static const String _androidEmulatorBaseUrl = 'http://10.0.2.2:8000/api';
  static const String _localBaseUrl = 'http://127.0.0.1:8000/api';

  static String get baseUrl {
    const String env = String.fromEnvironment('API_BASE_URL');
    if (env.isNotEmpty) return env;
    if (kIsWeb) return _localBaseUrl;
    if (defaultTargetPlatform == TargetPlatform.android) {
      return _androidEmulatorBaseUrl;
    }
    return _localBaseUrl;
  }

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  // Token storage key
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String localeKey = 'app_locale';
}
