import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/presentation/session_controller.dart';
import '../data/students_repository.dart';
import '../domain/student.dart';
import 'selected_student_controller.dart';

class StudentSelectorPage extends ConsumerWidget {
  const StudentSelectorPage({super.key});

  void _select(BuildContext context, WidgetRef ref, Student student) {
    ref.read(selectedStudentProvider.notifier).select(student);
    context.go('/home');
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final students = ref.watch(studentsProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Selecione o aluno'),
        actions: [
          IconButton(
            tooltip: 'Sair',
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(studentsProvider.future),
        child: students.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.cloud_off_outlined, size: 56),
              const SizedBox(height: 16),
              const Text(
                'Nao foi possivel carregar os alunos.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton.tonal(
                onPressed: () => ref.invalidate(studentsProvider),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (items) => items.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.person_search_outlined, size: 56),
                    SizedBox(height: 16),
                    Text(
                      'Nenhum aluno esta vinculado a este responsavel.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final student = items[index];
                    return Card(
                      child: ListTile(
                        contentPadding: const EdgeInsets.all(12),
                        leading: CircleAvatar(
                          child: Text(student.name.characters.first),
                        ),
                        title: Text(student.name),
                        subtitle: student.classLabel.isEmpty
                            ? null
                            : Text(student.classLabel),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => _select(context, ref, student),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
