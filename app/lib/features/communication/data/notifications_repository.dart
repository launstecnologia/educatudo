import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/push/push_notification_record.dart';
import '../../../core/push/push_notification_store.dart';

final notificationsRepositoryProvider = Provider<NotificationsRepository>(
  (ref) => NotificationsRepository(
    ref.read(dioProvider),
    ref.read(pushNotificationStoreProvider),
  ),
);

final notificationHistoryProvider =
    FutureProvider<List<PushNotificationRecord>>(
      (ref) => ref.read(pushNotificationStoreProvider).readAll(),
    );

class NotificationsRepository {
  const NotificationsRepository(this._dio, this._store);

  final Dio _dio;
  final PushNotificationStore _store;

  Future<List<PushNotificationRecord>> sync() async {
    try {
      final response = await _dio.get<dynamic>('/notifications');
      final payload = response.data;
      final raw = payload is Map ? payload['data'] : payload;
      if (raw is! List) return _store.readAll();
      final remote = raw.whereType<Map>().map((item) {
        final json = item.map((key, value) => MapEntry('$key', value));
        final id =
            json['id']?.toString() ?? json['notificacao_id']?.toString() ?? '';
        final read =
            json['is_read'] == true ||
            json['is_read'] == 1 ||
            json['read'] == true ||
            json['read'] == 1;
        return PushNotificationRecord(
          id: id,
          title:
              json['title']?.toString() ??
              json['titulo']?.toString() ??
              'EducaTudo',
          body:
              json['body']?.toString() ??
              json['message']?.toString() ??
              json['mensagem']?.toString() ??
              json['conteudo']?.toString() ??
              '',
          receivedAt:
              DateTime.tryParse(
                json['created_at']?.toString() ??
                    json['sent_at']?.toString() ??
                    '',
              ) ??
              DateTime.now(),
          route: json['route']?.toString() ?? json['url']?.toString(),
          read: read,
          data: {'notification_id': id},
        );
      });
      return _store.merge(remote);
    } on DioException catch (error) {
      // O histórico local continua disponível offline; apenas erros de API são
      // propagados quando ainda não existe nada salvo no aparelho.
      final local = await _store.readAll();
      if (local.isNotEmpty) return local;
      throw mapDioException(error);
    }
  }

  Future<void> markRead(String id) async {
    await _store.markRead(id);
    try {
      await _dio.post<void>('/notifications/$id/read');
    } on DioException catch (_) {
      // A leitura local funciona offline e será preservada no próximo merge.
    }
  }
}
