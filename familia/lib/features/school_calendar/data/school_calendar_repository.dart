import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../domain/school_event.dart';

final schoolCalendarRepositoryProvider = Provider(
  (ref) => SchoolCalendarRepository(ref.read(dioProvider)),
);
final schoolEventsProvider = FutureProvider.family<List<SchoolEvent>, int>(
  (ref, id) => ref.read(schoolCalendarRepositoryProvider).list(id),
);

class SchoolCalendarRepository {
  const SchoolCalendarRepository(this._dio);
  final Dio _dio;
  Future<List<SchoolEvent>> list(int studentId) async {
    final now = DateTime.now(), to = DateTime(now.year + 1, now.month, 0);
    final response = await _dio.get<dynamic>(
      '/students/$studentId/calendar-events',
      queryParameters: {
        'from':
            '${now.year}-${(now.month - 1).clamp(1, 12).toString().padLeft(2, '0')}-01',
        'to':
            '${to.year}-${to.month.toString().padLeft(2, '0')}-${to.day.toString().padLeft(2, '0')}',
      },
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    return raw is List
        ? raw
              .whereType<Map>()
              .map(
                (e) => SchoolEvent.fromJson(e.map((k, v) => MapEntry('$k', v))),
              )
              .toList()
        : [];
  }

  Future<void> read(int studentId, int eventId) =>
      _dio.post<dynamic>('/students/$studentId/calendar-events/$eventId/read');
}
