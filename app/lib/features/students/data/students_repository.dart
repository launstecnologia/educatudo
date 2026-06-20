import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/student.dart';

final studentsRepositoryProvider = Provider<StudentsRepository>(
  (ref) => StudentsRepository(ref.read(dioProvider)),
);

final studentsProvider = FutureProvider<List<Student>>((ref) async {
  return ref.read(studentsRepositoryProvider).list();
});

class StudentsRepository {
  const StudentsRepository(this._dio);
  final Dio _dio;

  Future<List<Student>> list() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('/students');
      final data = response.data!['data'] as List<dynamic>;
      return data
          .map((item) => Student.fromJson(item as Map<String, dynamic>))
          .toList(growable: false);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
