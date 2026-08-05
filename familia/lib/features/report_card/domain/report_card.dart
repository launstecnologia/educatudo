class ReportColumn {
  const ReportColumn({required this.code, required this.name});
  final String code, name;
  factory ReportColumn.fromJson(Map<String, dynamic> json) => ReportColumn(
    code: json['codigo']?.toString() ?? '',
    name: json['nome']?.toString() ?? json['codigo']?.toString() ?? 'Nota',
  );
}

class ReportSubject {
  const ReportSubject({required this.name, required this.grades});
  final String name;
  final Map<String, dynamic> grades;
  factory ReportSubject.fromJson(Map<String, dynamic> json) => ReportSubject(
    name: json['subject_name']?.toString() ?? 'Matéria',
    grades: json['grades'] is Map
        ? (json['grades'] as Map).map((k, v) => MapEntry('$k', v))
        : <String, dynamic>{},
  );
}

class ReportCard {
  const ReportCard({
    required this.ruleId,
    required this.title,
    required this.period,
    required this.columns,
    required this.subjects,
    this.schoolYear,
    this.term,
    this.updatedAt,
  });
  final int ruleId;
  final String title, period;
  final int? schoolYear, term;
  final DateTime? updatedAt;
  final List<ReportColumn> columns;
  final List<ReportSubject> subjects;
  factory ReportCard.fromJson(Map<String, dynamic> json) => ReportCard(
    ruleId: int.tryParse('${json['rule_id']}') ?? 0,
    title: json['title']?.toString() ?? 'Boletim',
    period: json['period']?.toString() ?? '',
    schoolYear: int.tryParse('${json['school_year']}'),
    term: int.tryParse('${json['term']}'),
    updatedAt: DateTime.tryParse(json['updated_at']?.toString() ?? ''),
    columns: _maps(json['columns']).map(ReportColumn.fromJson).toList(),
    subjects: _maps(json['subjects']).map(ReportSubject.fromJson).toList(),
  );
  static Iterable<Map<String, dynamic>> _maps(dynamic value) => value is List
      ? value.whereType<Map>().map((e) => e.map((k, v) => MapEntry('$k', v)))
      : const [];
}
