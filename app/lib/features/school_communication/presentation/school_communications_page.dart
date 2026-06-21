import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../data/school_communication_repository.dart';

class SchoolCommunicationsPage extends ConsumerWidget {
  const SchoolCommunicationsPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(schoolCommunicationsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Comunicação escolar')),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.refresh(schoolCommunicationsProvider(studentId).future),
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.cloud_off_outlined, size: 52),
              const Text(
                'Não foi possível carregar as comunicações.',
                textAlign: TextAlign.center,
              ),
              FilledButton.tonal(
                onPressed: () =>
                    ref.invalidate(schoolCommunicationsProvider(studentId)),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (items) => items.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.mark_email_read_outlined, size: 52),
                    Text(
                      'Nenhuma comunicação da escola.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (_, index) {
                    final item = items[index];
                    final color = item.priority == 'urgente'
                        ? Colors.red
                        : item.priority == 'importante'
                        ? Colors.orange
                        : Theme.of(context).colorScheme.primary;
                    return Card(
                      child: ListTile(
                        onTap: () => context.push(
                          '/students/$studentId/school-communications/${item.id}',
                        ),
                        leading: Icon(
                          item.isRead
                              ? Icons.drafts_outlined
                              : Icons.mark_email_unread_outlined,
                          color: color,
                          size: 30,
                        ),
                        title: Text(
                          item.title,
                          style: TextStyle(
                            fontWeight: item.isRead
                                ? FontWeight.w600
                                : FontWeight.w800,
                          ),
                        ),
                        subtitle: Text(
                          '${item.priority.toUpperCase()}${item.publishedAt == null ? '' : ' · ${DateFormat('dd/MM HH:mm').format(item.publishedAt!.toLocal())}'}\n${item.content}',
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                        isThreeLine: true,
                        trailing: const Icon(Icons.chevron_right),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
