import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../domain/access_event.dart';

final attendanceRepositoryProvider = Provider(
  (ref) => AttendanceRepository(ref.read(dioProvider)),
);
final accessHistoryProvider = FutureProvider.family<AccessHistory, int>(
  (ref, id) => ref.read(attendanceRepositoryProvider).list(id),
);

class AttendanceRepository {
  const AttendanceRepository(this._dio);
  final Dio _dio;
  Future<AccessHistory> list(int studentId) async {
    final response = await _dio.get<dynamic>(
      '/students/$studentId/access-events',
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    return AccessHistory.fromJson(
      (raw as Map).map((k, v) => MapEntry('$k', v)),
    );
  }
}
