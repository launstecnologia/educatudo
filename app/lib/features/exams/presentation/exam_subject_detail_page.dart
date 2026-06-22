import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
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
          final columnsByKey = <String, Exam>{};
          for (final exam in exams) {
            final name = exam.subjectName?.trim().isNotEmpty == true
                ? exam.subjectName!.trim()
                : 'Sem matéria';
            subjects.putIfAbsent(name, () => []).add(exam);
            columnsByKey.putIfAbsent(exam.columnKey, () => exam);
          }
          final columns = columnsByKey.values.toList()
            ..sort(
              (a, b) => (a.blockDate ?? a.completedAt ?? DateTime(2100))
                  .compareTo(b.blockDate ?? b.completedAt ?? DateTime(2100)),
            );
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
              if (columns.isNotEmpty) ...[
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: columns.indexed.map((entry) {
                      final (index, exam) = entry;
                      final date = exam.blockDate ?? exam.completedAt;
                      return Container(
                        width: 105,
                        margin: const EdgeInsets.only(right: 8),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF1FF),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Column(
                          children: [
                            Text(
                              'S${index + 1}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              date == null
                                  ? 'Sem data'
                                  : DateFormat('dd/MM/yyyy').format(date),
                              style: const TextStyle(fontSize: 11),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ),
                ),
                const SizedBox(height: 18),
              ],
              Text(
                'Resultado por matéria',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              ...subjects.entries.map(
                (entry) => _SubjectResult(
                  name: entry.key,
                  exams: entry.value,
                  columns: columns,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _SubjectResult extends StatelessWidget {
  const _SubjectResult({
    required this.name,
    required this.exams,
    required this.columns,
  });
  final String name;
  final List<Exam> exams;
  final List<Exam> columns;

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
            const SizedBox(height: 12),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: columns.indexed.map((entry) {
                  final (index, column) = entry;
                  final cells = exams
                      .where((exam) => exam.columnKey == column.columnKey)
                      .toList();
                  final hits = cells.fold(
                    0,
                    (sum, exam) => sum + exam.correctCount,
                  );
                  final misses = cells.fold(
                    0,
                    (sum, exam) => sum + exam.incorrectCount,
                  );
                  final questions = cells.fold(
                    0,
                    (sum, exam) => sum + exam.questionCount,
                  );
                  return Container(
                    width: 108,
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      border: Border.all(color: const Color(0xFFD8E0EC)),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      children: [
                        Text(
                          'S${index + 1}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '✓ $hits',
                          style: const TextStyle(color: Colors.green),
                        ),
                        Text(
                          '✕ $misses',
                          style: const TextStyle(color: Colors.red),
                        ),
                        Text('Q $questions'),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ),
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
