import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_list_body.dart';
import '../data/journeys_repository.dart';
import '../domain/journey.dart';

class JourneysPage extends ConsumerWidget {
  const JourneysPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final journeys = ref.watch(journeysProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Jornadas')),
      body: journeys.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => AsyncErrorBody(
          message: 'Não foi possível carregar as jornadas.',
          onRetry: () => ref.invalidate(journeysProvider(studentId)),
        ),
        data: (items) => AsyncListBody<Journey>(
          items: items,
          onRefresh: () => ref.refresh(journeysProvider(studentId).future),
          emptyIcon: Icons.route_outlined,
          emptyMessage: 'Nenhuma jornada disponível para esta turma.',
          itemBuilder: (context, journey) => Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    journey.title,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  if (journey.subjectName?.isNotEmpty == true ||
                      journey.teacherName?.isNotEmpty == true) ...[
                    const SizedBox(height: 4),
                    Text(
                      [
                        journey.subjectName,
                        journey.teacherName,
                      ].whereType<String>().join(' · '),
                    ),
                  ],
                  const SizedBox(height: 12),
                  LinearProgressIndicator(
                    value: (journey.progressPercent / 100).clamp(0, 1),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    journey.totalModules > 0
                        ? '${journey.completedModules}/${journey.totalModules} módulos · ${journey.progressPercent.toStringAsFixed(0)}% concluído'
                        : (journey.completed
                              ? 'Concluída'
                              : journey.started
                              ? 'Em andamento'
                              : 'Não iniciada'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
