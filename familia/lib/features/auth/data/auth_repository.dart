import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/parent.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.read(dioProvider)),
);

class LoginResult {
  const LoginResult({required this.token, required this.parent});

  final String token;
  final Parent parent;
}

class AuthRepository {
  const AuthRepository(this._dio);
  final Dio _dio;

  Future<LoginResult> login(String cpf, String password) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: {'cpf': cpf.replaceAll(RegExp(r'\D'), ''), 'password': password},
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      return LoginResult(
        token: data['access_token'] as String,
        parent: Parent.fromJson(data['parent'] as Map<String, dynamic>),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  Future<Parent> me() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('/me');
      return Parent.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}
