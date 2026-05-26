import 'package:dio/dio.dart';
import '../models/user.dart';
import 'api_service.dart';

class AuthService {
  final ApiService _api;

  AuthService(this._api);

  /// Login with username/phone and password
  Future<({User user, String token})> login(String identifier, String password) async {
    // Determine if identifier is phone or username
    final isPhone = RegExp(r'^[\d+\s()-]+$').hasMatch(identifier);

    final response = await _api.post('/login', data: {
      if (isPhone) 'phone': identifier else 'username': identifier,
      'password': password,
    });

    final body = response.data as Map<String, dynamic>;

    if (body['success'] != true) {
      throw Exception(body['message'] ?? 'Login failed');
    }

    final token = body['token'] as String;
    final user = User.fromJson(body['user'] as Map<String, dynamic>);

    // Store token and user
    await _api.setToken(token);
    await _api.setUserData(body['user'] as Map<String, dynamic>);

    return (user: user, token: token);
  }

  /// Logout
  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } catch (_) {
      // Ignore errors — clear token regardless
    } finally {
      await _api.removeToken();
    }
  }

  /// Get current authenticated user
  Future<User> getCurrentUser() async {
    final response = await _api.get('/me');
    final body = response.data as Map<String, dynamic>;

    if (body['success'] != true) {
      throw Exception('Failed to get user');
    }

    final user = User.fromJson(body['user'] as Map<String, dynamic>);
    await _api.setUserData(body['user'] as Map<String, dynamic>);
    return user;
  }

  /// Change password
  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    final response = await _api.post('/change-password', data: {
      'current_password': currentPassword,
      'new_password': newPassword,
      'new_password_confirmation': confirmation,
    });

    final body = response.data as Map<String, dynamic>;
    return body['message'] as String? ?? 'Password changed';
  }

  /// Check if token exists
  Future<bool> hasToken() async {
    final token = await _api.getToken();
    return token != null && token.isNotEmpty;
  }
}
