import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/school_communication.dart';

final schoolCommunicationRepositoryProvider = Provider(
  (ref) => SchoolCommunicationRepository(ref.read(dioProvider)),
);
final schoolCommunicationsProvider =
    FutureProvider.family<List<SchoolCommunication>, int>(
      (ref, studentId) =>
          ref.read(schoolCommunicationRepositoryProvider).list(studentId),
    );
final schoolCommunicationProvider =
    FutureProvider.family<
      SchoolCommunication,
      ({int studentId, int communicationId})
    >(
      (ref, args) => ref
          .read(schoolCommunicationRepositoryProvider)
          .get(args.studentId, args.communicationId),
    );

class SchoolCommunicationRepository {
  const SchoolCommunicationRepository(this._dio);
  final Dio _dio;

  Future<List<SchoolCommunication>> list(int studentId) async {
    final response = await _dio.get<dynamic>(
      '/students/$studentId/school-communications',
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    return raw is List
        ? raw.whereType<Map>().map((item) {
            return SchoolCommunication.fromJson(
              item.map((key, value) => MapEntry('$key', value)),
            );
          }).toList()
        : [];
  }

  Future<SchoolCommunication> get(int studentId, int id) async {
    final response = await _dio.get<dynamic>(
      '/students/$studentId/school-communications/$id',
    );
    final raw = response.data is Map ? response.data['data'] : response.data;
    await _dio.post<dynamic>(
      '/students/$studentId/school-communications/$id/read',
    );
    return SchoolCommunication.fromJson(
      (raw as Map).map((key, value) => MapEntry('$key', value)),
    );
  }

  Future<void> reply(int studentId, int id, String message) async {
    await _dio.post<dynamic>(
      '/students/$studentId/school-communications/$id/replies',
      data: {'message': message},
    );
  }
}
