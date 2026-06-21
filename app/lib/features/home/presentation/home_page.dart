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

    final modules = [
      (Icons.assignment_outlined, 'Provas', '/students/${student.id}/exams'),
      (Icons.route_outlined, 'Jornadas', '/students/${student.id}/journeys'),
      (Icons.edit_note_outlined, 'Redação', '/students/${student.id}/writing'),
      (
        Icons.menu_book_outlined,
        'Plano de aula',
        '/students/${student.id}/lesson-plans',
      ),
      (Icons.campaign_outlined, 'Recados', '/students/${student.id}/notices'),
      (
        Icons.forum_outlined,
        'Comunicação',
        '/students/${student.id}/school-communications',
      ),
      (
        Icons.calendar_month_outlined,
        'Calendário',
        '/students/${student.id}/calendar',
      ),
      (Icons.notifications_outlined, 'Notificações', '/notifications'),
    ];

    return Scaffold(
      appBar: AppBar(
        title: const Text('EducaColag'),
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
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF2857A5), Color(0xFF3B88C3)],
              ),
              borderRadius: BorderRadius.circular(24),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x302857A5),
                  blurRadius: 24,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 78,
                  height: 64,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Image.asset(
                    'assets/images/colag_logo.png',
                    fit: BoxFit.contain,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Olá, ${parent?.name.split(' ').first ?? 'responsável'}!',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(color: const Color(0xFFDCEBFF)),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        student.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        student.classLabel.isEmpty
                            ? 'Aluno selecionado'
                            : student.classLabel,
                        style: const TextStyle(color: Color(0xFFDCEBFF)),
                      ),
                    ],
                  ),
                ),
              ],
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
                const SizedBox(width: 8),
                Expanded(
                  child: _SummaryCard(
                    label: 'Provas',
                    value: '${data.totalExams}',
                  ),
                ),
                const SizedBox(width: 8),
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
                  onTap: () => context.push(module.$3),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: Theme.of(
                              context,
                            ).colorScheme.primaryContainer,
                            borderRadius: BorderRadius.circular(13),
                          ),
                          child: Icon(
                            module.$1,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                module.$2,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            const Icon(Icons.arrow_forward_rounded, size: 17),
                          ],
                        ),
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
