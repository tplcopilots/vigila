# Flutter Vigi

Main project documentation hub for mobile app, backend upload APIs, and testing references.

## Upload/API Documentation

- Backend docs index: `backend_laravel/docs/README.md`
- API request docs: `backend_laravel/docs/upload_api_requests.md`
- API response docs: `backend_laravel/docs/upload_api_responses.md`
- Signed curl examples: `backend_laravel/docs/upload_api_curl_examples.md`
- Postman collection: `backend_laravel/docs/upload_api.postman_collection.json`

## Flutter Upload Documentation

- Production checklist: `docs/flutter_upload_prod_checklist.md`

## Quick Start

### Flutter

```bash
flutter pub get
flutter run
```

### Laravel backend

```bash
cd backend_laravel
cp .env.example .env
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8080
```

### Validate Flutter code

```bash
flutter analyze
```
