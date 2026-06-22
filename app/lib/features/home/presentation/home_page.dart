import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/config/app_config.dart';
import '../../auth/presentation/session_controller.dart';
import '../../students/domain/student.dart';
import '../../students/presentation/selected_student_controller.dart';
import '../data/home_repository.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final parent = ref.watch(sessionControllerProvider).value,
        student = ref.watch(selectedStudentProvider);
    if (student == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) context.go('/students');
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final summary = ref.watch(homeSummaryProvider(student.id));
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(homeSummaryProvider(student.id).future),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 110),
            children: [
              _Header(
                parentName: parent?.name ?? 'Responsável',
                onNotifications: () => context.push('/notifications'),
                onProfile: () => _profile(context, ref),
              ),
              const SizedBox(height: 18),
              _StudentCard(
                student: student,
                onChange: () => context.go('/students'),
              ),
              const SizedBox(height: 18),
              summary.when(
                loading: () => const LinearProgressIndicator(),
                error: (_, _) => _Error(
                  onRetry: () =>
                      ref.invalidate(homeSummaryProvider(student.id)),
                ),
                data: (data) => _PerformanceCard(
                  summary: data,
                  onTap: () => context.push('/students/${student.id}/exams'),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Acesso rápido',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              _QuickGrid(studentId: student.id),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Atividade recente',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: () => context.push('/notifications'),
                    child: const Text('Ver todas'),
                  ),
                ],
              ),
              summary.when(
                loading: () => const SizedBox(),
                error: (_, _) => const SizedBox(),
                data: (data) => _RecentActivity(
                  items: data.recentActivity,
                  studentId: student.id,
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: _BottomNav(studentId: student.id),
    );
  }

  void _profile(BuildContext context, WidgetRef ref) {
    final parent = ref.read(sessionControllerProvider).value;
    showModalBottomSheet<void>(
      context: context,
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircleAvatar(
                radius: 34,
                child: Icon(Icons.person_outline, size: 36),
              ),
              const SizedBox(height: 12),
              Text(
                parent?.name ?? 'Responsável',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              ListTile(
                leading: const Icon(Icons.switch_account),
                title: const Text('Trocar aluno'),
                onTap: () {
                  Navigator.pop(context);
                  context.go('/students');
                },
              ),
              ListTile(
                leading: const Icon(Icons.logout),
                title: const Text('Sair'),
                onTap: () {
                  Navigator.pop(context);
                  ref.read(sessionControllerProvider.notifier).logout();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.parentName,
    required this.onNotifications,
    required this.onProfile,
  });
  final String parentName;
  final VoidCallback onNotifications, onProfile;
  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour,
        greeting = hour < 12
            ? 'Bom dia'
            : hour < 18
            ? 'Boa tarde'
            : 'Boa noite';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 58,
              height: 52,
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Image.asset('assets/images/colag_logo.png'),
            ),
            const Spacer(),
            _RoundButton(
              icon: Icons.notifications_none,
              onTap: onNotifications,
              showDot: true,
            ),
            const SizedBox(width: 10),
            _RoundButton(icon: Icons.person_outline, onTap: onProfile),
          ],
        ),
        const SizedBox(height: 20),
        Text(
          '$greeting, ${parentName.split(' ').first}! 👋',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w900,
            color: const Color(0xFF172C73),
          ),
        ),
        const Text(
          'Acompanhe tudo sobre a vida escolar.',
          style: TextStyle(color: Color(0xFF64748B), fontSize: 16),
        ),
      ],
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.icon,
    required this.onTap,
    this.showDot = false,
  });
  final IconData icon;
  final VoidCallback onTap;
  final bool showDot;
  @override
  Widget build(BuildContext context) => Stack(
    children: [
      Material(
        color: Colors.white,
        shape: const CircleBorder(),
        elevation: 3,
        child: IconButton(
          onPressed: onTap,
          icon: Icon(icon),
          padding: const EdgeInsets.all(14),
        ),
      ),
      if (showDot)
        const Positioned(
          right: 8,
          top: 7,
          child: CircleAvatar(radius: 5, backgroundColor: Colors.red),
        ),
    ],
  );
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.student, required this.onChange});
  final Student student;
  final VoidCallback onChange;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          Row(
            children: [
              _Avatar(student: student),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 19,
                      ),
                    ),
                    Text(
                      student.classLabel.isEmpty
                          ? 'Aluno selecionado'
                          : student.classLabel,
                      style: const TextStyle(color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(
                Icons.verified_user_outlined,
                size: 18,
                color: Color(0xFF1D4ED8),
              ),
              const SizedBox(width: 6),
              const Expanded(
                child: Text(
                  'Colégio Almeida Garrett',
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton.icon(
                onPressed: onChange,
                icon: const Icon(Icons.swap_horiz, size: 18),
                label: const Text('Trocar'),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.student});
  final Student student;
  @override
  Widget build(BuildContext context) {
    final raw = student.photoUrl ?? '',
        url = raw.startsWith('http')
            ? raw
            : '${AppConfig.apiOrigin}/${raw.replaceFirst(RegExp(r'^/+'), '')}';
    return CircleAvatar(
      radius: 38,
      backgroundColor: const Color(0xFFDCE9FF),
      backgroundImage: raw.isNotEmpty ? NetworkImage(url) : null,
      child: raw.isEmpty
          ? Text(
              student.name.characters.first,
              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
            )
          : null,
    );
  }
}

class _PerformanceCard extends StatelessWidget {
  const _PerformanceCard({required this.summary, required this.onTap});
  final HomeSummary summary;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    final status = summary.accessStatus, next = summary.nextExam;
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: const Color(0xFF2457B8),
        borderRadius: BorderRadius.circular(26),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33075CE5),
            blurRadius: 24,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _HeroMetric(
                  label: 'MÉDIA GERAL',
                  value: summary.averageGrade?.toStringAsFixed(1) ?? '--',
                  detail: '★ ★ ★ ★ ☆',
                ),
              ),
              Container(width: 1, height: 90, color: Colors.white30),
              Expanded(
                child: _HeroMetric(
                  label: 'STATUS',
                  value: status == null
                      ? '--'
                      : status.isAtSchool
                      ? 'Na escola'
                      : 'Fora',
                  detail: status?.at == null
                      ? 'Sem registro'
                      : DateFormat('HH:mm').format(status!.at!.toLocal()),
                ),
              ),
              Container(width: 1, height: 90, color: Colors.white30),
              Expanded(
                child: _HeroMetric(
                  label: 'PRÓXIMA PROVA',
                  value: next?.subjectName ?? next?.title ?? '--',
                  detail: next?.date == null
                      ? 'Não agendada'
                      : DateFormat('dd/MM').format(next!.date!.toLocal()),
                  small: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          OutlinedButton(
            onPressed: onTap,
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              side: const BorderSide(color: Colors.white70),
              minimumSize: const Size.fromHeight(48),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Ver desempenho completo'),
                SizedBox(width: 10),
                Icon(Icons.arrow_forward),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.label,
    required this.value,
    required this.detail,
    this.small = false,
  });
  final String label, value, detail;
  final bool small;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 8),
    child: Column(
      children: [
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white70, fontSize: 11),
        ),
        const SizedBox(height: 10),
        Text(
          value,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Colors.white,
            fontSize: small ? 17 : 29,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 7),
        Text(
          detail,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white, fontSize: 13),
        ),
      ],
    ),
  );
}

