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
    this.nextExam,
    this.accessStatus,
    this.recentActivity = const [],
  });

  final int totalExams;
  final int totalExercises;
  final int totalEssays;
  final double? averageGrade;
  final NextExam? nextExam;
  final HomeAccessStatus? accessStatus;
  final List<HomeActivity> recentActivity;

  factory HomeSummary.fromJson(Map<String, dynamic> json) => HomeSummary(
    totalExams: (json['total_exams'] as num? ?? 0).toInt(),
    totalExercises: (json['total_exercises'] as num? ?? 0).toInt(),
    totalEssays: (json['total_essays'] as num? ?? 0).toInt(),
    averageGrade: (json['average_grade'] as num?)?.toDouble(),
    nextExam: json['next_exam'] is Map
        ? NextExam.fromJson(
            (json['next_exam'] as Map).map((k, v) => MapEntry('$k', v)),
          )
        : null,
    accessStatus: json['access_status'] is Map
        ? HomeAccessStatus.fromJson(
            (json['access_status'] as Map).map((k, v) => MapEntry('$k', v)),
          )
        : null,
    recentActivity: json['recent_activity'] is List
        ? (json['recent_activity'] as List)
              .whereType<Map>()
              .map(
                (e) =>
                    HomeActivity.fromJson(e.map((k, v) => MapEntry('$k', v))),
              )
              .toList()
        : const [],
  );
}

class NextExam {
  const NextExam({
    required this.id,
    required this.title,
    this.subjectName,
    this.date,
  });
  final int id;
  final String title;
  final String? subjectName;
  final DateTime? date;
  factory NextExam.fromJson(Map<String, dynamic> json) => NextExam(
    id: int.tryParse('${json['id']}') ?? 0,
    title: json['title']?.toString() ?? 'Prova',
    subjectName: json['subject_name']?.toString(),
    date: DateTime.tryParse(json['date']?.toString() ?? ''),
  );
}

class HomeAccessStatus {
  const HomeAccessStatus({
    required this.isAtSchool,
    required this.kind,
    this.at,
  });
  final bool isAtSchool;
  final String kind;
  final DateTime? at;
  factory HomeAccessStatus.fromJson(Map<String, dynamic> json) =>
      HomeAccessStatus(
        isAtSchool: json['is_at_school'] == true || json['is_at_school'] == 1,
        kind: json['kind']?.toString() ?? '',
        at: DateTime.tryParse(json['at']?.toString() ?? ''),
      );
}

class HomeActivity {
  const HomeActivity({
    required this.type,
    required this.title,
    required this.route,
    this.occurredAt,
  });
  final String type, title, route;
  final DateTime? occurredAt;
  factory HomeActivity.fromJson(Map<String, dynamic> json) => HomeActivity(
    type: json['type']?.toString() ?? '',
    title: json['title']?.toString() ?? '',
    route: json['route']?.toString() ?? '',
    occurredAt: DateTime.tryParse(json['occurred_at']?.toString() ?? ''),
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
