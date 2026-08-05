import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/students/presentation/selected_student_controller.dart';

class AppShell extends ConsumerWidget {
  const AppShell({required this.location, required this.child, super.key});

  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    body: child,
    bottomNavigationBar: _AppBottomNav(
      location: location,
      studentId: ref.watch(selectedStudentProvider)?.id,
    ),
  );
}

class _AppBottomNav extends StatelessWidget {
  const _AppBottomNav({required this.location, required this.studentId});

  final String location;
  final int? studentId;

  @override
  Widget build(BuildContext context) {
    final academic =
        location.contains('/exams') ||
        location.contains('/report-card') ||
        location.contains('/journeys') ||
        location.contains('/writing') ||
        location.contains('/lesson-plans') ||
        location.contains('/finance') ||
        location.contains('/attendance');
    final communication =
        location.contains('/school-communications') ||
        location.contains('/notices') ||
        location == '/notifications';
    final agenda = location.contains('/calendar');

    return Material(
      color: Colors.white,
      elevation: 18,
      shadowColor: const Color(0x330F172A),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 74,
          child: Row(
            children: [
              _NavItem(
                icon: Icons.auto_graph_outlined,
                selectedIcon: Icons.auto_graph,
                label: 'Desempenho',
                selected: academic,
                onTap: () => _go(context, '/exams'),
              ),
              _NavItem(
                icon: Icons.chat_bubble_outline,
                selectedIcon: Icons.chat_bubble,
                label: 'Comunicação',
                selected: communication,
                onTap: () => _go(context, '/school-communications'),
              ),
              Expanded(
                child: Semantics(
                  button: true,
                  label: 'Início',
                  child: InkWell(
                    onTap: () => context.go('/home'),
                    borderRadius: BorderRadius.circular(40),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 52,
                          height: 52,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF075CE5), Color(0xFF5B36E8)],
                            ),
                            shape: BoxShape.circle,
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x45075CE5),
                                blurRadius: 14,
                                offset: Offset(0, 5),
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.home_rounded,
                            color: Colors.white,
                            size: 29,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              _NavItem(
                icon: Icons.calendar_month_outlined,
                selectedIcon: Icons.calendar_month,
                label: 'Agenda',
                selected: agenda,
                onTap: () => _go(context, '/calendar'),
              ),
              _NavItem(
                icon: Icons.switch_account_outlined,
                selectedIcon: Icons.switch_account,
                label: 'Aluno',
                selected: false,
                onTap: () => context.go('/students'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _go(BuildContext context, String suffix) {
    final id = studentId;
    if (id == null) {
      context.go('/students');
      return;
    }
    context.go('/students/$id$suffix');
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon, selectedIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: InkWell(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
            decoration: BoxDecoration(
              color: selected ? const Color(0xFFE8F1FF) : Colors.transparent,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(
              selected ? selectedIcon : icon,
              color: selected
                  ? const Color(0xFF075CE5)
                  : const Color(0xFF64748B),
              size: 23,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            style: TextStyle(
              fontSize: 10,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              color: selected
                  ? const Color(0xFF075CE5)
                  : const Color(0xFF475569),
            ),
          ),
        ],
      ),
    ),
  );
}
