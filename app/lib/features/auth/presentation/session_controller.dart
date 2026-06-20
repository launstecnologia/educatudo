import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/push/push_service.dart';
import '../../../core/storage/token_storage.dart';
import '../data/auth_repository.dart';
import '../domain/parent.dart';

final sessionControllerProvider =
    AsyncNotifierProvider<SessionController, Parent?>(SessionController.new);

class SessionController extends AsyncNotifier<Parent?> {
  @override
  Future<Parent?> build() async {
    final storage = ref.read(tokenStorageProvider);
    if (await storage.read() == null) return null;
    try {
      return await ref.read(authRepositoryProvider).me();
    } on ApiException catch (error) {
      if (error.isUnauthorized) await storage.clear();
      return null;
    }
  }

  Future<bool> login(String cpf, String password) async {
    state = const AsyncLoading();
    try {
      final result = await ref
          .read(authRepositoryProvider)
          .login(cpf, password);
      await ref.read(tokenStorageProvider).write(result.token);
      await ref.read(pushServiceProvider).registerForCurrentSession();
      state = AsyncData(result.parent);
      return true;
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
      return false;
    }
  }

  Future<void> logout() async {
    await ref.read(pushServiceProvider).unregister();
    await ref.read(tokenStorageProvider).clear();
    state = const AsyncData(null);
  }
}
