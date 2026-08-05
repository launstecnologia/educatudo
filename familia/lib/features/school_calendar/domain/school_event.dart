class SchoolEvent {
  const SchoolEvent({
    required this.id,
    required this.title,
    required this.category,
    required this.priority,
    required this.startsAt,
    required this.allDay,
    required this.status,
    required this.isRead,
    this.description,
    this.location,
    this.endsAt,
  });
  final int id;
  final String title, category, priority, status;
  final String? description, location;
  final DateTime startsAt;
  final DateTime? endsAt;
  final bool allDay, isRead;
  factory SchoolEvent.fromJson(Map<String, dynamic> json) => SchoolEvent(
    id: int.tryParse('${json['id']}') ?? 0,
    title: json['title']?.toString() ?? 'Evento',
    description: json['description']?.toString(),
    category: json['category']?.toString() ?? 'evento',
    priority: json['priority']?.toString() ?? 'normal',
    location: json['location']?.toString(),
    startsAt:
        DateTime.tryParse(json['starts_at']?.toString() ?? '') ??
        DateTime.now(),
    endsAt: DateTime.tryParse(json['ends_at']?.toString() ?? ''),
    allDay: json['all_day'] == true || json['all_day'] == 1,
    status: json['status']?.toString() ?? 'publicado',
    isRead: json['is_read'] == true || json['is_read'] == 1,
  );
}
