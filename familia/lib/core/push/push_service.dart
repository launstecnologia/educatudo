import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/app_config.dart';
import '../network/api_client.dart';
import 'push_notification_record.dart';
import 'push_notification_store.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp();
    await const PushNotificationStore().save(_recordFromMessage(message));
  } catch (_) {
    // Nunca deixa uma falha de cache interromper o tratamento do push pelo SO.
  }
}

PushNotificationRecord _recordFromMessage(RemoteMessage message) {
  final data = message.data.map((key, value) => MapEntry(key, '$value'));
  final rawDate = data['created_at'] ?? data['sent_at'];
  return PushNotificationRecord(
    id:
        data['notification_id'] ??
        message.messageId ??
        '${message.sentTime?.millisecondsSinceEpoch ?? DateTime.now().millisecondsSinceEpoch}',
    title: message.notification?.title ?? data['title'] ?? 'EducaTudo',
    body:
        message.notification?.body ??
        data['body'] ??
        data['message'] ??
        'Voce tem uma nova atualizacao.',
    receivedAt:
        DateTime.tryParse(rawDate ?? '') ?? message.sentTime ?? DateTime.now(),
    route: data['route'],
    data: data,
  );
}

final pushServiceProvider = Provider<PushService>((ref) {
  if (!AppConfig.enablePush) return const DisabledPushService();
  return FcmPushService(
    ref.read(dioProvider),
    ref.read(pushNotificationStoreProvider),
  );
});

abstract interface class PushService {
  bool get isAvailable;
  Future<void> initialize();
  Future<void> registerForCurrentSession();
  Future<void> unregister();
}

class DisabledPushService implements PushService {
  const DisabledPushService();

  @override
  bool get isAvailable => false;
  @override
  Future<void> initialize() async {}
  @override
  Future<void> registerForCurrentSession() async {}
  @override
  Future<void> unregister() async {}
}

class FcmPushService implements PushService {
  FcmPushService(this._dio, this._store);

  static const _channel = AndroidNotificationChannel(
    'educatudo_updates',
    'Atualizacoes escolares',
    description: 'Comunicados e atualizacoes do EducaTudo.',
    importance: Importance.high,
  );
  static const _deviceIdKey = 'educatudo_device_id';

  final Dio _dio;
  final PushNotificationStore _store;
  final _secureStorage = const FlutterSecureStorage();
  final _notifications = FlutterLocalNotificationsPlugin();
  StreamSubscription<String>? _tokenSubscription;
  StreamSubscription<RemoteMessage>? _messageSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  bool _initialized = false;

  @override
  bool get isAvailable => _initialized;

  @override
  Future<void> initialize() async {
    if (_initialized) return;
    if (kIsWeb) return;
    try {
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      await Firebase.initializeApp();
      const settings = InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      );
      await _notifications.initialize(settings: settings);
      await _notifications
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >()
          ?.createNotificationChannel(_channel);
      await FirebaseMessaging.instance.requestPermission();
      _messageSubscription = FirebaseMessaging.onMessage.listen(_showMessage);
      _openedSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
        _saveMessage,
      );
      final initialMessage = await FirebaseMessaging.instance
          .getInitialMessage();
      if (initialMessage != null) await _saveMessage(initialMessage);
      _tokenSubscription = FirebaseMessaging.instance.onTokenRefresh.listen(
        _upsertToken,
      );
      _initialized = true;
    } catch (_) {
      // Credenciais Firebase ausentes ou invalidas: push fica desabilitado e
      // nao impede login nem uso das demais funcionalidades.
      _initialized = false;
    }
  }

  @override
  Future<void> registerForCurrentSession() async {
    try {
      await initialize();
      if (!_initialized) return;
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _upsertToken(token);
    } catch (_) {
      // Push e best-effort e nunca pode impedir uma autenticacao valida.
    }
  }

  Future<void> _upsertToken(String token) async {
    try {
      await _dio.put<void>(
        '/devices/${await _deviceId()}',
        data: {
          'fcm_token': token,
          'platform': 'android',
          'app_version': '1.0.0',
        },
      );
    } catch (_) {
      // O refresh sera repetido no proximo login ou evento de renovacao FCM.
    }
  }

  Future<void> _showMessage(RemoteMessage message) async {
    await _saveMessage(message);
    final notification = message.notification;
    final title = notification?.title ?? message.data['title']?.toString();
    final body =
        notification?.body ??
        message.data['body']?.toString() ??
        message.data['message']?.toString();
    if (title == null && body == null) return;
    await _notifications.show(
      id: message.messageId.hashCode,
      title: title ?? 'EducaTudo',
      body: body ?? 'Voce tem uma nova atualizacao.',
      notificationDetails: const NotificationDetails(
        android: AndroidNotificationDetails(
          'educatudo_updates',
          'Atualizacoes escolares',
          channelDescription: 'Comunicados e atualizacoes do EducaTudo.',
          importance: Importance.high,
          priority: Priority.high,
        ),
      ),
      payload: message.data['route'] as String?,
    );
  }

  Future<void> _saveMessage(RemoteMessage message) =>
      _store.save(_recordFromMessage(message));

  @override
  Future<void> unregister() async {
    if (_initialized) {
      try {
        await _dio.delete<void>('/devices/${await _deviceId()}');
      } catch (_) {
        // Logout local nunca deve ser impedido por falha de rede.
      }
      await _tokenSubscription?.cancel();
      await _messageSubscription?.cancel();
      await _openedSubscription?.cancel();
      _tokenSubscription = null;
      _messageSubscription = null;
      _openedSubscription = null;
      _initialized = false;
    }
    // Evita que outro responsável que use o mesmo aparelho veja o histórico
    // local da sessão anterior.
    await _store.clear();
  }

  Future<String> _deviceId() async {
    final existing = await _secureStorage.read(key: _deviceIdKey);
    if (existing != null) return existing;
    final random = Random.secure();
    final value = List.generate(
      16,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
    await _secureStorage.write(key: _deviceIdKey, value: value);
    return value;
  }
}
