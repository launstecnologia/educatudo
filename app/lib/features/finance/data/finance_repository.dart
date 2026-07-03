import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/finance_overview.dart';

final financeRepositoryProvider = Provider(
  (ref) => FinanceRepository(ref.read(dioProvider)),
);

final financeOverviewProvider = FutureProvider.family<FinanceOverview, int>(
  (ref, studentId) => ref.read(financeRepositoryProvider).overview(studentId),
);

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
}
