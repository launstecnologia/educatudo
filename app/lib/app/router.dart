import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/presentation/login_page.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/communication/presentation/notices_page.dart';
import '../features/communication/presentation/notifications_page.dart';
import '../features/exams/presentation/exams_page.dart';
import '../features/home/presentation/home_page.dart';
import '../features/journeys/presentation/journeys_page.dart';
import '../features/lesson_plans/presentation/lesson_plan_detail_page.dart';
import '../features/lesson_plans/presentation/lesson_plans_page.dart';
import '../features/students/presentation/student_selector_page.dart';
import '../features/writing_journeys/presentation/writing_page.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final session = ref.watch(sessionControllerProvider);
  return GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) {
      final location = state.matchedLocation;
      if (session.isLoading) return location == '/splash' ? null : '/splash';
      final authenticated = session.value != null;
      if (!authenticated) return location == '/login' ? null : '/login';
      if (location == '/login' || location == '/splash') return '/students';
      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (_, _) =>
            const Scaffold(body: Center(child: CircularProgressIndicator())),
      ),
      GoRoute(path: '/login', builder: (_, _) => const LoginPage()),
      GoRoute(
        path: '/students',
        builder: (_, _) => const StudentSelectorPage(),
      ),
      GoRoute(path: '/home', builder: (_, _) => const HomePage()),
      GoRoute(
        path: '/students/:studentId/notices',
        builder: (_, state) => NoticesPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/notifications',
        builder: (_, _) => const NotificationsPage(),
      ),
      GoRoute(
        path: '/students/:studentId/exams',
        builder: (_, state) => ExamsPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/students/:studentId/journeys',
        builder: (_, state) => JourneysPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/students/:studentId/lesson-plans',
        builder: (_, state) => LessonPlansPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/students/:studentId/lesson-plans/:planId',
        builder: (_, state) => LessonPlanDetailPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
          planId: int.tryParse(state.pathParameters['planId'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/students/:studentId/writing',
        builder: (_, state) => WritingPage(
          studentId: int.tryParse(state.pathParameters['studentId'] ?? '') ?? 0,
        ),
      ),
    ],
  );
});
