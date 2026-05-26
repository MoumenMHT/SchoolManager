import '../models/payment.dart';
import 'api_service.dart';

class PaymentService {
  final ApiService _api;

  PaymentService(this._api);

  /// Get payment dashboard overview for the parent
  Future<List<ContractSummary>> getPaymentDashboard() async {
    final response = await _api.get('/parent/dashboard');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((c) => ContractSummary.fromJson(c as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get all contracts for the parent
  Future<List<Contract>> getContracts() async {
    final response = await _api.get('/parent/contracts');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((c) => Contract.fromJson(c as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get contract detail with bills and payments
  Future<Contract> getContractDetail(int contractId) async {
    final response = await _api.get('/parent/contracts/$contractId');
    final data = _api.extractData(response);

    return Contract.fromJson(data as Map<String, dynamic>);
  }

  /// Get all payments for the parent
  Future<List<Payment>> getPayments() async {
    final response = await _api.get('/parent/payments');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((p) => Payment.fromJson(p as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get all bills for the parent
  Future<List<Bill>> getBills() async {
    final response = await _api.get('/parent/bills');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((b) => Bill.fromJson(b as Map<String, dynamic>))
          .toList();
    }
    return [];
  }

  /// Get payments for a specific student
  Future<List<Payment>> getStudentPayments(int studentId) async {
    final response = await _api.get('/parent/students/$studentId/payments');
    final data = _api.extractData(response);

    if (data is List) {
      return data
          .map((p) => Payment.fromJson(p as Map<String, dynamic>))
          .toList();
    }
    return [];
  }
}
