import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../domain/report_card.dart';

final reportCardRepositoryProvider = Provider(
  (ref) => ReportCardRepository(ref.read(dioProvider)),
);
final reportCardsProvider = FutureProvider.family<List<ReportCard>, int>(
  (ref, id) => ref.read(reportCardRepositoryProvider).list(id),
);

class ReportCardRepository {
  const ReportCardRepository(this._dio);
  final Dio _dio;
  Future<List<ReportCard>> list(int studentId) async {
    final response = await _dio.get<dynamic>(
      '/students/$studentId/report-card',
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    return raw is List
        ? raw
              .whereType<Map>()
              .map(
                (e) => ReportCard.fromJson(e.map((k, v) => MapEntry('$k', v))),
              )
              .toList()
        : [];
  }
}
