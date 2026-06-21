import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../data/school_calendar_repository.dart';
import '../domain/school_event.dart';

class SchoolCalendarPage extends ConsumerWidget {
  const SchoolCalendarPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(schoolEventsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Calendário escolar')),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(schoolEventsProvider(studentId).future),
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.event_busy_outlined, size: 52),
              const Text(
                'Não foi possível carregar o calendário.',
                textAlign: TextAlign.center,
              ),
              FilledButton.tonal(
                onPressed: () =>
                    ref.invalidate(schoolEventsProvider(studentId)),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (events) => events.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.event_available_outlined, size: 52),
                    Text(
                      'Nenhum evento programado.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: events.length,
                  itemBuilder: (_, index) {
                    final event = events[index];
                    final newMonth =
                        index == 0 ||
                        events[index - 1].startsAt.month !=
                            event.startsAt.month;
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (newMonth)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
                            child: Text(
                              _monthLabel(event.startsAt),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                          ),
                        _EventCard(
                          event: event,
                          onTap: () =>
                              _openEvent(context, ref, studentId, event),
                        ),
                      ],
                    );
                  },
                ),
        ),
      ),
    );
  }

  Future<void> _openEvent(
    BuildContext context,
    WidgetRef ref,
    int studentId,
    SchoolEvent event,
  ) async {
    await ref.read(schoolCalendarRepositoryProvider).read(studentId, event.id);
    if (!context.mounted) return;
    await showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(event.title),
        content: Text(
          [
            if (event.description?.isNotEmpty == true) event.description!,
            if (event.location?.isNotEmpty == true) 'Local: ${event.location}',
            DateFormat('dd/MM/yyyy HH:mm').format(event.startsAt.toLocal()),
            if (event.status == 'cancelado') 'EVENTO CANCELADO',
          ].join('\n\n'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fechar'),
          ),
        ],
      ),
    );
    ref.invalidate(schoolEventsProvider(studentId));
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.onTap});
  final SchoolEvent event;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final canceled = event.status == 'cancelado';
    return Card(
      color: canceled ? Colors.red.shade50 : null,
      child: ListTile(
        onTap: onTap,
        leading: Container(
          width: 52,
          padding: const EdgeInsets.symmetric(vertical: 6),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            children: [
              Text(
                '${event.startsAt.day}',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                ),
              ),
              Text(
                _months[event.startsAt.month - 1].substring(0, 3).toUpperCase(),
                style: const TextStyle(fontSize: 10),
              ),
            ],
          ),
        ),
        title: Text(
          event.title,
          style: TextStyle(
            fontWeight: event.isRead ? FontWeight.w600 : FontWeight.w800,
            decoration: canceled ? TextDecoration.lineThrough : null,
          ),
        ),
        subtitle: Text(
          '${event.category}${event.location?.isNotEmpty == true ? ' · ${event.location}' : ''}${canceled ? ' · CANCELADO' : ''}',
        ),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

const _months = [
  'Janeiro',
  'Fevereiro',
  'Março',
  'Abril',
  'Maio',
  'Junho',
  'Julho',
  'Agosto',
  'Setembro',
  'Outubro',
  'Novembro',
  'Dezembro',
];

String _monthLabel(DateTime date) =>
    '${_months[date.month - 1].toUpperCase()} ${date.year}';
