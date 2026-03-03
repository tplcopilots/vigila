# Vigilance Upload Backend

This backend accepts theft report metadata and chunked video uploads from the Flutter vigilance app.

## Features

- Receives report metadata at `POST /api/report`
- Receives video chunks at `POST /api/upload/chunk`
- Joins all chunks into one final video at `POST /api/upload/finalize`
- Stores final video in one of three providers based on active flag:
  - `firebase`
  - `oneDrive`
  - `cloudServer`

## 1) Setup

```bash
cd backend
cp .env.example .env
npm install
npm run start
```

Server starts at `http://localhost:8080` by default.

## 2) Environment

Configure `.env`:

- `ACTIVE_STORAGE_PROVIDER=firebase|oneDrive|cloudServer`
- `API_KEY` required request key sent in `x-api-key` header
- `REQUEST_SIGNING_SECRET` shared secret to generate HMAC signatures
- `SIGNATURE_MAX_SKEW_MS` max allowed timestamp drift for anti-replay
- `FIREBASE_STORAGE_BUCKET` for Firebase storage
- `GOOGLE_APPLICATION_CREDENTIALS` path to Firebase service account JSON
- `ONEDRIVE_ACCESS_TOKEN` for Microsoft Graph API
- `ONEDRIVE_FOLDER` target folder in OneDrive

## 3) Flutter app runtime setup

Pass settings using `--dart-define`:

```bash
flutter run \
  --dart-define=BACKEND_BASE_URL=http://localhost:8080 \
  --dart-define=APP_FLAVOR=dev \
  --dart-define=ACTIVE_STORAGE_PROVIDER=firebase \
  --dart-define=API_KEY=change-me-api-key \
  --dart-define=REQUEST_SIGNING_SECRET=change-me-signing-secret
```

You can also use flavor files:

```bash
flutter run --dart-define-from-file=config/flavors/dev.json
```

For CI/release builds use secure environment variables instead of hardcoded values.

The app sends the active provider in upload/finalize payload, and backend can also fallback to `.env` default provider.

## API Contract

### `POST /api/report`

```json
{
  "reportId": "uuid",
  "provider": "firebase",
  "report": {
    "reporterName": "...",
    "meterNumber": "..."
  }
}
```

### `POST /api/upload/chunk`

```json
{
  "fileId": "uuid",
  "chunkIndex": 0,
  "totalChunks": 8,
  "provider": "firebase",
  "payload": "<base64-string>"
}
```

### `GET /api/upload/status?fileId=<id>&totalChunks=<count>`

Returns already uploaded chunk indexes and missing chunk indexes. This is used by client retry/resume.

### `POST /api/upload/finalize`

```json
{
  "fileId": "uuid",
  "totalChunks": 8,
  "provider": "firebase",
  "action": "join_chunks"
}
```

Returns storage details after merged upload.

## Security

All `/api/*` routes require `x-api-key` request header matching `.env` `API_KEY`.

All `/api/*` routes also require these signed headers:

- `x-timestamp` (epoch milliseconds)
- `x-nonce` (unique per request)
- `x-signature` (HMAC SHA-256)

Canonical signature format:

`METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY)`

Replay protection:

- Requests older/newer than `SIGNATURE_MAX_SKEW_MS` are rejected.
- Reused nonces inside validity window are rejected.
