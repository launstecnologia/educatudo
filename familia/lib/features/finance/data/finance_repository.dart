import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/finance_overview.dart';
import '../domain/finance_payment.dart';

final financeRepositoryProvider = Provider(
  (ref) => FinanceRepository(ref.read(dioProvider)),
);

final financeOverviewProvider = FutureProvider.family<FinanceOverview, int>(
  (ref, studentId) => ref.read(financeRepositoryProvider).overview(studentId),
);

final financePaymentProvider =
    FutureProvider.family<FinancePayment, FinancePaymentRequest>(
      (ref, request) => ref.read(financeRepositoryProvider).payment(request),
    );

class FinancePaymentRequest {
  const FinancePaymentRequest({
    required this.studentId,
    required this.source,
    required this.invoiceId,
  });

  final int studentId;
  final String source;
  final int invoiceId;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is FinancePaymentRequest &&
          runtimeType == other.runtimeType &&
          studentId == other.studentId &&
          source == other.source &&
          invoiceId == other.invoiceId;

  @override
  int get hashCode => Object.hash(studentId, source, invoiceId);
}

class FinanceRepository {
  const FinanceRepository(this._dio);
  final Dio _dio;

  Future<FinanceOverview> overview(int studentId) async {
    Response<dynamic> response;
    try {
      response = await _dio.get<dynamic>('/students/$studentId/finance');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return const FinanceOverview(
          summary: FinanceSummary(
            pendingCount: 0,
            pendingAmount: 0,
            overdueAmount: 0,
            contractsCount: 0,
          ),
          invoices: [],
          contracts: [],
          enrollments: [],
        );
      }
      rethrow;
    }
    final raw = response.data is Map ? response.data['data'] : response.data;
    return FinanceOverview.fromJson(
      raw is Map ? raw.map((k, v) => MapEntry('$k', v)) : <String, dynamic>{},
    );
  }

  Future<FinancePayment> payment(FinancePaymentRequest request) async {
    final response = await _dio.get<dynamic>(
      '/students/${request.studentId}/finance/invoices/${request.source}/${request.invoiceId}/payment',
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    return FinancePayment.fromJson(
      raw is Map ? raw.map((k, v) => MapEntry('$k', v)) : <String, dynamic>{},
    );
  }
}
