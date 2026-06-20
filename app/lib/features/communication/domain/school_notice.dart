class SchoolNotice {
  const SchoolNotice({
    required this.id,
    required this.title,
    required this.content,
    required this.publishedAt,
    this.authorName,
    this.subjectName,
  });

  final int id;
  final String title;
  final String content;
  final DateTime? publishedAt;
  final String? authorName;
  final String? subjectName;

  factory SchoolNotice.fromJson(Map<String, dynamic> json) => SchoolNotice(
    id: int.tryParse('${json['id']}') ?? 0,
    title: json['title']?.toString() ?? json['titulo']?.toString() ?? 'Recado',
    content: json['content']?.toString() ?? json['conteudo']?.toString() ?? '',
    publishedAt: DateTime.tryParse(
      json['published_at']?.toString() ??
          json['data_publicacao']?.toString() ??
          '',
    ),
    authorName:
        json['author_name']?.toString() ?? json['autor_nome']?.toString(),
    subjectName:
        json['subject_name']?.toString() ?? json['materia_nome']?.toString(),
  );
}
