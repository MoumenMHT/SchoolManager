import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:schoolhub_parent/l10n/app_localizations.dart';
import '../../services/payment_service.dart';
import '../../services/api_service.dart';
import '../../models/payment.dart';
import 'payment_history_screen.dart';
import '../../theme/app_colors.dart';

class PaymentDashboardScreen extends StatefulWidget {
  const PaymentDashboardScreen({super.key});

  @override
  State<PaymentDashboardScreen> createState() => _PaymentDashboardScreenState();
}

class _PaymentDashboardScreenState extends State<PaymentDashboardScreen> {
  late Future<List<ContractSummary>> _dashboardFuture;

  String _formatDueDate(BuildContext context, String? value) {
    final l10n = AppLocalizations.of(context)!;
    if (value == null || value.isEmpty) return l10n.notAvailable;

    final parsed = DateTime.tryParse(value);
    if (parsed == null) return l10n.notAvailable;

    final locale = Localizations.localeOf(context).toString();
    return DateFormat.yMMMd(locale).format(parsed);
  }

  @override
  void initState() {
    super.initState();
    _fetchDashboard();
  }

  void _fetchDashboard() {
    final paymentService = PaymentService(context.read<ApiService>());
    _dashboardFuture = paymentService.getPaymentDashboard();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text(l10n.payments),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _fetchDashboard();
              });
            },
          )
        ],
      ),
      body: FutureBuilder<List<ContractSummary>>(
        future: _dashboardFuture,
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

          final contracts = snapshot.data ?? [];
          if (contracts.isEmpty) {
            return Center(
              child: Text(l10n.noContracts),
            );
          }

          // Calculate global totals
          double totalRemaining = 0;
          double totalPaid = 0;
          for (var c in contracts) {
            totalRemaining += c.remainingAmount;
            totalPaid += c.paidAmount;
          }

          return CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildGlobalSummary(totalPaid, totalRemaining, context),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16.0),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final contract = contracts[index];
                      return _buildContractCard(contract, context);
                    },
                    childCount: contracts.length,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildGlobalSummary(double totalPaid, double totalRemaining, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: AppColors.headerGradient,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.3),
            blurRadius: 15,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.totalOutstandingBalance,
            style: const TextStyle(color: Colors.white70, fontSize: 14),
          ),
          const SizedBox(height: 8),
          Text(
            '${totalRemaining.toStringAsFixed(2)} DZD',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l10n.totalPaid,
                        style: const TextStyle(color: Colors.white70, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text('${totalPaid.toStringAsFixed(2)} DZD',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Flexible(
                child: InkWell(
                  borderRadius: BorderRadius.circular(20),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const PaymentHistoryScreen(),
                      ),
                    );
                  },
                  child: Ink(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.history, color: Colors.white, size: 16),
                        const SizedBox(width: 4),
                        Flexible(
                          child: Text(
                            l10n.paymentHistory,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildContractCard(ContractSummary contract, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final bool hasDue = contract.unpaidBillsCount > 0 || contract.lateBillsCount > 0;
    final int percentPaid = (contract.paymentProgress * 100).toInt();
    final String formattedDueDate = _formatDueDate(context, contract.nextDueDate);
    
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
        border: Border.all(color: Colors.grey[100]!),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${l10n.contractNumber}: ${contract.contractNumber}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: hasDue ? AppColors.error.withValues(alpha: 0.1) : AppColors.success.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    hasDue ? l10n.paymentDue : l10n.upToDate,
                    style: TextStyle(
                      color: hasDue ? AppColors.error : AppColors.success,
                      fontWeight: FontWeight.bold,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              '${l10n.academicYear}: ${contract.academicYear}',
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
            const SizedBox(height: 16),
            
            // Progress bar
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('$percentPaid% ${l10n.paid}',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                Text('${contract.paidAmount.toStringAsFixed(0)} / ${contract.totalAmount.toStringAsFixed(0)} DZD',
                    style: TextStyle(fontSize: 12, color: Colors.grey[600])),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: contract.paymentProgress,
                minHeight: 8,
                backgroundColor: Colors.grey[200],
                valueColor: AlwaysStoppedAnimation<Color>(
                  contract.paymentProgress == 1.0 ? AppColors.success : AppColors.primary,
                ),
              ),
            ),
            
            const SizedBox(height: 16),
            const Divider(),
            const SizedBox(height: 8),
            
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l10n.nextDue, style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                      const SizedBox(height: 4),
                      Text(
                        formattedDueDate,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          color: hasDue ? AppColors.error : Colors.black,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(l10n.unpaidBills, style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(
                      '${contract.unpaidBillsCount} (${contract.lateBillsCount} ${l10n.late})',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: contract.lateBillsCount > 0 ? AppColors.error : Colors.black,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            
          ],
        ),
      ),
    );
  }
}
