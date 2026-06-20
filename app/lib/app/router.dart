import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/presentation/login_page.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/home/presentation/home_page.dart';
import '../features/students/presentation/student_selector_page.dart';

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
    ],
  );
});
