# Flutter Vigi (Vigilance Meter Theft App)

Flutter app for meter theft reporting with professional UI, evidence video capture,
chunk upload, resume support, and backend-verified anti-replay security.

## Run locally

```bash
flutter pub get
flutter run \
	--dart-define=BACKEND_BASE_URL=http://localhost:8080 \
	--dart-define=ACTIVE_STORAGE_PROVIDER=firebase \
	--dart-define=API_KEY=change-me-api-key \
	--dart-define=REQUEST_SIGNING_SECRET=change-me-signing-secret \
	--dart-define=UPLOAD_RETRY_COUNT=3
```

## Flavor-based setup (recommended)

Use pre-defined flavor files:

```bash
flutter run --dart-define-from-file=config/flavors/dev.json
flutter run --dart-define-from-file=config/flavors/staging.json
flutter run --dart-define-from-file=config/flavors/prod.json
```

You can also combine with flavor override:

```bash
flutter run \
	--dart-define-from-file=config/flavors/dev.json \
	--dart-define=APP_FLAVOR=dev
```

## Build for release

```bash
flutter build apk \
	--dart-define=BACKEND_BASE_URL=https://api.your-domain.com \
	--dart-define=ACTIVE_STORAGE_PROVIDER=firebase \
	--dart-define=API_KEY=$API_KEY \
	--dart-define=REQUEST_SIGNING_SECRET=$REQUEST_SIGNING_SECRET
```

Or with file-based config:

```bash
flutter build apk --dart-define-from-file=config/flavors/prod.json
```

## CI/CD wiring

- Store `API_KEY` and `REQUEST_SIGNING_SECRET` as CI secrets.
- Generate environment-specific JSON at pipeline runtime and pass via `--dart-define-from-file`.
- Do not commit real production keys in repository files.
- Never hardcode secrets in source files.

## Required runtime keys

- `APP_FLAVOR` (`dev`, `staging`, `prod`)
- `BACKEND_BASE_URL`
- `ACTIVE_STORAGE_PROVIDER` (`firebase`, `oneDrive`, `cloudServer`)
- `API_KEY`
- `REQUEST_SIGNING_SECRET`
- `UPLOAD_RETRY_COUNT` (optional)
# vigi
