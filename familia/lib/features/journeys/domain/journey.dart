class Journey {
  const Journey({
    required this.id,
    required this.title,
    required this.status,
    required this.progressPercent,
    this.createdAt,
    this.teacherName,
    this.subjectName,
    this.totalModules = 0,
    this.completedModules = 0,
    this.started = false,
    this.completed = false,
  });

  final int id;
  final String title;
  final String status;
  final double progressPercent;
  final DateTime? createdAt;
  final String? teacherName;
  final String? subjectName;
  final int totalModules;
  final int completedModules;
  final bool started;
  final bool completed;

  factory Journey.fromJson(Map<String, dynamic> json) => Journey(
    id: (json['id'] as num).toInt(),
    title: json['title'] as String? ?? 'Jornada',
    status: json['status'] as String? ?? '',
    progressPercent: (json['progress_percent'] as num? ?? 0).toDouble(),
    createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
    teacherName: json['teacher_name'] as String?,
    subjectName: json['subject_name'] as String?,
    totalModules: (json['total_modules'] as num? ?? 0).toInt(),
    completedModules: (json['completed_modules'] as num? ?? 0).toInt(),
    started: json['started'] == true || json['started'] == 1,
    completed: json['completed'] == true || json['completed'] == 1,
  );
}
