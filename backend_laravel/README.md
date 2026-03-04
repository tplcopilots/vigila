# Vigilance Laravel Backend (Chunk Upload + Recovery)

Laravel backend for meter-theft video uploads with chunk recovery and resume support.

## Features

- `POST /api/report` saves report metadata.
- `POST /api/upload/chunk` uploads one video chunk.
- `GET /api/upload/status` returns uploaded/missing chunk indexes.
- `POST /api/upload/finalize` joins chunks and uploads final file to selected provider.
- Replay-safe signed API requests (`x-api-key`, timestamp, nonce, HMAC signature).

## Start

```bash
cd backend_laravel
cp .env.example .env
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8080
```

Health endpoint for Flutter preflight:

- `GET /health`

## API Documentation

- Complete API docs index: `docs/README.md`
- Requests: `docs/upload_api_requests.md`
- Responses: `docs/upload_api_responses.md`
- Signed curl examples: `docs/upload_api_curl_examples.md`
- Postman collection: `docs/upload_api.postman_collection.json`

## Flutter Compatibility

Use same payload contract already used in Flutter app:

- `reportId`, `report`, `provider`
- `fileId`, `chunkIndex`, `totalChunks`, `payload`
- `fileId`, `totalChunks`, `provider`, `action`

## Security Headers

All `/api/*` endpoints require:

- `x-api-key`
- `x-timestamp` (epoch ms)
- `x-nonce`
- `x-signature` (HMAC SHA256)

Canonical string:

`METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY)`

Replay protection:

- Timestamp must be within `SIGNATURE_MAX_SKEW_MS`.
- Reused nonce is rejected.

## Security / Audit Logs

All key upload events are logged for security tracking:

- auth failures/success (`api key`, timestamp, signature, replay)
- report save
- chunk upload, duplicate chunk attempts
- status checks
- finalize success/failure and storage location

Audit log file:

- `storage/logs/upload_audit-YYYY-MM-DD.log`

Retention and level can be controlled with:

- `LOG_UPLOAD_AUDIT_LEVEL`
- `LOG_UPLOAD_AUDIT_DAYS`

## Chunk Recovery Flow

1. Client uploads chunks with index.
2. If network fails, call `GET /api/upload/status`.
3. Resume uploading only `missingIndexes`.
4. Call `POST /api/upload/finalize` after all chunks are present.

## Provider Routing

Set active provider in request (`provider`) or fallback env `ACTIVE_STORAGE_PROVIDER`.

Supported provider values:

- `firebase`
- `oneDrive`
- `cloudServer`

Notes:

- `cloudServer` stores final file in `storage/app/cloud-server`.
- `oneDrive` requires `ONEDRIVE_ACCESS_TOKEN`.
- `firebase` uses `FIREBASE_STORAGE_BUCKET` + `FIREBASE_ACCESS_TOKEN` for real upload, else stores in local fallback folder.
