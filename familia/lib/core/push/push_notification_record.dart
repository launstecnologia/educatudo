class PushNotificationRecord {
  const PushNotificationRecord({
    required this.id,
    required this.title,
    required this.body,
    required this.receivedAt,
    this.route,
    this.read = false,
    this.data = const {},
  });

  final String id;
  final String title;
  final String body;
  final DateTime receivedAt;
  final String? route;
  final bool read;
  final Map<String, String> data;

  PushNotificationRecord copyWith({bool? read}) => PushNotificationRecord(
    id: id,
    title: title,
    body: body,
    receivedAt: receivedAt,
    route: route,
    read: read ?? this.read,
    data: data,
  );

  factory PushNotificationRecord.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'];
    return PushNotificationRecord(
      id: json['id'].toString(),
      title: json['title']?.toString() ?? 'EducaTudo',
      body: json['body']?.toString() ?? '',
      receivedAt:
          DateTime.tryParse(json['received_at']?.toString() ?? '') ??
          DateTime.now(),
      route: json['route']?.toString(),
      read: json['read'] == true || json['read'] == 1,
      data: rawData is Map
          ? rawData.map((key, value) => MapEntry('$key', '$value'))
          : const {},
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'title': title,
    'body': body,
    'received_at': receivedAt.toIso8601String(),
    'route': route,
    'read': read,
    'data': data,
  };
}
