import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../students/presentation/selected_student_controller.dart';
import '../data/attendance_repository.dart';
import '../domain/access_event.dart';

class AttendancePage extends ConsumerWidget {
  const AttendancePage({required this.studentId, super.key});
  final int studentId;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final student = ref.watch(selectedStudentProvider),
        state = ref.watch(accessHistoryProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Entrada e saída')),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(accessHistoryProvider(studentId).future),
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (_, _) => ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Icon(Icons.cloud_off_outlined, size: 52),
              const Text(
                'Não foi possível carregar os registros.',
                textAlign: TextAlign.center,
              ),
              FilledButton.tonal(
                onPressed: () =>
                    ref.invalidate(accessHistoryProvider(studentId)),
                child: const Text('Tentar novamente'),
              ),
            ],
          ),
          data: (history) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _StatusCard(
                studentName: student?.name ?? 'Aluno',
                status: history.status,
              ),
              const SizedBox(height: 20),
              Text(
                'Histórico',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              if (history.events.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text(
                      'Nenhuma entrada ou saída registrada.',
                      textAlign: TextAlign.center,
                    ),
                  ),
                )
              else
                ..._groups(context, history.events),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _groups(BuildContext context, List<AccessEvent> events) {
    final out = <Widget>[], format = DateFormat('dd/MM/yyyy');
    String? day;
    for (final event in events) {
      final key = format.format(event.eventAt.toLocal());
      if (day != key) {
        day = key;
        out.add(
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
            child: Text(
              _dayLabel(event.eventAt),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
          ),
        );
      }
      out.add(_EventTile(event: event));
    }
    return out;
  }

  String _dayLabel(DateTime value) {
    final local = value.toLocal(), now = DateTime.now();
    if (local.year == now.year &&
        local.month == now.month &&
        local.day == now.day) {
      return 'Hoje';
    }
    return DateFormat('dd/MM/yyyy').format(local);
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.studentName, required this.status});
  final String studentName;
  final AccessStatus? status;
  @override
  Widget build(BuildContext context) {
    final inside = status?.isAtSchool == true;
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: inside
              ? [const Color(0xFF0F9D58), const Color(0xFF34C759)]
              : [const Color(0xFF334155), const Color(0xFF64748B)],
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 30,
            backgroundColor: Colors.white24,
            child: Icon(
              inside ? Icons.school : Icons.home_outlined,
              color: Colors.white,
              size: 32,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  studentName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                Text(
                  status == null
                      ? 'Sem registros'
                      : inside
                      ? 'Na escola'
                      : 'Fora da escola',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 26,
                  ),
                ),
                if (status?.at != null)
                  Text(
                    'Último registro às ${DateFormat('HH:mm').format(status!.at!.toLocal())}',
                    style: const TextStyle(color: Colors.white70),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});
  final AccessEvent event;
  @override
  Widget build(BuildContext context) {
    final entry = event.kind == 'entrada';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: entry
              ? const Color(0xFFDCFCE7)
              : const Color(0xFFFEE2E2),
          child: Icon(
            entry ? Icons.login : Icons.logout,
            color: entry ? Colors.green : Colors.red,
          ),
        ),
        title: Text(
          entry ? 'Entrada' : 'Saída',
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        subtitle: Text(
          event.notified ? 'Responsáveis notificados' : 'Notificação pendente',
        ),
        trailing: Text(
          DateFormat('HH:mm').format(event.eventAt.toLocal()),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}
