import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/school_notice.dart';

final noticesRepositoryProvider = Provider<NoticesRepository>(
  (ref) => NoticesRepository(ref.read(dioProvider)),
);

final noticesProvider = FutureProvider.family<List<SchoolNotice>, int>(
  (ref, studentId) => ref.read(noticesRepositoryProvider).list(studentId),
);

class NoticesRepository {
  const NoticesRepository(this._dio);

  final Dio _dio;

  Future<List<SchoolNotice>> list(int studentId) async {
    try {
      final response = await _dio.get<dynamic>('/students/$studentId/notices');
      final payload = response.data;
      final raw = payload is Map ? payload['data'] : payload;
      if (raw is! List) return [];
      return raw
          .whereType<Map>()
          .map(
            (item) => SchoolNotice.fromJson(
              item.map((key, value) => MapEntry('$key', value)),
            ),
          )
          .toList();
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
