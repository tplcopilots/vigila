enum StorageProvider {
  firebase,
  oneDrive,
  cloudServer,
}

enum AppFlavor {
  dev,
  staging,
  prod,
}

class AppConfig {
  const AppConfig._();

  static const String appFlavorName =
      String.fromEnvironment('APP_FLAVOR', defaultValue: 'dev');

  static const String activeStorageProviderName =
      String.fromEnvironment('ACTIVE_STORAGE_PROVIDER', defaultValue: 'firebase');

  static const String backendBaseUrlOverride =
      String.fromEnvironment('BACKEND_BASE_URL', defaultValue: '');
  static const String apiKey = String.fromEnvironment('API_KEY', defaultValue: '');
  static const String requestSigningSecret =
      String.fromEnvironment('REQUEST_SIGNING_SECRET', defaultValue: '');
  static const int uploadRetryCount =
      int.fromEnvironment('UPLOAD_RETRY_COUNT', defaultValue: 3);

  static AppFlavor get appFlavor {
    switch (appFlavorName) {
      case 'dev':
        return AppFlavor.dev;
      case 'staging':
        return AppFlavor.staging;
      case 'prod':
        return AppFlavor.prod;
      default:
        return AppFlavor.dev;
    }
  }

  static String get appFlavorLabel {
    switch (appFlavor) {
      case AppFlavor.dev:
        return 'DEV';
      case AppFlavor.staging:
        return 'STAGING';
      case AppFlavor.prod:
        return 'PROD';
    }
  }

  static bool get isDevFlavor => appFlavor == AppFlavor.dev;

  static String get effectiveApiKey {
    if (apiKey.isNotEmpty) {
      return apiKey;
    }
    if (isDevFlavor) {
      return 'change-me-api-key';
    }
    return '';
  }

  static String get effectiveRequestSigningSecret {
    if (requestSigningSecret.isNotEmpty) {
      return requestSigningSecret;
    }
    if (isDevFlavor) {
      return 'change-me-signing-secret';
    }
    return '';
  }

  static String get backendBaseUrl {
    if (backendBaseUrlOverride.isNotEmpty) {
      return backendBaseUrlOverride;
    }

    switch (appFlavor) {
      case AppFlavor.dev:
        return 'http://localhost:8080';
      case AppFlavor.staging:
        return 'https://staging-api.example.com';
      case AppFlavor.prod:
        return 'https://api.example.com';
    }
  }

  static StorageProvider get activeStorageProvider {
    switch (activeStorageProviderName) {
      case 'firebase':
        return StorageProvider.firebase;
      case 'oneDrive':
        return StorageProvider.oneDrive;
      case 'cloudServer':
        return StorageProvider.cloudServer;
      default:
        return StorageProvider.firebase;
    }
  }

  static String get reportEndpoint => '$backendBaseUrl/api/report';
  static String get chunkUploadEndpoint => '$backendBaseUrl/api/upload/chunk';
  static String get finalizeJoinEndpoint => '$backendBaseUrl/api/upload/finalize';
  static String get uploadStatusEndpoint => '$backendBaseUrl/api/upload/status';
}
