import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../models/student.dart';
import '../services/api_service.dart';
import '../services/student_service.dart';

class ChildrenProvider extends ChangeNotifier {
  final ApiService _apiService;
  late final StudentService _studentService;

  List<Student> _children = [];
  Student? _selectedChild;
  bool _isLoading = false;
  String? _error;

  ChildrenProvider(this._apiService) {
    _studentService = StudentService(_apiService);
  }

  List<Student> get children => _children;
  Student? get selectedChild => _selectedChild;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get hasChildren => _children.isNotEmpty;

  /// Fetch children list
  Future<void> fetchChildren() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _children = await _studentService.getMyChildren();
      // Auto-select first child if none selected
      if (_selectedChild == null && _children.isNotEmpty) {
        _selectedChild = _children.first;
      }
      // Update selected child reference if it exists in new list
      if (_selectedChild != null) {
        final updated = _children.where((c) => c.id == _selectedChild!.id).firstOrNull;
        _selectedChild = updated ?? (_children.isNotEmpty ? _children.first : null);
      }
    } on DioException catch (e) {
      _error = _apiService.extractErrorMessage(e);
    } catch (e) {
      _error = 'Failed to load children';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Select a child
  void selectChild(Student child) {
    _selectedChild = child;
    notifyListeners();
  }

  /// Clear state on logout
  void clear() {
    _children = [];
    _selectedChild = null;
    _error = null;
    notifyListeners();
  }
}
