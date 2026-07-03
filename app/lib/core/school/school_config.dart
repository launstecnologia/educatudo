import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';

final schoolConfigProvider = FutureProvider<SchoolConfig>((ref) async {
  final dio = ref.read(dioProvider);
  final response = await dio.get<dynamic>('/status');
  final raw = response.data is Map ? response.data['data'] : null;
  return SchoolConfig.fromJson(raw is Map ? raw : const {});
});

class SchoolConfig {
  const SchoolConfig({
    required this.name,
    this.slug,
    this.logoUrl,
    this.primaryColor,
  });

  final String name;
  final String? slug;
  final String? logoUrl;
  final String? primaryColor;

  factory SchoolConfig.fromJson(Map<dynamic, dynamic> json) {
    final tenant = json['tenant'] is Map ? json['tenant'] as Map : const {};
    final branding = json['branding'] is Map
        ? json['branding'] as Map
        : const {};
    return SchoolConfig(
      name:
          branding['name']?.toString() ??
          tenant['name']?.toString() ??
          'EducaTudo',
      slug: tenant['slug']?.toString(),
      logoUrl: _clean(branding['logo_url']),
      primaryColor: _clean(branding['primary_color']),
    );
  }

  static String? _clean(dynamic value) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? null : text;
  }
}

class SchoolLogo extends StatelessWidget {
  const SchoolLogo({
    required this.config,
    this.width,
    this.height,
    this.fit = BoxFit.contain,
    super.key,
  });

  final AsyncValue<SchoolConfig> config;
  final double? width;
  final double? height;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final logo = config.asData?.value.logoUrl;
    if (logo != null && logo.isNotEmpty) {
      return Image.network(
        logo,
        width: width,
        height: height,
        fit: fit,
        errorBuilder: (_, _, _) => _fallback(),
      );
    }
    return _fallback();
  }

  Widget _fallback() => Image.asset(
    'assets/images/educatudo_logo_orange.png',
    width: width,
    height: height,
    fit: fit,
  );
}
