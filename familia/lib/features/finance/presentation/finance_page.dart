import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_client.dart';
import '../data/finance_repository.dart';
import '../domain/finance_overview.dart';

class FinancePage extends ConsumerWidget {
  const FinancePage({required this.studentId, super.key});

  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(financeOverviewProvider(studentId));
    return Scaffold(
      appBar: AppBar(title: const Text('Financeiro')),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _FinanceError(error: error, studentId: studentId),
        data: (data) {
          final openInvoices = data.invoices.where((i) => !i.isPaid).toList();
          final paidInvoices = data.invoices.where((i) => i.isPaid).toList();
          return RefreshIndicator(
            onRefresh: () =>
                ref.refresh(financeOverviewProvider(studentId).future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
              children: [
                _Summary(summary: data.summary),
                const SizedBox(height: 22),
                _SectionTitle(
                  title: 'Faturas',
                  subtitle: data.invoices.isEmpty
                      ? 'Nenhuma cobrança encontrada.'
                      : '${data.invoices.length} cobrança(s)',
                ),
                const SizedBox(height: 10),
                if (data.invoices.isEmpty)
                  const _EmptyCard(
                    icon: Icons.receipt_long_outlined,
                    text:
                        'Nenhuma fatura encontrada. Se a escola já usa financeiro, atualize a API no servidor.',
                  )
                else ...[
                  if (openInvoices.isNotEmpty) ...[
                    const _MiniLabel('Em aberto'),
                    const SizedBox(height: 8),
                    ...openInvoices.map(
                      (invoice) =>
                          _InvoiceCard(invoice: invoice, studentId: studentId),
                    ),
                  ],
                  if (paidInvoices.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    const _MiniLabel('Pagas'),
                    const SizedBox(height: 8),
                    ...paidInvoices.map(
                      (invoice) =>
                          _InvoiceCard(invoice: invoice, studentId: studentId),
                    ),
                  ],
                ],
                const SizedBox(height: 22),
                _SectionTitle(
                  title: 'Matrícula e contratos',
                  subtitle: 'Contratos financeiros e rematrículas',
                ),
                const SizedBox(height: 10),
                if (data.contracts.isEmpty && data.enrollments.isEmpty)
                  const _EmptyCard(
                    icon: Icons.description_outlined,
                    text:
                        'Nenhum contrato ou rematrícula disponível no momento.',
                  )
                else ...[
                  ...data.contracts.map(_ContractCard.new),
                  ...data.enrollments.map(_EnrollmentCard.new),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _FinanceError extends ConsumerWidget {
  const _FinanceError({required this.error, required this.studentId});

  final Object error;
  final int studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statusCode = error is DioException
        ? (error as DioException).response?.statusCode
        : null;
    final apiError = error is DioException
        ? mapDioException(error as DioException)
        : null;
    final isAuthError = statusCode == 401 || statusCode == 403;
    final message =
        apiError?.message ?? 'Não foi possível carregar o financeiro.';
    final String? details = apiError?.code == null
        ? null
        : 'Código: ${apiError!.code}${statusCode == null ? '' : ' • HTTP $statusCode'}';

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline,
                size: 42,
                color: Color(0xFFDC2626),
              ),
              const SizedBox(height: 10),
              Text(
                isAuthError ? 'Sessão expirada no financeiro.' : message,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              if (details != null) ...[
                const SizedBox(height: 6),
                Text(
                  details,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Color(0xFF64748B),
                    fontSize: 12,
                  ),
                ),
              ],
              const SizedBox(height: 14),
              SizedBox(
                width: 260,
                height: 48,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    fixedSize: const Size(260, 48),
                    minimumSize: const Size(260, 48),
                  ),
                  onPressed: isAuthError
                      ? () => context.go('/login')
                      : () =>
                            ref.invalidate(financeOverviewProvider(studentId)),
                  child: Text(
                    isAuthError ? 'Entrar novamente' : 'Tentar novamente',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MiniLabel extends StatelessWidget {
  const _MiniLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(
      color: Color(0xFF0F2B6D),
      fontWeight: FontWeight.w900,
      fontSize: 14,
    ),
  );
}

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final FinanceSummary summary;

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat.currency(locale: 'pt_BR', symbol: 'R\$');
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFF2457B8),
        borderRadius: BorderRadius.circular(26),
        boxShadow: const [
          BoxShadow(
            color: Color(0x26075CE5),
            blurRadius: 24,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.account_balance_wallet_outlined, color: Colors.white),
              SizedBox(width: 10),
              Text(
                'Resumo financeiro',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _SummaryValue(
                  label: 'Em aberto',
                  value: '${summary.pendingCount}',
                ),
              ),
              Expanded(
                child: _SummaryValue(
                  label: 'Valor pendente',
                  value: money.format(summary.pendingAmount),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _SummaryValue(
                  label: 'Vencido',
                  value: money.format(summary.overdueAmount),
                ),
              ),
              Expanded(
                child: _SummaryValue(
                  label: 'Contratos',
                  value: '${summary.contractsCount}',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({required this.label, required this.value});
  final String label, value;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
      const SizedBox(height: 4),
      Text(
        value,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 20,
          fontWeight: FontWeight.w900,
        ),
      ),
    ],
  );
}

class _InvoiceCard extends StatelessWidget {
  const _InvoiceCard({required this.invoice, required this.studentId});
  final FinanceInvoice invoice;
  final int studentId;

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat.currency(locale: 'pt_BR', symbol: 'R\$');
    final date = invoice.dueDate == null
        ? 'Sem vencimento'
        : DateFormat('dd/MM/yyyy').format(invoice.dueDate!);
    final color = invoice.isPaid
        ? const Color(0xFF16A34A)
        : invoice.isOverdue
        ? const Color(0xFFDC2626)
        : const Color(0xFFF59E0B);
    final paymentRoute =
        '/students/$studentId/finance/invoices/${invoice.source}/${invoice.id}/payment';
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: invoice.isPaid ? null : () => context.push(paymentRoute),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              CircleAvatar(
                backgroundColor: color.withValues(alpha: .12),
                child: Icon(Icons.receipt_long_outlined, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      invoice.description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${_status(invoice.status)} • vence em $date',
                      style: const TextStyle(color: Color(0xFF64748B)),
                    ),
                    if (invoice.isPaid)
                      const Padding(
                        padding: EdgeInsets.only(top: 6),
                        child: Text(
                          'Pagamento registrado',
                          style: TextStyle(
                            color: Color(0xFF16A34A),
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                money.format(invoice.amount),
                style: TextStyle(color: color, fontWeight: FontWeight.w900),
              ),
              if (!invoice.isPaid) ...[
                const SizedBox(width: 6),
                const Icon(Icons.chevron_right, color: Color(0xFF64748B)),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ContractCard extends StatelessWidget {
  const _ContractCard(this.contract);
  final FinanceContract contract;

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat.currency(locale: 'pt_BR', symbol: 'R\$');
    return _DocumentCard(
      title: 'Contrato financeiro #${contract.id}',
      subtitle:
          '${_status(contract.status)}${contract.schoolYear == null ? '' : ' • ${contract.schoolYear}'}',
      url: contract.contractUrl ?? contract.pdfUrl,
      icon: Icons.assignment_outlined,
      summaryTitle: 'Resumo do contrato financeiro',
      summaryRows: [
        ('Status', _status(contract.status)),
        if (contract.schoolYear != null) ('Ano letivo', contract.schoolYear!),
        if (contract.paymentPlan != null && contract.paymentPlan!.isNotEmpty)
          ('Plano', contract.paymentPlan!),
        ('Valor líquido', money.format(contract.netAmount)),
        if (contract.signedAt != null)
          ('Assinado em', DateFormat('dd/MM/yyyy').format(contract.signedAt!)),
      ],
      itemRows: contract.items
          .map(
            (item) => (
              item.description,
              [
                if (item.category.isNotEmpty) item.category,
                money.format(item.netAmount),
                if ((item.installmentsCount ?? 0) > 0)
                  '${item.installmentsCount} parcela(s)',
              ].join(' • '),
            ),
          )
          .toList(),
    );
  }
}

class _EnrollmentCard extends StatelessWidget {
  const _EnrollmentCard(this.enrollment);
  final EnrollmentContract enrollment;

  @override
  Widget build(BuildContext context) => _DocumentCard(
    title: '${_capitalize(enrollment.type)} #${enrollment.id}',
    subtitle:
        '${_status(enrollment.status)}${enrollment.schoolYear == null ? '' : ' • ${enrollment.schoolYear}'}',
    url: enrollment.contractUrl ?? enrollment.pdfUrl,
    icon: Icons.school_outlined,
    summaryTitle: 'Resumo da matrícula',
    summaryRows: [
      ('Status', _status(enrollment.status)),
      if (enrollment.schoolYear != null) ('Ano letivo', enrollment.schoolYear!),
      if (enrollment.className != null && enrollment.className!.isNotEmpty)
        ('Turma', enrollment.className!),
      if (enrollment.signedAt != null)
        ('Assinado em', DateFormat('dd/MM/yyyy').format(enrollment.signedAt!)),
    ],
  );
}

class _DocumentCard extends StatelessWidget {
  const _DocumentCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.summaryTitle,
    required this.summaryRows,
    this.itemRows = const [],
    this.url,
  });
  final String title, subtitle;
  final IconData icon;
  final String summaryTitle;
  final List<(String, String)> summaryRows;
  final List<(String, String)> itemRows;
  final String? url;

  @override
  Widget build(BuildContext context) {
    final canOpen = url != null && url!.isNotEmpty;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: canOpen ? () => _open(url!) : () => _showSummary(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: const Color(0xFFE8F1FF),
              child: Icon(icon, color: const Color(0xFF075CE5)),
            ),
            title: Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              canOpen
                  ? '$subtitle\nAbra para ler ou assinar'
                  : '$subtitle\nToque para ver resumo',
            ),
            isThreeLine: true,
            trailing: const Icon(Icons.chevron_right, color: Color(0xFF64748B)),
          ),
        ),
      ),
    );
  }

  void _showSummary(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  summaryTitle,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 6),
                Text(
                  title,
                  style: const TextStyle(
                    color: Color(0xFF64748B),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 16),
                ...summaryRows.map(
                  (row) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: 110,
                          child: Text(
                            row.$1,
                            style: const TextStyle(color: Color(0xFF64748B)),
                          ),
                        ),
                        Expanded(
                          child: Text(
                            row.$2,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (itemRows.isNotEmpty) ...[
                  const Divider(height: 28),
                  const Text(
                    'Itens do contrato',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                  ),
                  const SizedBox(height: 8),
                  ...itemRows.map(
                    (item) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      title: Text(
                        item.$1,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text(item.$2),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                const Text(
                  'O contrato completo para assinatura ainda não foi gerado pela plataforma. Quando existir PDF ou link de assinatura, este card abrirá o documento completo.',
                  style: TextStyle(color: Color(0xFF64748B), fontSize: 12),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _open(String url) {
    launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});
  final String title, subtitle;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
      ),
      Text(subtitle, style: const TextStyle(color: Color(0xFF64748B))),
    ],
  );
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          Icon(icon, size: 34, color: const Color(0xFF94A3B8)),
          const SizedBox(height: 8),
          Text(text, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}

String _status(String value) {
  final normalized = value.replaceAll('_', ' ');
  return normalized.isEmpty ? 'Sem status' : _capitalize(normalized);
}

String _capitalize(String value) =>
    value.isEmpty ? value : value[0].toUpperCase() + value.substring(1);
