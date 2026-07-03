import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/config/app_config.dart';
import '../../../core/school/school_config.dart';
import '../../auth/presentation/session_controller.dart';
import '../../school_calendar/data/school_calendar_repository.dart';
import '../../school_calendar/domain/school_event.dart';
import '../../students/domain/student.dart';
import '../../students/presentation/selected_student_controller.dart';
import '../data/home_repository.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final parent = ref.watch(sessionControllerProvider).value,
        student = ref.watch(selectedStudentProvider);
    final school = ref.watch(schoolConfigProvider);
    if (student == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) context.go('/students');
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final summary = ref.watch(homeSummaryProvider(student.id));
    final agenda = ref.watch(schoolEventsProvider(student.id));
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            await Future.wait([
              ref.refresh(homeSummaryProvider(student.id).future),
              ref.refresh(schoolEventsProvider(student.id).future),
            ]);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
            children: [
              _StudentHero(
                parentName: parent?.name ?? 'Responsável',
                student: student,
                school: school,
                onNotifications: () => context.push('/notifications'),
                onProfile: () => _profile(context, ref),
                onChange: () => context.go('/students'),
              ),
              const SizedBox(height: 18),
              summary.when(
                loading: () => const SizedBox(height: 104),
                error: (_, _) => const SizedBox(),
                data: (data) => _StatsStrip(summary: data),
              ),
              const SizedBox(height: 18),
              agenda.when(
                loading: () => const LinearProgressIndicator(),
                error: (_, _) => _Error(
                  onRetry: () =>
                      ref.invalidate(schoolEventsProvider(student.id)),
                ),
                data: (events) => _NextAgendaCard(
                  event: _nextEvent(events),
                  onTap: () => context.push('/students/${student.id}/calendar'),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Acompanhamento',
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
    );
  }

  SchoolEvent? _nextEvent(List<SchoolEvent> events) {
    final now = DateTime.now();
    final upcoming =
        events.where((event) => !event.startsAt.isBefore(now)).toList()
          ..sort((a, b) => a.startsAt.compareTo(b.startsAt));
    return upcoming.isEmpty ? null : upcoming.first;
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

class _StudentHero extends StatelessWidget {
  const _StudentHero({
    required this.parentName,
    required this.student,
    required this.school,
    required this.onNotifications,
    required this.onProfile,
    required this.onChange,
  });
  final Student student;
  final String parentName;
  final AsyncValue<SchoolConfig> school;
  final VoidCallback onNotifications, onProfile, onChange;
  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour,
        greeting = hour < 12
            ? 'Bom dia'
            : hour < 18
            ? 'Boa tarde'
            : 'Boa noite';
    return TweenAnimationBuilder<double>(
      duration: const Duration(milliseconds: 650),
      curve: Curves.easeOutCubic,
      tween: Tween(begin: 0, end: 1),
      builder: (context, value, child) => Transform.translate(
        offset: Offset(0, 22 * (1 - value)),
        child: Opacity(opacity: value, child: child),
      ),
      child: Container(
        constraints: const BoxConstraints(minHeight: 235),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF075CE5), Color(0xFF1939A8)],
          ),
          borderRadius: BorderRadius.circular(30),
          boxShadow: const [
            BoxShadow(
              color: Color(0x40075CE5),
              blurRadius: 28,
              offset: Offset(0, 12),
            ),
          ],
        ),
        child: Stack(
          children: [
            const Positioned(
              right: -20,
              top: -35,
              child: _DecorativeCircle(size: 145),
            ),
            const Positioned(
              left: 115,
              bottom: -58,
              child: _DecorativeCircle(size: 120),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 62,
                      height: 58,
                      padding: const EdgeInsets.all(7),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: SchoolLogo(config: school),
                    ),
                    const Spacer(),
                    _RoundButton(
                      icon: Icons.notifications_none,
                      onTap: onNotifications,
                      showDot: true,
                      dark: true,
                    ),
                    const SizedBox(width: 8),
                    _RoundButton(
                      icon: Icons.person_outline,
                      onTap: onProfile,
                      dark: true,
                    ),
                  ],
                ),
                const SizedBox(height: 17),
                Text(
                  '$greeting, ${parentName.split(' ').first}! 👋',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    _Avatar(student: student, radius: 35),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            student.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 23,
                              height: 1.05,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            student.classLabel.isEmpty
                                ? 'Aluno selecionado'
                                : student.classLabel,
                            style: const TextStyle(color: Colors.white70),
                          ),
                        ],
                      ),
                    ),
                    IconButton.filledTonal(
                      onPressed: onChange,
                      tooltip: 'Trocar aluno',
                      icon: const Icon(Icons.swap_horiz),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: const Color(0xFF1745B8),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DecorativeCircle extends StatelessWidget {
  const _DecorativeCircle({required this.size});
  final double size;
  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: Colors.white.withValues(alpha: .12), width: 2),
      color: Colors.white.withValues(alpha: .04),
    ),
  );
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.icon,
    required this.onTap,
    this.showDot = false,
    this.dark = false,
  });
  final IconData icon;
  final VoidCallback onTap;
  final bool showDot;
  final bool dark;
  @override
  Widget build(BuildContext context) => Stack(
    children: [
      Material(
        color: dark ? Colors.white.withValues(alpha: .18) : Colors.white,
        shape: const CircleBorder(),
        elevation: 3,
        child: IconButton(
          onPressed: onTap,
          icon: Icon(icon, color: dark ? Colors.white : null),
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

class _Avatar extends StatelessWidget {
  const _Avatar({required this.student, this.radius = 38});
  final Student student;
  final double radius;
  @override
  Widget build(BuildContext context) {
    final raw = student.photoUrl ?? '';
    final url = _normalizeUrl(raw);
    return CircleAvatar(
      radius: radius,
      backgroundColor: const Color(0xFFDCE9FF),
      backgroundImage: url != null ? NetworkImage(url) : null,
      child: raw.isEmpty
          ? Text(
              student.name.characters.first,
              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
            )
          : null,
    );
  }

  String? _normalizeUrl(String raw) {
    final value = raw.trim();
    if (value.isEmpty) return null;
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    final origin = AppConfig.apiOrigin.endsWith('/')
        ? AppConfig.apiOrigin.substring(0, AppConfig.apiOrigin.length - 1)
        : AppConfig.apiOrigin;
    if (value.startsWith('/')) return '$origin$value';
    return '$origin/$value';
  }
}

class _StatsStrip extends StatelessWidget {
  const _StatsStrip({required this.summary});
  final HomeSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = [
      (
        Icons.assignment_turned_in_outlined,
        summary.totalExams,
        'Provas',
        const Color(0xFF075CE5),
      ),
      (
        Icons.route_outlined,
        summary.totalExercises,
        'Atividades',
        const Color(0xFF0F9F91),
      ),
      (
        Icons.edit_note_outlined,
        summary.totalEssays,
        'Redações',
        const Color(0xFF8B5CF6),
      ),
    ];
    return Row(
      children: items.indexed.map((entry) {
        final (index, item) = entry;
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(left: index == 0 ? 0 : 8),
            child: TweenAnimationBuilder<double>(
              duration: Duration(milliseconds: 450 + index * 120),
              curve: Curves.easeOutBack,
              tween: Tween(begin: .82, end: 1),
              builder: (context, value, child) =>
                  Transform.scale(scale: value, child: child),
              child: Container(
                height: 108,
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x120F172A),
                      blurRadius: 18,
                      offset: Offset(0, 7),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _FloatingIcon(
                      icon: item.$1,
                      color: item.$4,
                      size: 27,
                      delay: Duration(milliseconds: index * 180),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.$2}',
                      style: TextStyle(
                        color: item.$4,
                        fontSize: 23,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      item.$3,
                      maxLines: 1,
                      style: const TextStyle(
                        color: Color(0xFF475569),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _NextAgendaCard extends StatelessWidget {
  const _NextAgendaCard({required this.event, required this.onTap});
  final SchoolEvent? event;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
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
          const _FloatingIcon(
            icon: Icons.calendar_month_outlined,
            color: Colors.white,
            size: 34,
            delay: Duration(milliseconds: 120),
          ),
          const SizedBox(height: 10),
          const Text(
            'PRÓXIMA AGENDA',
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),
          const SizedBox(height: 8),
          Text(
            event?.title ?? 'Nenhum compromisso agendado',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _details(),
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontSize: 15),
          ),
          const SizedBox(height: 16),
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
                Text('Ver agenda completa'),
                SizedBox(width: 10),
                Icon(Icons.arrow_forward),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _details() {
    if (event == null) return 'Consulte o calendário escolar';
    final date = event!.startsAt.toLocal();
    final when = event!.allDay
        ? DateFormat('dd/MM/yyyy').format(date)
        : DateFormat('dd/MM/yyyy • HH:mm').format(date);
    final location = event!.location?.trim();
    return location?.isNotEmpty == true ? '$when • $location' : when;
  }
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
      (
        Icons.forum_outlined,
        'Comunicação',
        'Fale com a escola',
        '/students/$studentId/school-communications',
        const Color(0xFF0891B2),
      ),
      (
        Icons.calendar_month_outlined,
        'Calendário',
        'Datas importantes',
        '/students/$studentId/calendar',
        const Color(0xFFF59E0B),
      ),
      (
        Icons.account_balance_wallet_outlined,
        'Financeiro',
        'Faturas e contrato',
        '/students/$studentId/finance',
        const Color(0xFF0F766E),
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final isTight = constraints.maxWidth < 370;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            childAspectRatio: isTight ? 1.08 : 1.34,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
          ),
          itemCount: items.length,
          itemBuilder: (_, i) {
            final item = items[i];
            return TweenAnimationBuilder<double>(
              duration: Duration(milliseconds: 350 + i * 70),
              curve: Curves.easeOutCubic,
              tween: Tween(begin: 0, end: 1),
              builder: (context, value, child) => Transform.translate(
                offset: Offset(0, 14 * (1 - value)),
                child: Opacity(opacity: value, child: child),
              ),
              child: Card(
                child: InkWell(
                  onTap: () => context.push(item.$4),
                  borderRadius: BorderRadius.circular(18),
                  child: Padding(
                    padding: EdgeInsets.all(isTight ? 12 : 14),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              decoration: BoxDecoration(
                                color: item.$5.withValues(alpha: .11),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: _FloatingIcon(
                                icon: item.$1,
                                color: item.$5,
                                size: 27,
                                delay: Duration(milliseconds: i * 110),
                              ),
                            ),
                            const Spacer(),
                            CircleAvatar(
                              radius: 13,
                              backgroundColor: item.$5.withValues(alpha: .11),
                              child: Icon(
                                Icons.arrow_forward_ios,
                                size: 12,
                                color: item.$5,
                              ),
                            ),
                          ],
                        ),
                        SizedBox(height: isTight ? 10 : 12),
                        Text(
                          item.$2,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          softWrap: true,
                          style: TextStyle(
                            height: 1.05,
                            fontSize: isTight ? 15 : 16,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          item.$3,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            height: 1.15,
                            fontSize: isTight ? 11 : 12,
                            color: const Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _FloatingIcon extends StatefulWidget {
  const _FloatingIcon({
    required this.icon,
    required this.color,
    required this.size,
    this.delay = Duration.zero,
  });

  final IconData icon;
  final Color color;
  final double size;
  final Duration delay;

  @override
  State<_FloatingIcon> createState() => _FloatingIconState();
}

class _FloatingIconState extends State<_FloatingIcon>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1250),
    );
    _animation = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
    Future<void>.delayed(widget.delay, () {
      if (mounted && !MediaQuery.disableAnimationsOf(context)) {
        _controller.repeat(reverse: true);
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _animation,
    builder: (_, child) {
      final progress = _controller.isAnimating ? _animation.value : .5;
      return Transform.translate(
        offset: Offset(0, -2.5 * progress),
        child: Transform.rotate(
          angle: (progress - .5) * .06,
          child: Transform.scale(scale: .96 + progress * .07, child: child),
        ),
      );
    },
    child: Icon(widget.icon, color: widget.color, size: widget.size),
  );
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
