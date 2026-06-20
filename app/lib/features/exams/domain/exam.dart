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
  );
}
