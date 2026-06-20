import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';

final homeRepositoryProvider = Provider<HomeRepository>(
  (ref) => HomeRepository(ref.read(dioProvider)),
);

final homeSummaryProvider = FutureProvider.family<HomeSummary, int>(
  (ref, studentId) => ref.read(homeRepositoryProvider).load(studentId),
);

class HomeSummary {
  const HomeSummary({
    required this.totalExams,
    required this.totalExercises,
    required this.totalEssays,
    this.averageGrade,
  });

  final int totalExams;
  final int totalExercises;
  final int totalEssays;
  final double? averageGrade;

  factory HomeSummary.fromJson(Map<String, dynamic> json) => HomeSummary(
    totalExams: (json['total_exams'] as num? ?? 0).toInt(),
    totalExercises: (json['total_exercises'] as num? ?? 0).toInt(),
    totalEssays: (json['total_essays'] as num? ?? 0).toInt(),
    averageGrade: (json['average_grade'] as num?)?.toDouble(),
  );
}

class HomeRepository {
  const HomeRepository(this._dio);
  final Dio _dio;

  Future<HomeSummary> load(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/students/$studentId/dashboard',
      );
      return HomeSummary.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
