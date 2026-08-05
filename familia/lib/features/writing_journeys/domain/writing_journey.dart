class WritingJourney {
  const WritingJourney({
    required this.id,
    required this.journeyTitle,
    required this.theme,
    this.description,
    this.dueAt,
    this.submissionStatus,
    this.grade,
  });

  final int id;
  final String journeyTitle;
  final String theme;
  final String? description;
  final DateTime? dueAt;
  final String? submissionStatus;
  final double? grade;

  factory WritingJourney.fromJson(Map<String, dynamic> json) => WritingJourney(
    id: (json['id'] as num).toInt(),
    journeyTitle: json['journey_title'] as String? ?? 'Jornada de redação',
    theme: json['theme'] as String? ?? 'Tema não informado',
    description: json['description'] as String?,
    dueAt: DateTime.tryParse(json['due_at'] as String? ?? ''),
    submissionStatus: json['submission_status'] as String?,
    grade: (json['grade'] as num?)?.toDouble(),
  );
}

class Essay {
  const Essay({
    required this.id,
    required this.theme,
    required this.isDraft,
    this.grade,
    this.feedback,
    this.createdAt,
    this.correctedAt,
  });

  final int id;
  final String theme;
  final bool isDraft;
  final double? grade;
  final String? feedback;
  final DateTime? createdAt;
  final DateTime? correctedAt;

  factory Essay.fromJson(Map<String, dynamic> json) => Essay(
    id: (json['id'] as num).toInt(),
    theme: json['theme'] as String? ?? 'Redação',
    isDraft: json['is_draft'] as bool? ?? false,
    grade: (json['grade'] as num?)?.toDouble(),
    feedback: json['feedback'] as String?,
    createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
    correctedAt: DateTime.tryParse(json['corrected_at'] as String? ?? ''),
  );
}
