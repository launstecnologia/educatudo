import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/async_list_body.dart';
import '../data/writing_repository.dart';
import '../domain/writing_journey.dart';

class WritingPage extends ConsumerWidget {
  const WritingPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Redação'),
        bottom: const TabBar(
          tabs: [
            Tab(text: 'Jornadas'),
            Tab(text: 'Redações'),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          _WritingJourneysTab(studentId: studentId),
          _EssaysTab(studentId: studentId),
        ],
      ),
    ),
  );
}

class _WritingJourneysTab extends ConsumerWidget {
  const _WritingJourneysTab({required this.studentId});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final value = ref.watch(writingJourneysProvider(studentId));
    return value.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, _) => AsyncErrorBody(
        message: 'Não foi possível carregar as jornadas de redação.',
        onRetry: () => ref.invalidate(writingJourneysProvider(studentId)),
      ),
      data: (items) => AsyncListBody<WritingJourney>(
        items: items,
        onRefresh: () => ref.refresh(writingJourneysProvider(studentId).future),
        emptyIcon: Icons.edit_note_outlined,
        emptyMessage: 'Nenhuma jornada de redação disponível.',
        itemBuilder: (context, item) => Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.theme,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 4),
                Text(item.journeyTitle),
                if (item.description?.isNotEmpty ?? false) ...[
                  const SizedBox(height: 8),
                  Text(
                    item.description!,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  children: [
                    if (item.submissionStatus != null)
                      Chip(label: Text(item.submissionStatus!)),
                    if (item.dueAt != null)
                      Chip(
                        label: Text(
                          'Até ${DateFormat('dd/MM/yyyy').format(item.dueAt!.toLocal())}',
                        ),
                      ),
                    if (item.grade != null)
                      Chip(
                        label: Text('Nota ${item.grade!.toStringAsFixed(1)}'),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EssaysTab extends ConsumerWidget {
  const _EssaysTab({required this.studentId});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final value = ref.watch(essaysProvider(studentId));
    return value.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, _) => AsyncErrorBody(
        message: 'Não foi possível carregar as redações.',
        onRetry: () => ref.invalidate(essaysProvider(studentId)),
      ),
      data: (items) => AsyncListBody<Essay>(
        items: items,
        onRefresh: () => ref.refresh(essaysProvider(studentId).future),
        emptyIcon: Icons.description_outlined,
        emptyMessage: 'Nenhuma redação encontrada para este aluno.',
        itemBuilder: (context, item) => Card(
          child: ExpansionTile(
            tilePadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 6,
            ),
            title: Text(item.theme),
            subtitle: Text(
              item.isDraft
                  ? 'Rascunho'
                  : item.grade == null
                  ? 'Aguardando correção'
                  : 'Nota ${item.grade!.toStringAsFixed(1)}',
            ),
            childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            expandedCrossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (item.feedback?.isNotEmpty ?? false) ...[
                Text('Feedback', style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 6),
                Text(item.feedback!),
              ] else
                const Text('Ainda não há feedback disponível.'),
            ],
          ),
        ),
      ),
    );
  }
}
