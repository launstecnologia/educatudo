import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mask_text_input_formatter/mask_text_input_formatter.dart';

import '../../../core/network/api_exception.dart';
import '../../../app/theme/app_theme.dart';
import 'session_controller.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _cpfController = TextEditingController();
  final _passwordController = TextEditingController();
  final _cpfMask = MaskTextInputFormatter(
    mask: '###.###.###-##',
    filter: {'#': RegExp(r'[0-9]')},
  );
  bool _hidePassword = true;

  @override
  void dispose() {
    _cpfController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    await ref
        .read(sessionControllerProvider.notifier)
        .login(_cpfMask.getUnmaskedText(), _passwordController.text);
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionControllerProvider);
    final error = session.hasError
        ? (session.error is ApiException
              ? (session.error! as ApiException).message
              : 'Nao foi possivel entrar. Tente novamente.')
        : null;
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFE7F3FF), Color(0xFFF8FBFF), Color(0xFFDCEBFF)],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Container(
                  padding: const EdgeInsets.fromLTRB(26, 28, 26, 30),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.96),
                    borderRadius: BorderRadius.circular(28),
                    border: Border.all(color: const Color(0xFFCFE0F5)),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x1F2857A5),
                        blurRadius: 34,
                        offset: Offset(0, 14),
                      ),
                    ],
                  ),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Center(
                          child: Image.asset(
                            'assets/images/colag_logo.png',
                            width: 210,
                            fit: BoxFit.contain,
                            semanticLabel: 'Logo Colégio Almeida Garrett',
                          ),
                        ),
                        const SizedBox(height: 24),
                        const Text(
                          'Portal dos Responsáveis',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: AppTheme.navy,
                            fontSize: 25,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Acompanhe a rotina escolar de perto.',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyLarge
                              ?.copyWith(color: const Color(0xFF58708F)),
                        ),
                        const SizedBox(height: 30),
                        TextFormField(
                          key: const Key('cpfField'),
                          controller: _cpfController,
                          inputFormatters: [_cpfMask],
                          keyboardType: TextInputType.number,
                          autofillHints: const [AutofillHints.username],
                          decoration: const InputDecoration(
                            labelText: 'CPF',
                            prefixIcon: Icon(Icons.badge_outlined),
                          ),
                          validator: (_) =>
                              _cpfMask.getUnmaskedText().length == 11
                              ? null
                              : 'Informe um CPF com 11 digitos.',
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          key: const Key('passwordField'),
                          controller: _passwordController,
                          obscureText: _hidePassword,
                          autofillHints: const [AutofillHints.password],
                          decoration: InputDecoration(
                            labelText: 'Senha',
                            prefixIcon: const Icon(Icons.lock_outline),
                            suffixIcon: IconButton(
                              onPressed: () => setState(
                                () => _hidePassword = !_hidePassword,
                              ),
                              icon: Icon(
                                _hidePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                            ),
                          ),
                          validator: (value) => value == null || value.isEmpty
                              ? 'Informe sua senha.'
                              : null,
                          onFieldSubmitted: (_) => _submit(),
                        ),
                        if (error != null) ...[
                          const SizedBox(height: 16),
                          Container(
                            key: const Key('loginError'),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Theme.of(
                                context,
                              ).colorScheme.errorContainer,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              error,
                              style: TextStyle(
                                color: Theme.of(
                                  context,
                                ).colorScheme.onErrorContainer,
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 24),
                        FilledButton.icon(
                          key: const Key('loginButton'),
                          onPressed: session.isLoading ? null : _submit,
                          icon: session.isLoading
                              ? const SizedBox.square(
                                  dimension: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.login_rounded),
                          label: Text(
                            session.isLoading ? 'Entrando...' : 'Entrar',
                          ),
                        ),
                        const SizedBox(height: 18),
                        const Text(
                          'EducaTudo • Colégio Almeida Garrett',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Color(0xFF7890AD),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
