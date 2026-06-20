import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/push/push_notification_record.dart';
import '../data/notifications_repository.dart';

class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_sync);
  }

  Future<void> _sync() async {
    await ref.read(notificationsRepositoryProvider).sync();
    if (mounted) ref.invalidate(notificationHistoryProvider);
  }

  Future<void> _open(PushNotificationRecord item) async {
    await ref.read(notificationsRepositoryProvider).markRead(item.id);
    ref.invalidate(notificationHistoryProvider);
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(item.title),
        content: SingleChildScrollView(child: Text(item.body)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fechar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final history = ref.watch(notificationHistoryProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Notificacoes')),
      body: RefreshIndicator(
        onRefresh: _sync,
        child: history.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(24),
            children: const [
              Icon(Icons.notifications_off_outlined, size: 52),
              SizedBox(height: 12),
              Text(
                'Nao foi possivel abrir o historico.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
          data: (items) => items.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.notifications_none, size: 52),
                    SizedBox(height: 12),
                    Text(
                      'As notificacoes recebidas ficarao salvas aqui.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, index) {
                    final item = items[index];
                    return ListTile(
                      leading: CircleAvatar(
                        child: Icon(
                          item.read
                              ? Icons.notifications_none
                              : Icons.notifications_active,
                        ),
                      ),
                      title: Text(
                        item.title,
                        style: TextStyle(
                          fontWeight: item.read
                              ? FontWeight.normal
                              : FontWeight.w700,
                        ),
                      ),
                      subtitle: Text(
                        '${item.body}\n${DateFormat('dd/MM/yyyy HH:mm').format(item.receivedAt.toLocal())}',
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                      isThreeLine: true,
                      onTap: () => _open(item),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
