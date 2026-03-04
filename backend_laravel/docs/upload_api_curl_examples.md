# Upload API - Signed cURL Examples

Base URL examples assume `http://localhost:8080`.

## 1) Generate signed headers (Node.js helper)

Use this helper to produce headers for each request:

```bash
node -e "
const crypto=require('crypto');
const method=process.argv[1]||'GET';
const path=process.argv[2]||'/api/upload/status';
const body=process.argv[3]||'';
const apiKey=process.env.API_KEY||'change-me-api-key';
const secret=process.env.SIGNING_SECRET||'change-me-signing-secret';
const timestamp=Date.now().toString();
const nonce=crypto.randomUUID();
const bodyHash=crypto.createHash('sha256').update(body).digest('hex');
const canonical=[method.toUpperCase(),path,timestamp,nonce,bodyHash].join('\\n');
const signature=crypto.createHmac('sha256',secret).update(canonical).digest('hex');
console.log(JSON.stringify({
  'content-type':'application/json',
  'x-api-key':apiKey,
  'x-timestamp':timestamp,
  'x-nonce':nonce,
  'x-signature':signature
},null,2));
" POST /api/upload/init '{"fileId":"demo-file-1","totalChunks":3,"provider":"firebase"}'
```

## 2) API Flow

### A. Report

```bash
BODY='{"reportId":"demo-report-1","provider":"firebase","report":{"reporterName":"Inspector","meterNumber":"MTR-1001","location":"Sector 10","details":"Bypass found"}}'
node -e "const c=require('crypto');const b=process.argv[1];const t=Date.now().toString();const n='n-'+Math.random();const h=c.createHash('sha256').update(b).digest('hex');const can=['POST','/api/report',t,n,h].join('\\n');const s=c.createHmac('sha256',process.env.SIGNING_SECRET||'change-me-signing-secret').update(can).digest('hex');console.log([t,n,s].join(' '));" "$BODY"
# Copy printed values into TS NONCE SIGN below and run:
# curl -X POST 'http://localhost:8080/api/report' -H 'content-type: application/json' -H 'x-api-key: change-me-api-key' -H "x-timestamp: TS" -H "x-nonce: NONCE" -H "x-signature: SIGN" -d "$BODY"
```

### B. Upload Init

```bash
curl -X POST 'http://localhost:8080/api/upload/init' \
  -H 'content-type: application/json' \
  -H 'x-api-key: change-me-api-key' \
  -H 'x-timestamp: <ts>' \
  -H 'x-nonce: <nonce>' \
  -H 'x-signature: <signature>' \
  -d '{"fileId":"demo-file-1","totalChunks":3,"provider":"firebase"}'
```

### C. Upload Chunk

Payload must be base64 chunk bytes.

```bash
curl -X POST 'http://localhost:8080/api/upload/chunk' \
  -H 'content-type: application/json' \
  -H 'x-api-key: change-me-api-key' \
  -H 'x-timestamp: <ts>' \
  -H 'x-nonce: <nonce>' \
  -H 'x-signature: <signature>' \
  -d '{"fileId":"demo-file-1","chunkIndex":0,"totalChunks":3,"provider":"firebase","payload":"SGVsbG8="}'
```

### D. Upload Status

```bash
curl 'http://localhost:8080/api/upload/status?fileId=demo-file-1&totalChunks=3' \
  -H 'x-api-key: change-me-api-key' \
  -H 'x-timestamp: <ts>' \
  -H 'x-nonce: <nonce>' \
  -H 'x-signature: <signature>'
```

### E. Upload Finalize

```bash
curl -X POST 'http://localhost:8080/api/upload/finalize' \
  -H 'content-type: application/json' \
  -H 'x-api-key: change-me-api-key' \
  -H 'x-timestamp: <ts>' \
  -H 'x-nonce: <nonce>' \
  -H 'x-signature: <signature>' \
  -d '{"fileId":"demo-file-1","totalChunks":3,"provider":"firebase","fileName":"evidence.mp4","mimeType":"video/mp4"}'
```

## 3) Dashboard Endpoints (no signed headers)

- `GET /upload/files`
- `GET /upload/analytics`
- `GET /upload/history?page=1&perPage=10`
- `GET /upload/logs?page=1&perPage=10`
