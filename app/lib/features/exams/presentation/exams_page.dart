import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/async_list_body.dart';
import '../data/exams_repository.dart';
import '../domain/exam.dart';

class ExamsPage extends ConsumerWidget {
  const ExamsPage({required this.studentId, super.key});
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final exams = ref.watch(examsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Provas')),
      body: exams.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => AsyncErrorBody(
          message: 'Não foi possível carregar as provas.',
          onRetry: () => ref.invalidate(examsProvider(studentId)),
        ),
        data: (items) => AsyncListBody<Exam>(
          items: items,
          onRefresh: () => ref.refresh(examsProvider(studentId).future),
          emptyIcon: Icons.assignment_outlined,
          emptyMessage: 'Nenhuma prova encontrada para este aluno.',
          itemBuilder: (context, exam) => Card(
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              leading: CircleAvatar(
                child: Text(exam.grade?.toStringAsFixed(1) ?? '--'),
              ),
              title: Text(exam.title),
              subtitle: Text(
                [
                  if (exam.subjectName?.isNotEmpty == true) exam.subjectName!,
                  _subtitle(exam),
                  if (exam.questionCount > 0)
                    '${exam.correctCount} acertos · ${exam.incorrectCount} erros${exam.pendingCount > 0 ? " · ${exam.pendingCount} pendentes" : ""} · ${exam.accuracyPercent.toStringAsFixed(0)}%',
                ].join('\n'),
              ),
              isThreeLine: exam.questionCount > 0,
              trailing: _StatusChip(exam.status),
            ),
          ),
        ),
      ),
    );
  }

  String _subtitle(Exam exam) {
    final date = exam.completedAt;
    return date == null
        ? 'Ainda não finalizada'
        : 'Finalizada em ${DateFormat('dd/MM/yyyy').format(date.toLocal())}';
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip(this.status);
  final String status;

  @override
  Widget build(BuildContext context) {
    final label = switch (status.toLowerCase()) {
      'finalizado' || 'finalizada' => 'Finalizada',
      'em_andamento' || 'iniciado' => 'Em andamento',
      _ => status.isEmpty ? 'Pendente' : status,
    };
    return Chip(label: Text(label));
  }
}
