import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/lesson_plan.dart';

typedef LessonPlanKey = ({int studentId, int planId});

final lessonPlansRepositoryProvider = Provider<LessonPlansRepository>(
  (ref) => LessonPlansRepository(ref.read(dioProvider)),
);
final lessonPlansProvider = FutureProvider.family<List<LessonPlan>, int>(
  (ref, studentId) => ref.read(lessonPlansRepositoryProvider).list(studentId),
);
final lessonPlanProvider = FutureProvider.family<LessonPlan, LessonPlanKey>(
  (ref, key) =>
      ref.read(lessonPlansRepositoryProvider).get(key.studentId, key.planId),
);

class LessonPlansRepository {
  const LessonPlansRepository(this._dio);
  final Dio _dio;

  Future<List<LessonPlan>> list(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/lesson-plans',
      );
      final data = response.data!['data'] as List<dynamic>? ?? const [];
      return data
          .map((item) => LessonPlan.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  Future<LessonPlan> get(int studentId, int planId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/lesson-plans/$planId',
      );
      return LessonPlan.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
