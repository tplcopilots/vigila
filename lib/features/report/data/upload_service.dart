import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:flutter_vigi/core/app_config.dart';
import 'package:flutter_vigi/features/report/domain/theft_report.dart';
import 'package:http/http.dart' as http;
import 'package:uuid/uuid.dart';

class UploadService {
  const UploadService();

  static const _uuid = Uuid();

  Future<void> verifyBackendReachable() async {
    final uri = _resolveUri('${AppConfig.backendBaseUrl}/health');

    try {
      final response = await http.get(uri).timeout(const Duration(seconds: 5));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception('Health check failed with ${response.statusCode}');
      }
    } on Exception catch (error) {
      throw Exception(
        'Backend is not reachable at $uri. $error. ${_networkHint(uri)}',
      );
    }
  }

  Future<void> uploadReport({
    required TheftReport report,
    required String reportId,
  }) async {
    final body = jsonEncode({
      'reportId': reportId,
      'report': report.toJson(),
      'provider': AppConfig.activeStorageProvider.name,
    });

    await _post(
      endpoint: AppConfig.reportEndpoint,
      body: body,
    );
  }

  Future<void> uploadChunk(VideoChunk chunk) async {
    final encodedChunk = base64Encode(chunk.bytes);

    final body = jsonEncode({
      'fileId': chunk.fileId,
      'chunkIndex': chunk.chunkIndex,
      'totalChunks': chunk.totalChunks,
      'payload': encodedChunk,
      'provider': AppConfig.activeStorageProvider.name,
    });

    try {
      await _post(
        endpoint: AppConfig.chunkUploadEndpoint,
        body: body,
      );
    } on UploadException catch (error) {
      if (error.statusCode == 409) {
        return;
      }
      rethrow;
    }
  }

  Future<void> finalizeUpload({
    required String fileId,
    required int totalChunks,
  }) async {
    final body = jsonEncode({
      'fileId': fileId,
      'totalChunks': totalChunks,
      'provider': AppConfig.activeStorageProvider.name,
      'action': 'join_chunks',
    });

    await _post(
      endpoint: AppConfig.finalizeJoinEndpoint,
      body: body,
    );
  }

  Future<UploadStatus> getUploadStatus({
    required String fileId,
    required int totalChunks,
  }) async {
    final uri = _resolveUri(
      '${AppConfig.uploadStatusEndpoint}?fileId=$fileId&totalChunks=$totalChunks',
    );

    final response = await _getWithRetry(
      uri: uri,
      headers: _authHeaders(uri: uri, method: 'GET', body: ''),
    );

    final payload = jsonDecode(response.body) as Map<String, dynamic>;
    final uploadedIndexes = (payload['uploadedIndexes'] as List<dynamic>? ?? [])
        .map((value) => value as int)
        .toList();
    final missingIndexes = (payload['missingIndexes'] as List<dynamic>? ?? [])
        .map((value) => value as int)
        .toList();

    return UploadStatus(
      uploadedIndexes: uploadedIndexes,
      missingIndexes: missingIndexes,
    );
  }

  Future<void> _post({
    required String endpoint,
    required String body,
  }) async {
    final uri = _resolveUri(endpoint);
    final headers = _authHeaders(uri: uri, method: 'POST', body: body);

    final response = await _postWithRetry(
      uri: uri,
      headers: headers,
      body: body,
    );

    _throwIfHttpError(response);
  }

  Future<http.Response> _postWithRetry({
    required Uri uri,
    required Map<String, String> headers,
    required String body,
  }) async {
    late Object lastError;
    for (var attempt = 0; attempt <= AppConfig.uploadRetryCount; attempt++) {
      try {
        final response = await http.post(uri, headers: headers, body: body);
        if (_isRetryableStatusCode(response.statusCode) &&
            attempt < AppConfig.uploadRetryCount) {
          await Future<void>.delayed(_backoffDelay(attempt));
          continue;
        }
        return response;
      } on SocketException catch (error) {
        lastError = error;
      } on http.ClientException catch (error) {
        lastError = error;
      } on HttpException catch (error) {
        lastError = error;
      }

      if (attempt < AppConfig.uploadRetryCount) {
        await Future<void>.delayed(_backoffDelay(attempt));
      }
    }

    throw Exception(
      'Upload failed after retries: $lastError. ${_networkHint(uri)}',
    );
  }

  Future<http.Response> _getWithRetry({
    required Uri uri,
    required Map<String, String> headers,
  }) async {
    late Object lastError;
    for (var attempt = 0; attempt <= AppConfig.uploadRetryCount; attempt++) {
      try {
        final response = await http.get(uri, headers: headers);
        if (_isRetryableStatusCode(response.statusCode) &&
            attempt < AppConfig.uploadRetryCount) {
          await Future<void>.delayed(_backoffDelay(attempt));
          continue;
        }
        _throwIfHttpError(response);
        return response;
      } on SocketException catch (error) {
        lastError = error;
      } on http.ClientException catch (error) {
        lastError = error;
      } on HttpException catch (error) {
        lastError = error;
      }

      if (attempt < AppConfig.uploadRetryCount) {
        await Future<void>.delayed(_backoffDelay(attempt));
      }
    }

    throw Exception(
      'Request failed after retries: $lastError. ${_networkHint(uri)}',
    );
  }

  Duration _backoffDelay(int attempt) {
    return Duration(milliseconds: 500 * (attempt + 1));
  }

  Uri _resolveUri(String endpoint) {
    final uri = Uri.parse(endpoint);

    if (Platform.isAndroid &&
        (uri.host == 'localhost' || uri.host == '127.0.0.1')) {
      return uri.replace(host: '10.0.2.2');
    }

    return uri;
  }

  String _networkHint(Uri uri) {
    if (uri.host == 'localhost' ||
        uri.host == '127.0.0.1' ||
        uri.host == '10.0.2.2') {
      return 'Start backend with: cd backend && npm run start. Android emulator uses 10.0.2.2. Real device must use your computer LAN IP via BACKEND_BASE_URL.';
    }
    return 'Verify backend server is running and reachable from this device/network.';
  }

  Map<String, String> _authHeaders({
    required Uri uri,
    required String method,
    required String body,
  }) {
    final apiKey = AppConfig.effectiveApiKey;
    final signingSecret = AppConfig.effectiveRequestSigningSecret;

    if (apiKey.isEmpty || signingSecret.isEmpty) {
      throw StateError(
        'Missing secure config for ${AppConfig.appFlavorLabel}. Provide API_KEY and REQUEST_SIGNING_SECRET using --dart-define or --dart-define-from-file.',
      );
    }

    final timestamp = DateTime.now().millisecondsSinceEpoch.toString();
    final nonce = _uuid.v4();
    final bodyHash = _sha256Hex(body);
    final canonical = [
      method.toUpperCase(),
      uri.path,
      timestamp,
      nonce,
      bodyHash,
    ].join('\n');
    final signature = _hmacSha256Hex(signingSecret, canonical);

    return {
      'content-type': 'application/json',
      'x-api-key': apiKey,
      'x-timestamp': timestamp,
      'x-nonce': nonce,
      'x-signature': signature,
    };
  }

  String _sha256Hex(String value) {
    final digest = sha256.convert(utf8.encode(value));
    return digest.toString();
  }

  String _hmacSha256Hex(String secret, String message) {
    final hmac = Hmac(sha256, utf8.encode(secret));
    final digest = hmac.convert(utf8.encode(message));
    return digest.toString();
  }

  bool _isRetryableStatusCode(int statusCode) {
    return statusCode == 408 || statusCode == 429 || statusCode >= 500;
  }

  void _throwIfHttpError(http.Response response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw UploadException(response.statusCode, response.body);
    }
  }
}

class UploadStatus {
  const UploadStatus({
    required this.uploadedIndexes,
    required this.missingIndexes,
  });

  final List<int> uploadedIndexes;
  final List<int> missingIndexes;
}

class UploadException implements Exception {
  const UploadException(this.statusCode, this.responseBody);

  final int statusCode;
  final String responseBody;

  @override
  String toString() {
    return 'Upload failed ($statusCode): $responseBody';
  }
}
