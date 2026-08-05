class Student {
  const Student({
    required this.id,
    required this.name,
    this.photoUrl,
    this.className,
    this.classGrade,
  });

  final int id;
  final String name;
  final String? photoUrl;
  final String? className;
  final String? classGrade;

  String get classLabel => [
    classGrade,
    className,
  ].whereType<String>().where((value) => value.isNotEmpty).join(' - ');

  factory Student.fromJson(Map<String, dynamic> json) {
    final classData = json['class'] as Map<String, dynamic>?;
    final photo =
        json['photo_url'] ??
        json['foto_url'] ??
        json['avatar_url'] ??
        json['image_url'] ??
        json['photo'];
    return Student(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      photoUrl: photo is String && photo.trim().isNotEmpty
          ? photo.trim()
          : null,
      className: classData?['name'] as String?,
      classGrade: classData?['grade'] as String?,
    );
  }
}
