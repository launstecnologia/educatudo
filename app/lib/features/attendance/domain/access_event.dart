class AccessStatus {
  const AccessStatus({required this.isAtSchool, required this.kind, this.at});
  final bool isAtSchool;
  final String kind;
  final DateTime? at;
  factory AccessStatus.fromJson(Map<String, dynamic> json) => AccessStatus(
    isAtSchool: json['is_at_school'] == true || json['is_at_school'] == 1,
    kind: json['kind']?.toString() ?? '',
    at: DateTime.tryParse(json['at']?.toString() ?? ''),
  );
}

class AccessEvent {
  const AccessEvent({
    required this.id,
    required this.kind,
    required this.eventAt,
    required this.notified,
    this.confidence,
  });
  final int id;
  final String kind;
  final DateTime eventAt;
  final bool notified;
  final double? confidence;
  factory AccessEvent.fromJson(Map<String, dynamic> json) => AccessEvent(
    id: int.tryParse('${json['id']}') ?? 0,
    kind: json['kind']?.toString() ?? '',
    eventAt:
        DateTime.tryParse(json['event_at']?.toString() ?? '') ?? DateTime.now(),
    notified: json['notified'] == true || json['notified'] == 1,
    confidence: (json['confidence'] as num?)?.toDouble(),
  );
}

class AccessHistory {
  const AccessHistory({required this.events, this.status});
  final AccessStatus? status;
  final List<AccessEvent> events;
  factory AccessHistory.fromJson(Map<String, dynamic> json) {
    final statusRaw = json['status'];
    final eventsRaw = json['events'];
    return AccessHistory(
      status: statusRaw is Map
          ? AccessStatus.fromJson(statusRaw.map((k, v) => MapEntry('$k', v)))
          : null,
      events: eventsRaw is List
          ? eventsRaw
                .whereType<Map>()
                .map(
                  (e) =>
                      AccessEvent.fromJson(e.map((k, v) => MapEntry('$k', v))),
                )
                .toList()
          : [],
    );
  }
}
