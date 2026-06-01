import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../models/payment.dart';
import '../../services/api_service.dart';
import '../../services/payment_service.dart';
import '../../theme/app_colors.dart';

class PaymentHistoryScreen extends StatefulWidget {
  const PaymentHistoryScreen({super.key});

  @override
  State<PaymentHistoryScreen> createState() => _PaymentHistoryScreenState();
}

class _PaymentHistoryScreenState extends State<PaymentHistoryScreen> {
  late Future<List<Payment>> _paymentsFuture;

  @override
  void initState() {
    super.initState();
    _fetchPayments();
  }

  void _fetchPayments() {
    final paymentService = PaymentService(context.read<ApiService>());
    _paymentsFuture = paymentService.getPayments();
  }

  String _formatPaidDateTime(BuildContext context, String value) {
    final l10n = AppLocalizations.of(context)!;
    if (value.isEmpty) return l10n.notAvailable;

    final parsed = DateTime.tryParse(value);
    if (parsed == null) return l10n.notAvailable;

    final locale = Localizations.localeOf(context).toString();
    return DateFormat.yMMMd(locale).add_Hm().format(parsed);
  }

  String _formatPaidDate(BuildContext context, String value) {
    final l10n = AppLocalizations.of(context)!;
    if (value.isEmpty) return l10n.notAvailable;

    final parsed = DateTime.tryParse(value);
    if (parsed == null) return l10n.notAvailable;

    final locale = Localizations.localeOf(context).toString();
    return DateFormat.yMMMd(locale).format(parsed);
  }

  String _statusLabel(String status, AppLocalizations l10n) {
    switch (status.toLowerCase()) {
      case 'paid':
        return l10n.paid;
      case 'unpaid':
        return l10n.unpaid;
      case 'partial':
        return l10n.partial;
      case 'late':
      case 'overdue':
        return l10n.overdue;
      default:
        return status.isEmpty ? l10n.notAvailable : status;
    }
  }

  Color _statusColor(String status) {
    return AppColors.billStatusColor(status);
  }

  void _showPaymentDetails(BuildContext context, Payment payment) {
    final l10n = AppLocalizations.of(context)!;
    final amountText = '${payment.amount.toStringAsFixed(2)} DZD';
    final dateText = _formatPaidDateTime(context, payment.paidDate);
    final typeText = payment.paymentType.isEmpty ? l10n.notAvailable : payment.paymentType;
    final statusText = _statusLabel(payment.status, l10n);
    final statusColor = _statusColor(payment.status);
    final noteText = (payment.note ?? '').trim().isEmpty ? l10n.notAvailable : payment.note!.trim();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        l10n.paymentDetails,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: () => Navigator.of(sheetContext).pop(),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                _detailRow(l10n.amount, amountText),
                _detailRow(l10n.paymentDate, dateText),
                _detailRow(l10n.paymentType, typeText),
                _detailRow(l10n.status, statusText, valueColor: statusColor),
                _detailRow(l10n.contractId, payment.contractId.toString()),
                _detailRow(l10n.paymentId, payment.id.toString()),
                _detailRow(l10n.note, noteText),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _detailRow(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[600], fontSize: 12),
            ),
          ),
          Expanded(
            flex: 6,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: valueColor ?? Colors.black,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text(l10n.paymentHistory),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _fetchPayments();
              });
            },
          ),
        ],
      ),
      body: FutureBuilder<List<Payment>>(
        future: _paymentsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          if (snapshot.hasError) {
            return Center(
              child: Text(
                '${l10n.error}: ${snapshot.error}',
                style: const TextStyle(color: Colors.red),
              ),
            );
          }

          final payments = snapshot.data ?? [];
          if (payments.isEmpty) {
            return Center(
              child: Text(l10n.noPayments),
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemBuilder: (context, index) {
              final payment = payments[index];
              final amountText = '${payment.amount.toStringAsFixed(2)} DZD';
              final dateText = _formatPaidDate(context, payment.paidDate);
              final typeText = payment.paymentType.isEmpty
                  ? l10n.notAvailable
                  : payment.paymentType;
              final statusText = _statusLabel(payment.status, l10n);
              final statusColor = _statusColor(payment.status);

              return Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _showPaymentDetails(context, payment),
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.grey[100]!),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(Icons.payments_outlined, color: AppColors.primary),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                amountText,
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '$dateText - $typeText',
                                style: TextStyle(color: Colors.grey[600], fontSize: 12),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          statusText,
                          style: TextStyle(
                            color: statusColor,
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemCount: payments.length,
          );
        },
      ),
    );
  }
}
