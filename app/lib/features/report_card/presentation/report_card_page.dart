import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/report_card_repository.dart';
import '../domain/report_card.dart';

class ReportCardPage extends ConsumerWidget {
  const ReportCardPage({required this.studentId, super.key});
  final int studentId;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(reportCardsProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Boletim escolar')),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(reportCardsProvider(studentId).future),
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.cloud_off_outlined, size: 52),
              const Text(
                'Não foi possível carregar o boletim.',
                textAlign: TextAlign.center,
              ),
              FilledButton.tonal(
                onPressed: () => ref.invalidate(reportCardsProvider(studentId)),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (cards) => cards.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.grading_outlined, size: 52),
                    Text(
                      'Nenhum boletim disponível.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: cards.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => _ReportCardView(card: cards[i]),
                ),
        ),
      ),
    );
  }
}

class _ReportCardView extends StatelessWidget {
  const _ReportCardView({required this.card});
  final ReportCard card;
  @override
  Widget build(BuildContext context) => Card(
    child: ExpansionTile(
      initiallyExpanded: true,
      leading: const Icon(Icons.school_outlined),
      title: Text(
        card.title,
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      subtitle: Text(
        [
          if (card.term != null) '${card.term}º bimestre',
          if (card.schoolYear != null) '${card.schoolYear}',
          if (card.period.isNotEmpty) card.period,
        ].join(' · '),
      ),
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: DataTable(
            columns: [
              const DataColumn(label: Text('Matéria')),
              ...card.columns.map((c) => DataColumn(label: Text(c.name))),
            ],
            rows: card.subjects
                .map(
                  (s) => DataRow(
                    cells: [
                      DataCell(
                        SizedBox(
                          width: 130,
                          child: Text(
                            s.name,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                      ...card.columns.map(
                        (c) => DataCell(Text(_value(s.grades[c.code]))),
                      ),
                    ],
                  ),
                )
                .toList(),
          ),
        ),
      ],
    ),
  );
  String _value(dynamic value) {
    if (value == null || '$value'.trim().isEmpty) return '—';
    if (value is num) return value.toStringAsFixed(value % 1 == 0 ? 0 : 1);
    return '$value';
  }
}
