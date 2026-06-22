class Exam {
  const Exam({
    required this.id,
    required this.title,
    required this.status,
    this.grade,
    this.completedAt,
    this.subjectName,
    this.questionCount = 0,
    this.correctCount = 0,
    this.incorrectCount = 0,
    this.pendingCount = 0,
    this.accuracyPercent = 0,
    this.blockId,
    this.blockTitle,
    this.blockModelId,
    this.blockModelName,
    this.blockDate,
    this.term,
    this.schoolYear,
  });

  final int id;
  final String title;
  final String status;
  final double? grade;
  final DateTime? completedAt;
  final String? subjectName;
  final int questionCount;
  final int correctCount;
  final int incorrectCount;
  final int pendingCount;
  final double accuracyPercent;
  final int? blockId;
  final String? blockTitle;
  final int? blockModelId;
  final String? blockModelName;
  final DateTime? blockDate;
  final String? term;
  final int? schoolYear;

  String get groupKey => blockModelId != null
      ? 'model:$blockModelId:${term ?? 0}:${schoolYear ?? 0}'
      : blockId != null
      ? 'block:$blockId:${term ?? 0}'
      : 'exam:${title.trim()}:${completedAt?.toIso8601String().split('T').first ?? id}';

  String get groupTitle {
    for (final value in [blockModelName, blockTitle, title]) {
      if (value?.trim().isNotEmpty == true) {
        final suffix = term != null && term!.isNotEmpty && term != '0'
            ? ' — $termº Bimestre'
            : '';
        return '${value!.trim()}$suffix';
      }
    }
    return 'Grupo de provas';
  }

  String get columnKey => blockId != null ? 'block:$blockId' : 'exam:$id';

  factory Exam.fromJson(Map<String, dynamic> json) => Exam(
    id: (json['id'] as num).toInt(),
    title: json['title'] as String? ?? 'Prova',
    status: json['status'] as String? ?? '',
    grade: (json['grade'] as num?)?.toDouble(),
    completedAt: DateTime.tryParse(json['completed_at'] as String? ?? ''),
    subjectName: json['subject_name'] as String?,
    questionCount: (json['question_count'] as num? ?? 0).toInt(),
    correctCount: (json['correct_count'] as num? ?? 0).toInt(),
    incorrectCount: (json['incorrect_count'] as num? ?? 0).toInt(),
    pendingCount: (json['pending_count'] as num? ?? 0).toInt(),
    accuracyPercent: (json['accuracy_percent'] as num? ?? 0).toDouble(),
    blockId: (json['block_id'] as num?)?.toInt(),
    blockTitle: json['block_title'] as String?,
    blockModelId: (json['block_model_id'] as num?)?.toInt(),
    blockModelName: json['block_model_name'] as String?,
    blockDate: DateTime.tryParse(json['block_date'] as String? ?? ''),
    term: json['term']?.toString(),
    schoolYear: (json['school_year'] as num?)?.toInt(),
  );
}
