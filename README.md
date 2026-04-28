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

CREATE TABLE `fcm_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(255) NOT NULL,
    `conversation_id` VARCHAR(100) NOT NULL,
    `platform` ENUM('android', 'ios', 'web') NOT NULL,
    `user_id` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX (`user_id`),
    INDEX (`conversation_id`),
    UNIQUE KEY `unique_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `chatmessages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `message` TEXT NOT NULL,
    `conversation_id` VARCHAR(100) NOT NULL,
    `user_id` VARCHAR(100) NOT NULL,
    `platform` ENUM('android','ios','web') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_conversation` (`conversation_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


SELECT 
    `id`,
    `message`,
    `conversation_id`,
    `user_id`,
    `platform`,
    `created_at`
FROM `chatmessages`
WHERE `conversation_id` = 'service_number_or_flutter_timestamp'
ORDER BY `id` DESC
LIMIT 50 OFFSET 0;


Use cursor-based pagination ✅

SELECT 
    `id`,
    `message`,
    `conversation_id`,
    `user_id`,
    `platform`,
    `created_at`
FROM `messages`
WHERE `conversation_id` = '1234567890'
AND `id` < 5000   -- last message id from previous load
ORDER BY `id` DESC
LIMIT 50;

ALTER TABLE `messages`
ADD COLUMN `status` ENUM('sent','delivered','read') DEFAULT 'sent';
