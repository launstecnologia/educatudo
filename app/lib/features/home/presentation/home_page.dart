import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/presentation/session_controller.dart';
import '../../students/presentation/selected_student_controller.dart';
import '../data/home_repository.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final parent = ref.watch(sessionControllerProvider).value;
    final student = ref.watch(selectedStudentProvider);
    if (student == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) context.go('/students');
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final summary = ref.watch(homeSummaryProvider(student.id));

    const modules = [
      (Icons.assignment_outlined, 'Provas'),
      (Icons.route_outlined, 'Jornadas'),
      (Icons.edit_note_outlined, 'Redacao'),
      (Icons.menu_book_outlined, 'Plano de aula'),
      (Icons.workspace_premium_outlined, 'Boletim'),
      (Icons.chat_bubble_outline, 'Mensagens'),
      (Icons.event_busy_outlined, 'Faltas'),
      (Icons.notifications_outlined, 'Notificacoes'),
    ];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inicio'),
        actions: [
          IconButton(
            tooltip: 'Trocar aluno',
            onPressed: () => context.go('/students'),
            icon: const Icon(Icons.switch_account_outlined),
          ),
          IconButton(
            tooltip: 'Sair',
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Ola, ${parent?.name.split(' ').first ?? 'responsavel'}!',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          Card(
            color: Theme.of(context).colorScheme.primaryContainer,
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              leading: CircleAvatar(child: Text(student.name.characters.first)),
              title: Text(student.name),
              subtitle: student.classLabel.isEmpty
                  ? const Text('Aluno selecionado')
                  : Text(student.classLabel),
            ),
          ),
          const SizedBox(height: 16),
          summary.when(
            loading: () => const LinearProgressIndicator(),
            error: (_, _) => Card(
              child: ListTile(
                leading: const Icon(Icons.sync_problem_outlined),
                title: const Text('Resumo indisponivel'),
                trailing: IconButton(
                  onPressed: () =>
                      ref.invalidate(homeSummaryProvider(student.id)),
                  icon: const Icon(Icons.refresh),
                ),
              ),
            ),
            data: (data) => Row(
              children: [
                Expanded(
                  child: _SummaryCard(
                    label: 'Media',
                    value: data.averageGrade?.toStringAsFixed(1) ?? '--',
                  ),
                ),
                Expanded(
                  child: _SummaryCard(
                    label: 'Provas',
                    value: '${data.totalExams}',
                  ),
                ),
                Expanded(
                  child: _SummaryCard(
                    label: 'Atividades',
                    value: '${data.totalExercises}',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text('Acompanhamento', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              childAspectRatio: 1.45,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
            ),
            itemCount: modules.length,
            itemBuilder: (context, index) {
              final module = modules[index];
              return Card(
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('${module.$2} em breve.')),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(module.$1),
                        const SizedBox(height: 8),
                        Text(module.$2),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      child: Column(
        children: [
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    ),
  );
}
