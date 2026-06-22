import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/exams_repository.dart';
import '../domain/exam.dart';

class ExamGroupDetailPage extends ConsumerWidget {
  const ExamGroupDetailPage({
    required this.studentId,
    required this.groupKey,
    super.key,
  });
  final int studentId;
  final String groupKey;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(examsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Resultado do grupo')),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => const Center(
          child: Text('Não foi possível carregar o desempenho.'),
        ),
        data: (all) {
          final exams = all.where((e) => e.groupKey == groupKey).toList(),
              correct = exams.fold(0, (v, e) => v + e.correctCount),
              wrong = exams.fold(0, (v, e) => v + e.incorrectCount);
          final subjects = <String, List<Exam>>{};
          for (final exam in exams) {
            final name = exam.subjectName?.trim().isNotEmpty == true
                ? exam.subjectName!.trim()
                : 'Sem matéria';
            subjects.putIfAbsent(name, () => []).add(exam);
          }
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
              if (exams.isNotEmpty) ...[
                Text(
                  exams.first.groupTitle,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 16),
              ],
              Text(
                'Resultado por matéria',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...subjects.entries.map(
                (entry) => _SubjectResult(name: entry.key, exams: entry.value),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _SubjectResult extends StatelessWidget {
  const _SubjectResult({required this.name, required this.exams});
  final String name;
  final List<Exam> exams;

  @override
  Widget build(BuildContext context) {
    final correct = exams.fold(0, (sum, exam) => sum + exam.correctCount);
    final wrong = exams.fold(0, (sum, exam) => sum + exam.incorrectCount);
    final total = exams.fold(0, (sum, exam) => sum + exam.questionCount);
    final percent = total == 0 ? 0.0 : correct * 100 / total;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    name,
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 17,
                    ),
                  ),
                ),
                Text(
                  '${percent.toStringAsFixed(0)}%',
                  style: const TextStyle(
                    color: Color(0xFF1D4ED8),
                    fontWeight: FontWeight.w900,
                    fontSize: 18,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            LinearProgressIndicator(value: percent / 100),
            const SizedBox(height: 10),
            Text('$correct acertos · $wrong erros · $total questões'),
          ],
        ),
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
