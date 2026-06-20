import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/async_list_body.dart';
import '../data/lesson_plans_repository.dart';
import '../domain/lesson_plan.dart';

class LessonPlansPage extends ConsumerWidget {
  const LessonPlansPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final plans = ref.watch(lessonPlansProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Planos de aula')),
      body: plans.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => AsyncErrorBody(
          message: 'Não foi possível carregar os planos de aula.',
          onRetry: () => ref.invalidate(lessonPlansProvider(studentId)),
        ),
        data: (items) => AsyncListBody<LessonPlan>(
          items: items,
          onRefresh: () => ref.refresh(lessonPlansProvider(studentId).future),
          emptyIcon: Icons.menu_book_outlined,
          emptyMessage: 'Nenhum plano de aula disponível para esta turma.',
          itemBuilder: (context, plan) => Card(
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              leading: const CircleAvatar(
                child: Icon(Icons.menu_book_outlined),
              ),
              title: Text(plan.title),
              subtitle: Text(_subtitle(plan)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () =>
                  context.push('/students/$studentId/lesson-plans/${plan.id}'),
            ),
          ),
        ),
      ),
    );
  }

  String _subtitle(LessonPlan plan) {
    final parts = <String>[
      if (plan.subjectName != null) plan.subjectName!,
      if (plan.teacherName != null) plan.teacherName!,
      if (plan.createdAt != null)
        DateFormat('dd/MM/yyyy').format(plan.createdAt!.toLocal()),
    ];
    return parts.isEmpty ? 'Toque para visualizar' : parts.join(' · ');
  }
}
