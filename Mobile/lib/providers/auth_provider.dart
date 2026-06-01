import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../models/user.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

enum AuthStatus { initial, authenticated, unauthenticated, loading }

class AuthProvider extends ChangeNotifier {
  final ApiService _apiService;
  late final AuthService _authService;

  AuthStatus _status = AuthStatus.initial;
  User? _user;
  String? _error;

  AuthProvider(this._apiService) {
    _authService = AuthService(_apiService);
    _apiService.onUnauthorized = _handleUnauthorized;
  }

  bool _isAllowedRole(User user) {
    return user.role == 'parent' || user.role == 'teacher';
  }

  AuthStatus get status => _status;
  User? get user => _user;
  String? get error => _error;
  bool get isAuthenticated => _status == AuthStatus.authenticated;
  bool get isLoading => _status == AuthStatus.loading;

  /// Check if user is already logged in
  Future<void> checkAuth() async {
    _status = AuthStatus.loading;
    notifyListeners();

    try {
      final hasToken = await _authService.hasToken();
      if (!hasToken) {
        _status = AuthStatus.unauthenticated;
        notifyListeners();
        return;
      }

      _user = await _authService.getCurrentUser();

      // Verify this is a supported account
      if (!_isAllowedRole(_user!)) {
        await _authService.logout();
        _error = 'This app supports parent and teacher accounts only.';
        _status = AuthStatus.unauthenticated;
        _user = null;
        notifyListeners();
        return;
      }

      _status = AuthStatus.authenticated;
      _error = null;
    } catch (e) {
      _status = AuthStatus.unauthenticated;
      _user = null;
    }
    notifyListeners();
  }

  /// Login
  Future<bool> login(String identifier, String password) async {
    _status = AuthStatus.loading;
    _error = null;
    notifyListeners();

    try {
      final result = await _authService.login(identifier, password);
      _user = result.user;

      // Verify this is a supported account
      if (!_isAllowedRole(_user!)) {
        await _authService.logout();
        _error = 'This app supports parent and teacher accounts only.';
        _status = AuthStatus.unauthenticated;
        _user = null;
        notifyListeners();
        return false;
      }

      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      _error = _apiService.extractErrorMessage(e);
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    } catch (e) {
      _error = e.toString().replaceFirst('Exception: ', '');
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  /// Logout
  Future<void> logout() async {
    await _authService.logout();
    _user = null;
    _status = AuthStatus.unauthenticated;
    _error = null;
    notifyListeners();
  }

  /// Change password
  Future<String> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    return await _authService.changePassword(
      currentPassword: currentPassword,
      newPassword: newPassword,
      confirmation: confirmation,
    );
  }

  void _handleUnauthorized() {
    _user = null;
    _status = AuthStatus.unauthenticated;
    _error = 'Session expired. Please log in again.';
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
