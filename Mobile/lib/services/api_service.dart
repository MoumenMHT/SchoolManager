import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/api_config.dart';

class ApiService {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  String _locale = 'en';

  // Callback for 401 handling — set by AuthProvider
  void Function()? onUnauthorized;

  ApiService() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.receiveTimeout,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Attach token
        final token = await getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        // Attach locale
        options.headers['Accept-Language'] = _locale;
        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          final requestPath = error.requestOptions.path;
          final isLoginRequest = requestPath.endsWith('/login');
          final token = await getToken();

          if (!isLoginRequest && token != null) {
            await removeToken();
            onUnauthorized?.call();
          }
        }
        return handler.next(error);
      },
    ));
  }

  // ── Locale ─────────────────────────────────────────────────────
  void setLocale(String locale) {
    _locale = locale;
  }

  // ── Token management ──────────────────────────────────────────
  Future<String?> getToken() async {
    return await _storage.read(key: ApiConfig.tokenKey);
  }

  Future<void> setToken(String token) async {
    await _storage.write(key: ApiConfig.tokenKey, value: token);
  }

  Future<void> removeToken() async {
    await _storage.delete(key: ApiConfig.tokenKey);
    await _storage.delete(key: ApiConfig.userKey);
  }

  // ── User storage ──────────────────────────────────────────────
  Future<void> setUserData(Map<String, dynamic> user) async {
    await _storage.write(key: ApiConfig.userKey, value: jsonEncode(user));
  }

  Future<Map<String, dynamic>?> getUserData() async {
    final data = await _storage.read(key: ApiConfig.userKey);
    if (data != null) {
      return jsonDecode(data) as Map<String, dynamic>;
    }
    return null;
  }

  // ── HTTP methods ──────────────────────────────────────────────
  Future<Response> get(String path, {Map<String, dynamic>? params}) async {
    return await _dio.get(path, queryParameters: params);
  }

  Future<Response> post(String path, {dynamic data}) async {
    return await _dio.post(path, data: data);
  }

  Future<Response> put(String path, {dynamic data}) async {
    return await _dio.put(path, data: data);
  }

  Future<Response> delete(String path, {dynamic data}) async {
    return await _dio.delete(path, data: data);
  }

  // ── Helper to extract data from standard API response ─────────
  /// The API returns { success: bool, data: T, message?: string }
  dynamic extractData(Response response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      if (body.containsKey('data')) return body['data'];
      return body;
    }
    return body;
  }

  bool isSuccess(Response response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      return body['success'] == true;
    }
    return response.statusCode == 200 || response.statusCode == 201;
  }

  String? extractMessage(Response response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      return body['message'] as String?;
    }
    return null;
  }

  String extractErrorMessage(DioException error) {
    if (error.response?.data is Map<String, dynamic>) {
      final data = error.response!.data as Map<String, dynamic>;
      if (data.containsKey('message')) return data['message'] as String;
      if (data.containsKey('errors')) {
        final errors = data['errors'] as Map<String, dynamic>;
        return errors.values
            .expand((v) => v is List ? v : [v])
            .join('\n');
      }
    }
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Connection timed out. Please try again.';
      case DioExceptionType.connectionError:
        return 'No internet connection.';
      default:
        return error.message ?? 'An unexpected error occurred.';
    }
  }
}
