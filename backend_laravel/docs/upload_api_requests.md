# Upload API Requests

This document contains request details only.

## Base URL

- `http://localhost:8080`

## Signed Headers (required for all `/api/*` requests)

- `content-type: application/json`
- `x-api-key: <api-key>`
- `x-timestamp: <unix-ms-timestamp>`
- `x-nonce: <unique-random-string>`
- `x-signature: <hmac-sha256-hex>`

Signature canonical format:

```text
METHOD
PATH
TIMESTAMP
NONCE
SHA256(body)
```

---

## 1) Health

### GET `/health`

Request body: none

---

## 2) Save Report

### POST `/api/report`

Request JSON:

```json
{
  "reportId": "demo-report-001",
  "provider": "firebase",
  "report": {
    "reporterName": "Inspector 1",
    "phone": "+91-9000000000",
    "meterNumber": "MTR-1001",
    "consumerName": "Consumer Name",
    "location": "Sector 10",
    "incidentDate": "2026-03-05T00:00:00.000Z",
    "theftType": "Meter Bypass",
    "details": "Suspicious bypass found",
    "latitude": 28.6139,
    "longitude": 77.209
  }
}
```

Required fields:

- `reportId` (string)
- `report` (object)

Optional fields:

- `provider` (`firebase` | `oneDrive` | `cloudServer`)

---

## 3) Upload Init (resume-aware)

### POST `/api/upload/init`

Request JSON:

```json
{
  "fileId": "demo-file-001",
  "totalChunks": 3,
  "provider": "firebase"
}
```

Required fields:

- `totalChunks` (integer, min `1`)

Optional fields:

- `fileId` (string; if omitted backend creates one)
- `provider` (`firebase` | `oneDrive` | `cloudServer`)

---

## 4) Upload Chunk

### POST `/api/upload/chunk`

Request JSON:

```json
{
  "fileId": "demo-file-001",
  "chunkIndex": 0,
  "totalChunks": 3,
  "provider": "firebase",
  "payload": "SGVsbG8gQ2h1bms="
}
```

Required fields:

- `fileId` (string)
- `chunkIndex` (integer, min `0`)
- `totalChunks` (integer, min `1`)
- `payload` (base64-encoded chunk)

Optional fields:

- `provider` (`firebase` | `oneDrive` | `cloudServer`)

---

## 5) Upload Status

### GET `/api/upload/status?fileId=<id>&totalChunks=<n>`

Query params:

- `fileId` (required string)
- `totalChunks` (required integer, min `1`)

Request body: none

---

## 6) Upload Finalize

### POST `/api/upload/finalize`

Request JSON:

```json
{
  "fileId": "demo-file-001",
  "totalChunks": 3,
  "provider": "firebase",
  "fileName": "evidence.mp4",
  "mimeType": "video/mp4"
}
```

Required fields:

- `fileId` (string)
- `totalChunks` (integer, min `1`)

Optional fields:

- `provider` (`firebase` | `oneDrive` | `cloudServer`)
- `fileName` (string)
- `mimeType` (string)

---

## 7) Dashboard Data APIs (no signed auth)

### GET `/upload/files`
Request body: none

### GET `/upload/analytics`
Request body: none

### GET `/upload/history?page=1&perPage=10`
Query params:
- `page` (optional integer)
- `perPage` (optional integer)

### GET `/upload/logs?page=1&perPage=10`
Query params:
- `page` (optional integer)
- `perPage` (optional integer)

### GET `/upload/files/{name}/view`
Path param:
- `name` (file name)

### GET `/upload/files/{name}/download`
Path param:
- `name` (file name)

### POST `/upload/init`
Same JSON structure as `/api/upload/init`

### GET `/upload/status?fileId=<id>&totalChunks=<n>`
Same query structure as `/api/upload/status`

### POST `/upload/chunk`
Same JSON structure as `/api/upload/chunk`

### POST `/upload/finalize`
Same JSON structure as `/api/upload/finalize`
