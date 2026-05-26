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
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      remainingAmount: (json['remaining_amount'] as num?)?.toDouble() ?? 0,
      balance: (json['balance'] as num?)?.toDouble() ?? 0,
      monthlyAmount: (json['monthly_amount'] as num?)?.toDouble() ?? 0,
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
      totalFees: (json['total_fees'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      remainingAmount: (json['remaining_amount'] as num?)?.toDouble() ?? 0,
      balance: (json['balance'] as num?)?.toDouble() ?? 0,
      monthlyAmount: (json['monthly_amount'] as num?)?.toDouble() ?? 0,
      discountType: json['discount_type'] as String?,
      discountValue: (json['discount_value'] as num?)?.toDouble() ?? 0,
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
      amountDue: (json['amount_due'] as num?)?.toDouble() ?? 0,
      amountPaid: (json['amount_paid'] as num?)?.toDouble() ?? 0,
      balance: (json['balance'] as num?)?.toDouble() ?? 0,
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
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      paymentType: json['payment_type'] as String? ?? '',
      paidDate: json['paid_date'] as String? ?? '',
      status: json['status'] as String? ?? '',
      note: json['note'] as String?,
    );
  }
}
