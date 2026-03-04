# Flutter Upload Production Checklist

## Required config (`--dart-define` or flavor json)

- `BACKEND_BASE_URL`
- `API_KEY`
- `REQUEST_SIGNING_SECRET`
- `ACTIVE_STORAGE_PROVIDER` (`firebase` | `oneDrive` | `cloudServer`)
- `UPLOAD_RETRY_COUNT`

## API sequence to keep

1. `POST /api/report`
2. `POST /api/upload/init`
3. Upload missing chunks with `POST /api/upload/chunk`
4. Optional verify: `GET /api/upload/status`
5. `POST /api/upload/finalize` with `fileName` and `mimeType`

## Reliability rules

- Keep `fileId` stable for retries/resume.
- On app restart, call `upload/init` first; merge `uploadedIndexes` + `missingIndexes`.
- Treat `409` on chunk upload as already uploaded and continue.
- Retry network/5xx/429 with backoff (already implemented in `UploadService`).

## Security rules

- Send `x-api-key`, `x-timestamp`, `x-nonce`, `x-signature` on every `/api/*` request.
- Canonical signature string:
  - `METHOD`
  - `PATH`
  - `TIMESTAMP`
  - `NONCE`
  - `SHA256(body)`
- Reject stale timestamp and replayed nonce on backend (middleware already active).

## Backend reflection checks

- Finalized files appear in dashboard list/history (`/upload/files`, `/upload/history`).
- Logs appear in dashboard log tab (`/upload/logs`).
- Analytics update from same storage source (`/upload/analytics`).

## Smoke test

- Upload one small test file from Flutter.
- Kill app mid-upload and relaunch.
- Re-submit and verify resume from missing chunks only.
- Confirm finalize success and dashboard visibility.
