class Parent {
  const Parent({
    required this.id,
    required this.name,
    this.email,
    this.mustChangePassword = false,
  });

  final int id;
  final String name;
  final String? email;
  final bool mustChangePassword;

  factory Parent.fromJson(Map<String, dynamic> json) => Parent(
    id: (json['id'] as num).toInt(),
    name: json['name'] as String,
    email: json['email'] as String?,
    mustChangePassword: json['must_change_password'] as bool? ?? false,
  );
}
