import 'finance_overview.dart';

class FinancePayment {
  const FinancePayment({
    required this.invoice,
    required this.amount,
    required this.simulation,
    this.dueDate,
    this.boleto,
    this.pix,
    this.notice,
  });

  final FinanceInvoice invoice;
  final double amount;
  final bool simulation;
  final DateTime? dueDate;
  final BoletoPayment? boleto;
  final PixPayment? pix;
  final String? notice;

  factory FinancePayment.fromJson(Map<String, dynamic> json) {
    final invoiceRaw = json['invoice'] is Map
        ? (json['invoice'] as Map).map((k, v) => MapEntry('$k', v))
        : <String, dynamic>{};
    final paymentRaw = json['payment'] is Map
        ? (json['payment'] as Map).map((k, v) => MapEntry('$k', v))
        : <String, dynamic>{};

    return FinancePayment(
      invoice: FinanceInvoice.fromJson(invoiceRaw),
      amount: double.tryParse('${paymentRaw['amount'] ?? 0}') ?? 0,
      simulation: paymentRaw['simulation'] == true,
      dueDate: DateTime.tryParse(paymentRaw['due_date']?.toString() ?? ''),
      boleto: paymentRaw['boleto'] is Map
          ? BoletoPayment.fromJson(
              (paymentRaw['boleto'] as Map).map((k, v) => MapEntry('$k', v)),
            )
          : null,
      pix: paymentRaw['pix'] is Map
          ? PixPayment.fromJson(
              (paymentRaw['pix'] as Map).map((k, v) => MapEntry('$k', v)),
            )
          : null,
      notice: paymentRaw['notice']?.toString(),
    );
  }
}

class BoletoPayment {
  const BoletoPayment({this.barcode, this.line});

  final String? barcode, line;

  factory BoletoPayment.fromJson(Map<String, dynamic> json) => BoletoPayment(
    barcode: json['barcode']?.toString(),
    line: json['line']?.toString(),
  );
}

class PixPayment {
  const PixPayment({this.key, this.copyPaste, this.qrPayload});

  final String? key, copyPaste, qrPayload;

  factory PixPayment.fromJson(Map<String, dynamic> json) => PixPayment(
    key: json['key']?.toString(),
    copyPaste: json['copy_paste']?.toString(),
    qrPayload: json['qr_payload']?.toString(),
  );
}
