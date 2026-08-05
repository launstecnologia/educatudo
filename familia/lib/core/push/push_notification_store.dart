import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'push_notification_record.dart';

final pushNotificationStoreProvider = Provider<PushNotificationStore>(
  (_) => const PushNotificationStore(),
);

class PushNotificationStore {
  const PushNotificationStore();

  static const _key = 'educatudo_push_history_v1';
  static const _maxItems = 200;

  Future<List<PushNotificationRecord>> readAll() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return [];
      final items = decoded
          .whereType<Map>()
          .map(
            (item) => PushNotificationRecord.fromJson(
              item.map((key, value) => MapEntry('$key', value)),
            ),
          )
          .toList();
      items.sort((a, b) => b.receivedAt.compareTo(a.receivedAt));
      return items;
    } catch (_) {
      return [];
    }
  }

  Future<void> save(PushNotificationRecord item) async {
    await merge([item]);
  }

  Future<List<PushNotificationRecord>> merge(
    Iterable<PushNotificationRecord> incoming,
  ) async {
    final byId = <String, PushNotificationRecord>{
      for (final item in await readAll()) item.id: item,
    };
    for (final item in incoming) {
      final previous = byId[item.id];
      byId[item.id] = item.copyWith(
        read: item.read || (previous?.read ?? false),
      );
    }
    final result = byId.values.toList()
      ..sort((a, b) => b.receivedAt.compareTo(a.receivedAt));
    if (result.length > _maxItems) result.removeRange(_maxItems, result.length);
    await _write(result);
    return result;
  }

  Future<List<PushNotificationRecord>> markRead(String id) async {
    final items = await readAll();
    final updated = [
      for (final item in items)
        item.id == id ? item.copyWith(read: true) : item,
    ];
    await _write(updated);
    return updated;
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  Future<void> _write(List<PushNotificationRecord> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _key,
      jsonEncode(items.map((item) => item.toJson()).toList()),
    );
  }
}