class _QuickGrid extends StatelessWidget {
  const _QuickGrid({required this.studentId});
  final int studentId;
  @override
  Widget build(BuildContext context) {
    final items = [
      (
        Icons.assignment_outlined,
        'Provas',
        'Ver desempenho',
        '/students/$studentId/exams',
        const Color(0xFF1D4ED8),
      ),
      (
        Icons.route_outlined,
        'Jornadas',
        'Acessar',
        '/students/$studentId/journeys',
        const Color(0xFFFF7A00),
      ),
      (
        Icons.login_outlined,
        'Entrada e saída',
        'Ver histórico',
        '/students/$studentId/attendance',
        const Color(0xFF16A34A),
      ),
      (
        Icons.grading_outlined,
        'Boletim',
        'Ver notas',
        '/students/$studentId/report-card',
        const Color(0xFF7C3AED),
      ),
      (
        Icons.edit_note_outlined,
        'Redação',
        'Ver redações',
        '/students/$studentId/writing',
        const Color(0xFF9333EA),
      ),
      (
        Icons.menu_book_outlined,
        'Plano de aula',
        'Ver planos',
        '/students/$studentId/lesson-plans',
        const Color(0xFF2563EB),
      ),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 1.55,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
      ),
      itemCount: items.length,
      itemBuilder: (_, i) {
        final item = items[i];
        return Card(
          child: InkWell(
            onTap: () => context.push(item.$4),
            borderRadius: BorderRadius.circular(18),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: item.$5.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(item.$1, color: item.$5),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.$2,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          item.$3,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.arrow_forward_ios, size: 15),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _RecentActivity extends StatelessWidget {
  const _RecentActivity({required this.items, required this.studentId});
  final List<HomeActivity> items;
  final int studentId;
  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text(
            'Nenhuma atividade recente.',
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return Card(
      child: Column(
        children: items
            .map(
              (item) => ListTile(
                onTap: () => _open(context, item),
                leading: CircleAvatar(
                  backgroundColor: _color(item.type).withValues(alpha: .12),
                  child: Icon(_icon(item.type), color: _color(item.type)),
                ),
                title: Text(
                  item.title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  item.occurredAt == null
                      ? ''
                      : DateFormat(
                          'dd/MM/yyyy HH:mm',
                        ).format(item.occurredAt!.toLocal()),
                ),
                trailing: const Icon(Icons.chevron_right),
              ),
            )
            .toList(),
      ),
    );
  }

  void _open(BuildContext context, HomeActivity item) {
    switch (item.type) {
      case 'attendance':
        context.push('/students/$studentId/attendance');
        return;
      case 'exam':
        context.push('/students/$studentId/exams');
        return;
      case 'communication':
        context.push('/students/$studentId${item.route}');
        return;
      default:
        context.push('/notifications');
        return;
    }
  }

  IconData _icon(String type) => switch (type) {
    'attendance' => Icons.school,
    'exam' => Icons.star,
    'communication' => Icons.campaign,
    _ => Icons.notifications,
  };
  Color _color(String type) => switch (type) {
    'attendance' => Colors.green,
    'exam' => Colors.orange,
    'communication' => Colors.blue,
    _ => Colors.grey,
  };
}

class _BottomNav extends StatelessWidget {
  const _BottomNav({required this.studentId});
  final int studentId;
  @override
  Widget build(BuildContext context) => NavigationBar(
    selectedIndex: 0,
    onDestinationSelected: (i) {
      switch (i) {
        case 1:
          context.push('/students/$studentId/exams');
          return;
        case 2:
          context.push('/students/$studentId/school-communications');
          return;
        case 3:
          context.push('/students/$studentId/calendar');
          return;
        case 4:
          context.go('/students');
          return;
        default:
          return;
      }
    },
    destinations: const [
      NavigationDestination(
        icon: Icon(Icons.home_outlined),
        selectedIcon: Icon(Icons.home),
        label: 'Início',
      ),
      NavigationDestination(
        icon: Icon(Icons.insights_outlined),
        label: 'Desempenho',
      ),
      NavigationDestination(
        icon: Icon(Icons.chat_bubble_outline),
        label: 'Comunicação',
      ),
      NavigationDestination(
        icon: Icon(Icons.calendar_month_outlined),
        label: 'Agenda',
      ),
      NavigationDestination(icon: Icon(Icons.person_outline), label: 'Perfil'),
    ],
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.onRetry});
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: const Icon(Icons.sync_problem),
      title: const Text('Resumo indisponível'),
      trailing: IconButton(onPressed: onRetry, icon: const Icon(Icons.refresh)),
    ),
  );
}
