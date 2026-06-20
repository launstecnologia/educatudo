import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/journey.dart';

final journeysRepositoryProvider = Provider<JourneysRepository>(
  (ref) => JourneysRepository(ref.read(dioProvider)),
);
final journeysProvider = FutureProvider.family<List<Journey>, int>(
  (ref, studentId) => ref.read(journeysRepositoryProvider).list(studentId),
);

class JourneysRepository {
  const JourneysRepository(this._dio);
  final Dio _dio;

  Future<List<Journey>> list(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/journeys',
      );
      final data = response.data!['data'] as List<dynamic>? ?? const [];
      return data
          .map((item) => Journey.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
