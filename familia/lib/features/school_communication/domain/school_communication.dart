class CommunicationAttachment {
  const CommunicationAttachment({required this.name, required this.url});
  final String name;
  final String url;
  factory CommunicationAttachment.fromJson(Map<String, dynamic> json) =>
      CommunicationAttachment(
        name: json['name']?.toString() ?? 'Anexo',
        url: json['url']?.toString() ?? '',
      );
}

class CommunicationReply {
  const CommunicationReply({
    required this.id,
    required this.senderType,
    required this.message,
    this.createdAt,
  });
  final int id;
  final String senderType;
  final String message;
  final DateTime? createdAt;
  factory CommunicationReply.fromJson(Map<String, dynamic> json) =>
      CommunicationReply(
        id: int.tryParse('${json['id']}') ?? 0,
        senderType: json['sender_type']?.toString() ?? '',
        message: json['message']?.toString() ?? '',
        createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      );
}

class SchoolCommunication {
  const SchoolCommunication({
    required this.id,
    required this.title,
    required this.content,
    required this.priority,
    required this.allowReplies,
    required this.isRead,
    required this.replyCount,
    this.publishedAt,
    this.attachments = const [],
    this.replies = const [],
  });
  final int id;
  final String title;
  final String content;
  final String priority;
  final bool allowReplies;
  final bool isRead;
  final int replyCount;
  final DateTime? publishedAt;
  final List<CommunicationAttachment> attachments;
  final List<CommunicationReply> replies;

  factory SchoolCommunication.fromJson(
    Map<String, dynamic> json,
  ) => SchoolCommunication(
    id: int.tryParse('${json['id']}') ?? 0,
    title: json['title']?.toString() ?? 'Comunicação',
    content: json['content']?.toString() ?? '',
    priority: json['priority']?.toString() ?? 'normal',
    allowReplies: json['allow_replies'] == true || json['allow_replies'] == 1,
    isRead: json['is_read'] == true || json['is_read'] == 1,
    replyCount: int.tryParse('${json['reply_count']}') ?? 0,
    publishedAt: DateTime.tryParse(json['published_at']?.toString() ?? ''),
    attachments: _maps(
      json['attachments'],
    ).map(CommunicationAttachment.fromJson).toList(),
    replies: _maps(json['replies']).map(CommunicationReply.fromJson).toList(),
  );

  static Iterable<Map<String, dynamic>> _maps(dynamic value) => value is List
      ? value.whereType<Map>().map(
          (item) => item.map((key, value) => MapEntry('$key', value)),
        )
      : const [];
}
