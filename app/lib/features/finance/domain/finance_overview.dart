class FinanceOverview {
  const FinanceOverview({
    required this.summary,
    required this.invoices,
    required this.contracts,
    required this.enrollments,
  });

  final FinanceSummary summary;
  final List<FinanceInvoice> invoices;
  final List<FinanceContract> contracts;
  final List<EnrollmentContract> enrollments;

  factory FinanceOverview.fromJson(Map<String, dynamic> json) =>
      FinanceOverview(
        summary: FinanceSummary.fromJson(_map(json['summary'])),
        invoices: _list(json['invoices']).map(FinanceInvoice.fromJson).toList(),
        contracts: _list(
          json['contracts'],
        ).map(FinanceContract.fromJson).toList(),
        enrollments: _list(
          json['enrollments'],
        ).map(EnrollmentContract.fromJson).toList(),
      );

  static Map<String, dynamic> _map(dynamic value) => value is Map
      ? value.map((key, value) => MapEntry('$key', value))
      : <String, dynamic>{};

  static Iterable<Map<String, dynamic>> _list(dynamic value) => value is List
      ? value.whereType<Map>().map((e) => e.map((k, v) => MapEntry('$k', v)))
      : const [];
}

class FinanceSummary {
  const FinanceSummary({
    required this.pendingCount,
    required this.pendingAmount,
    required this.overdueAmount,
    required this.contractsCount,
  });

  final int pendingCount;
  final double pendingAmount;
  final double overdueAmount;
  final int contractsCount;

  factory FinanceSummary.fromJson(Map<String, dynamic> json) => FinanceSummary(
    pendingCount: int.tryParse('${json['pending_count'] ?? 0}') ?? 0,
    pendingAmount: double.tryParse('${json['pending_amount'] ?? 0}') ?? 0,
    overdueAmount: double.tryParse('${json['overdue_amount'] ?? 0}') ?? 0,
    contractsCount: int.tryParse('${json['contracts_count'] ?? 0}') ?? 0,
  );
}

class FinanceInvoice {
  const FinanceInvoice({
    required this.id,
    required this.source,
    required this.description,
    required this.category,
    required this.amount,
    required this.status,
    this.contractId,
    this.installmentNumber,
    this.dueDate,
    this.paidAt,
    this.paidAmount,
    this.barcode,
    this.paymentUrl,
  });

  final int id;
  final String source, description, category, status;
  final int? contractId, installmentNumber;
  final double amount;
  final double? paidAmount;
  final DateTime? dueDate, paidAt;
  final String? barcode, paymentUrl;

  bool get isPaid => status == 'pago';
  bool get isOverdue => status == 'vencido';

  factory FinanceInvoice.fromJson(Map<String, dynamic> json) => FinanceInvoice(
    id: int.tryParse('${json['id'] ?? 0}') ?? 0,
    source: json['source']?.toString() ?? '',
    contractId: int.tryParse('${json['contract_id'] ?? ''}'),
    installmentNumber: int.tryParse('${json['installment_number'] ?? ''}'),
    description: json['description']?.toString() ?? 'Fatura',
    category: json['category']?.toString() ?? '',
    amount: double.tryParse('${json['amount'] ?? 0}') ?? 0,
    paidAmount: double.tryParse('${json['paid_amount'] ?? ''}'),
    dueDate: DateTime.tryParse(json['due_date']?.toString() ?? ''),
    paidAt: DateTime.tryParse(json['paid_at']?.toString() ?? ''),
    status: json['status']?.toString() ?? '',
    barcode: json['barcode']?.toString(),
    paymentUrl: json['payment_url']?.toString(),
  );
}

class FinanceContract {
  const FinanceContract({
    required this.id,
    required this.status,
    required this.netAmount,
    this.schoolYear,
    this.paymentPlan,
    this.enrollmentType,
    this.enrollmentStatus,
    this.contractUrl,
    this.pdfUrl,
    this.signedAt,
  });

  final int id;
  final String status;
  final String? schoolYear, paymentPlan, enrollmentType, enrollmentStatus;
  final double netAmount;
  final String? contractUrl, pdfUrl;
  final DateTime? signedAt;

  factory FinanceContract.fromJson(Map<String, dynamic> json) =>
      FinanceContract(
        id: int.tryParse('${json['id'] ?? 0}') ?? 0,
        status: json['status']?.toString() ?? '',
        schoolYear: json['school_year']?.toString(),
        paymentPlan: json['payment_plan']?.toString(),
        enrollmentType: json['enrollment_type']?.toString(),
        enrollmentStatus: json['enrollment_status']?.toString(),
        netAmount: double.tryParse('${json['net_amount'] ?? 0}') ?? 0,
        contractUrl: json['contract_url']?.toString(),
        pdfUrl: json['pdf_url']?.toString(),
        signedAt: DateTime.tryParse(json['signed_at']?.toString() ?? ''),
      );
}

class EnrollmentContract {
  const EnrollmentContract({
    required this.id,
    required this.type,
    required this.status,
    this.schoolYear,
    this.className,
    this.contractUrl,
    this.pdfUrl,
    this.signedAt,
  });

  final int id;
  final String type, status;
  final String? schoolYear, className, contractUrl, pdfUrl;
  final DateTime? signedAt;

  factory EnrollmentContract.fromJson(Map<String, dynamic> json) =>
      EnrollmentContract(
        id: int.tryParse('${json['id'] ?? 0}') ?? 0,
        type: json['type']?.toString() ?? '',
        status: json['status']?.toString() ?? '',
        schoolYear: json['school_year']?.toString(),
        className: json['class_name']?.toString(),
        contractUrl: json['contract_url']?.toString(),
        pdfUrl: json['pdf_url']?.toString(),
        signedAt: DateTime.tryParse(json['signed_at']?.toString() ?? ''),
      );
}
