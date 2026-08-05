import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_list_body.dart';
import '../data/lesson_plans_repository.dart';
import '../domain/lesson_plan.dart';

class LessonPlanDetailPage extends ConsumerWidget {
  const LessonPlanDetailPage({
    required this.studentId,
    required this.planId,
    super.key,
  });
  final int studentId;
  final int planId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key = (studentId: studentId, planId: planId);
    final plan = ref.watch(lessonPlanProvider(key));
    return Scaffold(
      appBar: AppBar(title: const Text('Plano de aula')),
      body: plan.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => AsyncErrorBody(
          message: 'Não foi possível carregar este plano de aula.',
          onRetry: () => ref.invalidate(lessonPlanProvider(key)),
        ),
        data: (item) => _Detail(plan: item),
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.plan});
  final LessonPlan plan;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      Text(plan.title, style: Theme.of(context).textTheme.headlineSmall),
      if (plan.subjectName != null || plan.teacherName != null) ...[
        const SizedBox(height: 8),
        Text(
          [plan.subjectName, plan.teacherName].whereType<String>().join(' · '),
        ),
      ],
      const SizedBox(height: 16),
      _Section(title: 'Objetivos', value: plan.objectives),
      _Section(title: 'Conteúdo', value: plan.content),
      _Section(title: 'Recursos', value: plan.resources),
      _Section(title: 'Avaliação', value: plan.assessment),
      _Section(title: 'Observações', value: plan.notes),
    ],
  );
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.value});
  final String title;
  final String? value;

  @override
  Widget build(BuildContext context) {
    if (value == null) return const SizedBox.shrink();
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(value!),
          ],
        ),
      ),
    );
  }
}
