import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/widgets/async_list_body.dart';
import '../data/exams_repository.dart';
import '../domain/exam.dart';

class ExamsPage extends ConsumerWidget {
  const ExamsPage({required this.studentId, super.key});
  final int studentId;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(examsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Desempenho em provas')),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => AsyncErrorBody(
          message: 'Não foi possível carregar as provas.',
          onRetry: () => ref.invalidate(examsProvider(studentId)),
        ),
        data: (items) {
          final groups = _groups(items);
          return RefreshIndicator(
            onRefresh: () => ref.refresh(examsProvider(studentId).future),
            child: groups.isEmpty
                ? ListView(
                    padding: const EdgeInsets.all(24),
                    children: const [
                      Icon(Icons.assignment_outlined, size: 52),
                      Text(
                        'Nenhuma prova encontrada.',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  )
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _Overall(exams: items),
                      const SizedBox(height: 22),
                      Text(
                        'Grupos de provas',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 10),
                      ...groups.entries.map(
                        (entry) => _GroupCard(
                          title: entry.value.first.groupTitle,
                          exams: entry.value,
                          onTap: () => context.push(
                            '/students/$studentId/exams/group?key=${Uri.encodeQueryComponent(entry.key)}',
                          ),
                        ),
                      ),
                    ],
                  ),
          );
        },
      ),
    );
  }

  Map<String, List<Exam>> _groups(List<Exam> exams) {
    final map = <String, List<Exam>>{};
    for (final exam in exams) {
      map.putIfAbsent(exam.groupKey, () => []).add(exam);
    }
    return map;
  }
}

class _Overall extends StatelessWidget {
  const _Overall({required this.exams});
  final List<Exam> exams;
  @override
  Widget build(BuildContext context) {
    final correct = exams.fold(0, (v, e) => v + e.correctCount),
        wrong = exams.fold(0, (v, e) => v + e.incorrectCount),
        answered = correct + wrong,
        rate = answered == 0 ? 0 : correct * 100 / answered;
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF075CE5), Color(0xFF7C3AED), Color(0xFFFF7A00)],
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _Metric(
            label: 'ACERTOS',
            value: '$correct',
            color: Colors.greenAccent,
          ),
          _Metric(label: 'ERROS', value: '$wrong', color: Colors.orangeAccent),
          _Metric(
            label: 'APROVEITAMENTO',
            value: '${rate.toStringAsFixed(0)}%',
            color: Colors.white,
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({
    required this.label,
    required this.value,
    required this.color,
  });
  final String label, value;
  final Color color;
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
      Text(
        value,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w900,
          fontSize: 25,
        ),
      ),
    ],
  );
}

class _GroupCard extends StatelessWidget {
  const _GroupCard({
    required this.title,
    required this.exams,
    required this.onTap,
  });
  final String title;
  final List<Exam> exams;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final correct = exams.fold(0, (v, e) => v + e.correctCount),
        wrong = exams.fold(0, (v, e) => v + e.incorrectCount),
        answered = correct + wrong,
        rate = answered == 0 ? 0 : correct * 100 / answered;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              CircleAvatar(child: Text('${rate.toStringAsFixed(0)}%')),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      '${exams.length} matéria(s) · $correct acertos · $wrong erros · ${rate.toStringAsFixed(0)}%',
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
