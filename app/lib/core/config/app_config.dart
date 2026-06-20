enum AppEnvironment { dev, staging, prod }

class AppConfig {
  const AppConfig._();

  static const environmentName = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'dev',
  );

  static AppEnvironment get environment => switch (environmentName) {
    'staging' => AppEnvironment.staging,
    'prod' => AppEnvironment.prod,
    _ => AppEnvironment.dev,
  };

  static const apiOrigin = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static String get apiBaseUrl {
    final origin = apiOrigin.endsWith('/')
        ? apiOrigin.substring(0, apiOrigin.length - 1)
        : apiOrigin;
    return origin.endsWith('/api/v1') ? origin : '$origin/api/v1';
  }

  static const enablePush = bool.fromEnvironment(
    'ENABLE_PUSH',
    defaultValue: false,
  );
}
