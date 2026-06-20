import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/app_config.dart';
import '../network/api_client.dart';

final pushServiceProvider = Provider<PushService>((ref) {
  if (!AppConfig.enablePush) return const DisabledPushService();
  return FcmPushService(ref.read(dioProvider));
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
  FcmPushService(this._dio);

  static const _channel = AndroidNotificationChannel(
    'educatudo_updates',
    'Atualizacoes escolares',
    description: 'Comunicados e atualizacoes do EducaTudo.',
    importance: Importance.high,
  );
  static const _deviceIdKey = 'educatudo_device_id';

  final Dio _dio;
  final _secureStorage = const FlutterSecureStorage();
  final _notifications = FlutterLocalNotificationsPlugin();
  StreamSubscription<String>? _tokenSubscription;
  StreamSubscription<RemoteMessage>? _messageSubscription;
  bool _initialized = false;

  @override
  bool get isAvailable => _initialized;

  @override
  Future<void> initialize() async {
    if (_initialized) return;
    try {
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
    await initialize();
    if (!_initialized) return;
    final token = await FirebaseMessaging.instance.getToken();
    if (token != null) await _upsertToken(token);
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
    final notification = message.notification;
    if (notification == null) return;
    await _notifications.show(
      id: message.messageId.hashCode,
      title: notification.title ?? 'EducaTudo',
      body: notification.body ?? 'Voce tem uma nova atualizacao.',
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

  @override
  Future<void> unregister() async {
    if (!_initialized) return;
    try {
      await _dio.delete<void>('/devices/${await _deviceId()}');
    } catch (_) {
      // Logout local nunca deve ser impedido por falha de rede.
    }
    await _tokenSubscription?.cancel();
    await _messageSubscription?.cancel();
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
