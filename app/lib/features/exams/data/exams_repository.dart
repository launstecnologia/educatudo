import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/exam.dart';

final examsRepositoryProvider = Provider<ExamsRepository>(
  (ref) => ExamsRepository(ref.read(dioProvider)),
);

final examsProvider = FutureProvider.family<List<Exam>, int>(
  (ref, studentId) => ref.read(examsRepositoryProvider).list(studentId),
);

class ExamsRepository {
  const ExamsRepository(this._dio);
  final Dio _dio;

  Future<List<Exam>> list(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/exams',
      );
      final data = response.data!['data'] as List<dynamic>? ?? const [];
      return data
          .map((item) => Exam.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
