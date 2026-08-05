import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../data/finance_repository.dart';

class FinancePaymentPage extends ConsumerWidget {
  const FinancePaymentPage({
    required this.studentId,
    required this.source,
    required this.invoiceId,
    super.key,
  });

  final int studentId;
  final String source;
  final int invoiceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final request = FinancePaymentRequest(
      studentId: studentId,
      source: source,
      invoiceId: invoiceId,
    );
    final state = ref.watch(financePaymentProvider(request));
    return Scaffold(
      appBar: AppBar(title: const Text('Pagamento')),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.error_outline,
                  color: Color(0xFFDC2626),
                  size: 42,
                ),
                const SizedBox(height: 10),
                const Text(
                  'Não foi possível carregar o pagamento.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: () =>
                      ref.invalidate(financePaymentProvider(request)),
                  child: const Text('Tentar novamente'),
                ),
              ],
            ),
          ),
        ),
        data: (data) {
          final money = NumberFormat.currency(locale: 'pt_BR', symbol: 'R\$');
          final due = data.dueDate == null
              ? 'Sem vencimento'
              : DateFormat('dd/MM/yyyy').format(data.dueDate!);
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: const Color(0xFF2457B8),
                  borderRadius: BorderRadius.circular(26),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Fatura',
                      style: TextStyle(color: Colors.white70),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      data.invoice.description,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: _InfoPill(
                            label: 'Valor',
                            value: money.format(data.amount),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _InfoPill(label: 'Vencimento', value: due),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              if (data.notice != null && data.notice!.isNotEmpty) ...[
                const SizedBox(height: 14),
                _Notice(text: data.notice!),
              ],
              const SizedBox(height: 18),
              if (data.pix != null)
                _CopyCard(
                  icon: Icons.pix,
                  title: 'PIX copia e cola',
                  subtitle: data.pix!.key == null
                      ? null
                      : 'Chave: ${data.pix!.key}',
                  value: data.pix!.copyPaste ?? data.pix!.qrPayload ?? '',
                  buttonLabel: 'Copiar PIX',
                ),
              const SizedBox(height: 12),
              if (data.boleto != null)
                _CopyCard(
                  icon: Icons.receipt_long_outlined,
                  title: 'Boleto simulado',
                  subtitle: 'Linha digitável',
                  value: data.boleto!.line ?? data.boleto!.barcode ?? '',
                  buttonLabel: 'Copiar boleto',
                ),
            ],
          );
        },
      ),
    );
  }
}

class _InfoPill extends StatelessWidget {
  const _InfoPill({required this.label, required this.value});
  final String label, value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .13),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: Colors.white70, fontSize: 12),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF7ED),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: const Color(0xFFFED7AA)),
    ),
    child: Row(
      children: [
        const Icon(Icons.info_outline, color: Color(0xFFEA580C)),
        const SizedBox(width: 10),
        Expanded(
          child: Text(text, style: const TextStyle(color: Color(0xFF9A3412))),
        ),
      ],
    ),
  );
}

class _CopyCard extends StatelessWidget {
  const _CopyCard({
    required this.icon,
    required this.title,
    required this.value,
    required this.buttonLabel,
    this.subtitle,
  });

  final IconData icon;
  final String title, value, buttonLabel;
  final String? subtitle;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                backgroundColor: const Color(0xFFE8F1FF),
                child: Icon(icon, color: const Color(0xFF075CE5)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    if (subtitle != null)
                      Text(
                        subtitle!,
                        style: const TextStyle(color: Color(0xFF64748B)),
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: SelectableText(
              value.isEmpty ? 'Não disponível' : value,
              style: const TextStyle(fontFamily: 'monospace'),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: value.isEmpty
                  ? null
                  : () async {
                      await Clipboard.setData(ClipboardData(text: value));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('$buttonLabel copiado.')),
                        );
                      }
                    },
              icon: const Icon(Icons.copy),
              label: Text(buttonLabel),
            ),
          ),
        ],
      ),
    ),
  );
}
