class LessonPlan {
  const LessonPlan({
    required this.id,
    required this.title,
    this.objectives,
    this.content,
    this.resources,
    this.assessment,
    this.notes,
    this.teacherName,
    this.subjectName,
    this.className,
    this.createdAt,
  });

  final int id;
  final String title;
  final String? objectives;
  final String? content;
  final String? resources;
  final String? assessment;
  final String? notes;
  final String? teacherName;
  final String? subjectName;
  final String? className;
  final DateTime? createdAt;

  factory LessonPlan.fromJson(Map<String, dynamic> json) => LessonPlan(
    id: (json['id'] as num).toInt(),
    title: json['title'] as String? ?? 'Plano de aula',
    objectives: _text(json['objectives']),
    content: _text(json['content']),
    resources: _text(json['resources']),
    assessment: _text(json['assessment']),
    notes: _text(json['notes']),
    teacherName: _text(json['teacher_name']),
    subjectName: _text(json['subject_name']),
    className: _text(
      json['class_name'] ??
          (json['class'] is Map ? (json['class'] as Map)['name'] : null),
    ),
    createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
  );

  static String? _text(dynamic value) {
    if (value == null) return null;
    final text = value is List ? value.join('\n') : value.toString();
    return text.trim().isEmpty ? null : text.trim();
  }
}
