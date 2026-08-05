import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/writing_journey.dart';

final writingRepositoryProvider = Provider<WritingRepository>(
  (ref) => WritingRepository(ref.read(dioProvider)),
);
final writingJourneysProvider =
    FutureProvider.family<List<WritingJourney>, int>(
      (ref, studentId) =>
          ref.read(writingRepositoryProvider).journeys(studentId),
    );
final essaysProvider = FutureProvider.family<List<Essay>, int>(
  (ref, studentId) => ref.read(writingRepositoryProvider).essays(studentId),
);

class WritingRepository {
  const WritingRepository(this._dio);
  final Dio _dio;

  Future<List<WritingJourney>> journeys(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/writing-journeys',
      );
      final data = response.data!['data'] as List<dynamic>? ?? const [];
      return data
          .map((item) => WritingJourney.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  Future<List<Essay>> essays(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/essays',
      );
      final data = response.data!['data'] as List<dynamic>? ?? const [];
      return data
          .map((item) => Essay.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
