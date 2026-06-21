import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../data/school_communication_repository.dart';

class SchoolCommunicationDetailPage extends ConsumerStatefulWidget {
  const SchoolCommunicationDetailPage({
    required this.studentId,
    required this.communicationId,
    super.key,
  });
  final int studentId;
  final int communicationId;

  @override
  ConsumerState<SchoolCommunicationDetailPage> createState() =>
      _SchoolCommunicationDetailPageState();
}

class _SchoolCommunicationDetailPageState
    extends ConsumerState<SchoolCommunicationDetailPage> {
  final _controller = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final args = (
      studentId: widget.studentId,
      communicationId: widget.communicationId,
    );
    final state = ref.watch(schoolCommunicationProvider(args));
    return Scaffold(
      appBar: AppBar(title: const Text('Comunicação')),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) =>
            const Center(child: Text('Não foi possível abrir a comunicação.')),
        data: (item) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              item.priority.toUpperCase(),
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: item.priority == 'urgente'
                    ? Colors.red
                    : Theme.of(context).colorScheme.primary,
              ),
            ),
            Text(
              item.title,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            if (item.publishedAt != null)
              Text(
                DateFormat(
                  'dd/MM/yyyy HH:mm',
                ).format(item.publishedAt!.toLocal()),
              ),
            const SizedBox(height: 18),
            Text(item.content),
            if (item.attachments.isNotEmpty) ...[
              const Divider(height: 32),
              Text('Anexos', style: Theme.of(context).textTheme.titleMedium),
              ...item.attachments.map(
                (attachment) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.attach_file),
                  title: Text(attachment.name),
                  onTap: attachment.url.isEmpty
                      ? null
                      : () => launchUrl(
                          Uri.parse(attachment.url),
                          mode: LaunchMode.externalApplication,
                        ),
                ),
              ),
            ],
            const Divider(height: 32),
            Text('Conversa', style: Theme.of(context).textTheme.titleMedium),
            ...item.replies.map(
              (reply) => Align(
                alignment: reply.senderType == 'responsavel'
                    ? Alignment.centerRight
                    : Alignment.centerLeft,
                child: Container(
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: reply.senderType == 'responsavel'
                        ? Theme.of(context).colorScheme.primaryContainer
                        : Colors.grey.shade200,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(reply.message),
                      if (reply.createdAt != null)
                        Text(
                          DateFormat(
                            'dd/MM HH:mm',
                          ).format(reply.createdAt!.toLocal()),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                    ],
                  ),
                ),
              ),
            ),
            if (item.allowReplies) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _controller,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Responder à escola',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 8),
              FilledButton.icon(
                onPressed: _sending ? null : () => _send(args),
                icon: const Icon(Icons.send),
                label: Text(_sending ? 'Enviando...' : 'Enviar resposta'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _send(({int studentId, int communicationId}) args) async {
    final message = _controller.text.trim();
    if (message.isEmpty) return;
    setState(() => _sending = true);
    try {
      await ref
          .read(schoolCommunicationRepositoryProvider)
          .reply(args.studentId, args.communicationId, message);
      _controller.clear();
      ref.invalidate(schoolCommunicationProvider(args));
      ref.invalidate(schoolCommunicationsProvider(args.studentId));
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Resposta enviada.')));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }
}
