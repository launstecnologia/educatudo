import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../data/exams_repository.dart';
import '../domain/exam.dart';

class ExamSubjectDetailPage extends ConsumerWidget {
  const ExamSubjectDetailPage({
    required this.studentId,
    required this.subjectName,
    super.key,
  });
  final int studentId;
  final String subjectName;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(examsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: Text(subjectName)),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => const Center(
          child: Text('Não foi possível carregar o desempenho.'),
        ),
        data: (all) {
          final exams = all
                  .where(
                    (e) =>
                        (e.subjectName?.trim().isNotEmpty == true
                            ? e.subjectName!.trim()
                            : 'Sem matéria') ==
                        subjectName,
                  )
                  .toList(),
              correct = exams.fold(0, (v, e) => v + e.correctCount),
              wrong = exams.fold(0, (v, e) => v + e.incorrectCount);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                children: [
                  Expanded(
                    child: _Count(
                      label: 'Acertos',
                      value: correct,
                      color: Colors.green,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _Count(
                      label: 'Erros',
                      value: wrong,
                      color: Colors.red,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                'Provas',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...exams.map((exam) => _ExamCard(exam: exam)),
            ],
          );
        },
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count({required this.label, required this.value, required this.color});
  final String label;
  final int value;
  final Color color;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          Text(
            '$value',
            style: TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w900,
              color: color,
            ),
          ),
          Text(label),
        ],
      ),
    ),
  );
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.exam});
  final Exam exam;
  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  exam.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ),
              CircleAvatar(child: Text(exam.grade?.toStringAsFixed(1) ?? '--')),
            ],
          ),
          if (exam.completedAt != null)
            Text(
              'Finalizada em ${DateFormat('dd/MM/yyyy').format(exam.completedAt!.toLocal())}',
            ),
          const SizedBox(height: 12),
          LinearProgressIndicator(value: exam.accuracyPercent / 100),
          const SizedBox(height: 8),
          Text(
            '${exam.correctCount} acertos · ${exam.incorrectCount} erros · ${exam.questionCount} questões · ${exam.accuracyPercent.toStringAsFixed(0)}%',
          ),
        ],
      ),
    ),
  );
}
