import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../students/presentation/selected_student_controller.dart';
import '../data/notices_repository.dart';
import '../domain/school_notice.dart';

class NoticesPage extends ConsumerWidget {
  const NoticesPage({required this.studentId, super.key});

  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final student = ref.watch(selectedStudentProvider);
    final notices = ref.watch(noticesProvider(studentId));
    return Scaffold(
      appBar: AppBar(
        title: Text(student == null ? 'Recados' : 'Recados de ${student.name}'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(noticesProvider(studentId).future),
        child: notices.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.cloud_off_outlined, size: 52),
              const SizedBox(height: 12),
              const Text(
                'Nao foi possivel carregar os recados.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: () => ref.invalidate(noticesProvider(studentId)),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (items) => items.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(24),
                  children: const [
                    Icon(Icons.campaign_outlined, size: 52),
                    SizedBox(height: 12),
                    Text(
                      'Nenhum recado para este aluno no momento.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (_, index) => _NoticeCard(notice: items[index]),
                ),
        ),
      ),
    );
  }
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({required this.notice});

  final SchoolNotice notice;

  @override
  Widget build(BuildContext context) {
    final metadata = [
      if (notice.authorName?.isNotEmpty == true) notice.authorName!,
      if (notice.subjectName?.isNotEmpty == true) notice.subjectName!,
      if (notice.publishedAt != null)
        DateFormat('dd/MM/yyyy HH:mm').format(notice.publishedAt!.toLocal()),
    ].join(' · ');
    return Card(
      child: ExpansionTile(
        leading: const Icon(Icons.campaign_outlined),
        title: Text(notice.title),
        subtitle: metadata.isEmpty ? null : Text(metadata),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(_plainText(notice.content)),
            ),
          ),
        ],
      ),
    );
  }

  String _plainText(String value) => value
      .replaceAll(RegExp('<br\\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp('<[^>]*>'), '')
      .replaceAll('&nbsp;', ' ')
      .trim();
}
