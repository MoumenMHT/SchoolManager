double _asDouble(dynamic value) {
  if (value == null) return 0;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}

class ContractSummary {
  final int contractId;
  final String contractNumber;
  final String academicYear;
  final double totalAmount;
  final double paidAmount;
  final double remainingAmount;
  final double balance;
  final double monthlyAmount;
  final String? nextDueDate;
  final int unpaidBillsCount;
  final int lateBillsCount;
  final Payment? lastPayment;

  ContractSummary({
    required this.contractId,
    required this.contractNumber,
    required this.academicYear,
    required this.totalAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.balance,
    required this.monthlyAmount,
    this.nextDueDate,
    required this.unpaidBillsCount,
    required this.lateBillsCount,
    this.lastPayment,
  });

  factory ContractSummary.fromJson(Map<String, dynamic> json) {
    return ContractSummary(
      contractId: json['contract_id'] as int,
      contractNumber: json['contract_number'] as String? ?? '',
      academicYear: json['academic_year'] as String? ?? '',
      totalAmount: _asDouble(json['total_amount']),
      paidAmount: _asDouble(json['paid_amount']),
      remainingAmount: _asDouble(json['remaining_amount']),
      balance: _asDouble(json['balance']),
      monthlyAmount: _asDouble(json['monthly_amount']),
      nextDueDate: json['next_due_date'] as String?,
      unpaidBillsCount: json['unpaid_bills_count'] as int? ?? 0,
      lateBillsCount: json['late_bills_count'] as int? ?? 0,
      lastPayment: json['last_payment'] != null
          ? Payment.fromJson(json['last_payment'] as Map<String, dynamic>)
          : null,
    );
  }

  double get paymentProgress =>
      totalAmount > 0 ? (paidAmount / totalAmount).clamp(0, 1) : 0;
}

class Contract {
  final int id;
  final String? contractNumber;
  final int parentId;
  final String academicYear;
  final double totalFees;
  final double paidAmount;
  final double remainingAmount;
  final double balance;
  final double monthlyAmount;
  final String? discountType;
  final double discountValue;
  final String? discountReason;
  final String status;
  final String? startDate;
  final String? endDate;
  final String? notes;
  final List<Bill>? bills;
  final List<Payment>? payments;

  Contract({
    required this.id,
    this.contractNumber,
    required this.parentId,
    required this.academicYear,
    required this.totalFees,
    required this.paidAmount,
    required this.remainingAmount,
    required this.balance,
    required this.monthlyAmount,
    this.discountType,
    this.discountValue = 0,
    this.discountReason,
    required this.status,
    this.startDate,
    this.endDate,
    this.notes,
    this.bills,
    this.payments,
  });

  factory Contract.fromJson(Map<String, dynamic> json) {
    return Contract(
      id: json['id'] as int,
      contractNumber: json['contract_number'] as String?,
      parentId: json['parent_id'] as int,
      academicYear: json['academic_year'] as String? ?? '',
      totalFees: _asDouble(json['total_fees']),
      paidAmount: _asDouble(json['paid_amount']),
      remainingAmount: _asDouble(json['remaining_amount']),
      balance: _asDouble(json['balance']),
      monthlyAmount: _asDouble(json['monthly_amount']),
      discountType: json['discount_type'] as String?,
      discountValue: _asDouble(json['discount_value']),
      discountReason: json['discount_reason'] as String?,
      status: json['status'] as String? ?? 'active',
      startDate: json['start_date'] as String?,
      endDate: json['end_date'] as String?,
      notes: json['notes'] as String?,
      bills: json['bills'] != null
          ? (json['bills'] as List)
              .map((b) => Bill.fromJson(b as Map<String, dynamic>))
              .toList()
          : null,
      payments: json['payments'] != null
          ? (json['payments'] as List)
              .map((p) => Payment.fromJson(p as Map<String, dynamic>))
              .toList()
          : null,
    );
  }

  double get effectiveTotal => totalFees - discountValue;

  double get paymentProgress =>
      effectiveTotal > 0 ? (paidAmount / effectiveTotal).clamp(0, 1) : 0;
}

class Bill {
  final int id;
  final int contractId;
  final String monthYear;
  final double amountDue;
  final double amountPaid;
  final double balance;
  final String status; // paid, partial, late, unpaid
  final String dueDate;
  final String? note;

  Bill({
    required this.id,
    required this.contractId,
    required this.monthYear,
    required this.amountDue,
    required this.amountPaid,
    required this.balance,
    required this.status,
    required this.dueDate,
    this.note,
  });

  factory Bill.fromJson(Map<String, dynamic> json) {
    return Bill(
      id: json['id'] as int,
      contractId: json['contract_id'] as int,
      monthYear: json['month_year'] as String? ?? '',
      amountDue: _asDouble(json['amount_due']),
      amountPaid: _asDouble(json['amount_paid']),
      balance: _asDouble(json['balance']),
      status: json['status'] as String? ?? 'unpaid',
      dueDate: json['due_date'] as String? ?? '',
      note: json['note'] as String?,
    );
  }

  bool get isPaid => status == 'paid';
  bool get isOverdue => status == 'late';
}

class Payment {
  final int id;
  final int contractId;
  final double amount;
  final String paymentType;
  final String paidDate;
  final String status;
  final String? note;

  Payment({
    required this.id,
    required this.contractId,
    required this.amount,
    required this.paymentType,
    required this.paidDate,
    required this.status,
    this.note,
  });

  factory Payment.fromJson(Map<String, dynamic> json) {
    return Payment(
      id: json['id'] as int,
      contractId: json['contract_id'] as int,
      amount: _asDouble(json['amount']),
      paymentType: json['payment_type'] as String? ?? '',
      paidDate: json['paid_date'] as String? ?? '',
      status: json['status'] as String? ?? '',
      note: json['note'] as String?,
    );
  }
}
