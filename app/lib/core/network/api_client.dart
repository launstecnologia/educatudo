import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 20),
      headers: const {'Accept': 'application/json'},
    ),
  );
  dio.interceptors.add(AuthInterceptor(ref.read(tokenStorageProvider)));
  return dio;
});

class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._tokens);

  final TokenStorage _tokens;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokens.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }
}

ApiException mapDioException(DioException error) {
  final body = error.response?.data;
  final errorBody = body is Map<String, dynamic> ? body['error'] : null;
  final details = errorBody is Map<String, dynamic> ? errorBody : null;
  return ApiException(
    details?['message'] as String? ??
        (error.type == DioExceptionType.connectionError
            ? 'Nao foi possivel conectar ao EducaTudo.'
            : 'Nao foi possivel concluir a solicitacao.'),
    code: details?['code'] as String?,
    statusCode: error.response?.statusCode,
  );
}
