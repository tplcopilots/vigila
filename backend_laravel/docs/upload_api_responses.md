# Upload API Responses

This document contains response details only.

## Common Error Patterns

### Validation Error

Status: `422`

```json
{
  "error": "Validation failed",
  "details": {
    "fieldName": ["The fieldName field is required."]
  }
}
```

### Server Error

Status: `500`

```json
{
  "error": "<error-message>"
}
```

---

## 1) Health

### GET `/health`

Status: `200`

```json
{
  "ok": true,
  "timestamp": "2026-03-05T10:00:00.000000Z"
}
```

---

## 2) Save Report

### POST `/api/report`

Status: `200`

```json
{
  "success": true,
  "reportId": "demo-report-001"
}
```

---

## 3) Upload Init (resume-aware)

### POST `/api/upload/init`

Status: `200`

```json
{
  "success": true,
  "fileId": "demo-file-001",
  "totalChunks": 3,
  "uploadedIndexes": [0, 1],
  "missingIndexes": [2],
  "provider": "firebase"
}
```

---

## 4) Upload Chunk

### POST `/api/upload/chunk`

Success status: `200`

```json
{
  "success": true,
  "fileId": "demo-file-001",
  "chunkIndex": 0
}
```

Duplicate status: `409`

```json
{
  "error": "Chunk already uploaded"
}
```

Invalid index status: `400`

```json
{
  "error": "Invalid chunkIndex or totalChunks value"
}
```

---

## 5) Upload Status

### GET `/api/upload/status`

Status: `200`

```json
{
  "success": true,
  "fileId": "demo-file-001",
  "totalChunks": 3,
  "uploadedIndexes": [0, 1],
  "missingIndexes": [2]
}
```

---

## 6) Upload Finalize

### POST `/api/upload/finalize`

Success status: `200`

```json
{
  "success": true,
  "fileId": "demo-file-001",
  "provider": "firebase",
  "storage": {
    "provider": "firebase",
    "location": "firebase://uploads/demo-file-001",
    "path": "<provider-specific-path-or-url>"
  }
}
```

Missing chunks status: `409`

```json
{
  "error": "Cannot finalize upload. Missing chunks exist.",
  "missingIndexes": [2],
  "uploadedIndexes": [0, 1]
}
```

---

## 7) Dashboard Data APIs

### GET `/upload/files`

Status: `200`

```json
{
  "success": true,
  "files": [
    {
      "name": "demo-file-001.mp4",
      "sizeBytes": 1048576,
      "updatedAt": "2026-03-05T10:00:00+00:00",
      "extension": "mp4"
    }
  ]
}
```

### GET `/upload/analytics`

Status: `200`

```json
{
  "success": true,
  "analytics": {
    "chunkState": {
      "pendingChunks": 0,
      "runningChunks": 0,
      "completedFiles": 1
    },
    "pendingByProvider": {
      "firebase": 0,
      "oneDrive": 0,
      "cloudServer": 0
    },
    "fileTypeCounts": {
      "mp4": 1
    }
  }
}
```

### GET `/upload/history?page=1&perPage=10`

Status: `200`

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "dateTime": "2026-03-05T10:00:00+00:00",
        "name": "demo-file-001.mp4",
        "type": "MP4",
        "status": "COMPLETED"
      }
    ],
    "pagination": {
      "page": 1,
      "perPage": 10,
      "total": 1,
      "lastPage": 1
    }
  }
}
```

### GET `/upload/logs?page=1&perPage=10`

Status: `200`

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "dateTime": "2026-03-05 10:00:00",
        "fileId": "demo-file-001",
        "level": "INFO",
        "event": "api.finalize.success",
        "message": "<raw log line>",
        "details": {
          "dateTime": "2026-03-05 10:00:00",
          "level": "INFO",
          "event": "api.finalize.success",
          "fileId": "demo-file-001",
          "context": {},
          "extra": {},
          "raw": "<raw log line>"
        }
      }
    ],
    "pagination": {
      "page": 1,
      "perPage": 10,
      "total": 1,
      "lastPage": 1
    }
  }
}
```
