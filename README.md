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


1. Browser Register Response - POST /user/api/v1/chatbot/browser-register

{
  "status": "success",
  "message": "FCM token registered successfully",
  "data": {
    "token_registered": true,
    "conversation_id": "1234567890",
    "platform": "android"
  }
}
2. Send Message Response - POST /user/api/v1/chatbot/user-message

{
  "status": "success", 
  "message": "Message sent successfully",
  "data": {
    "message_id": "msg_1777286097499_123456",
    "conversation_id": "1234567890",
    "timestamp": "2026-04-27T10:30:00Z",
    "status": "sent"
  }
}
3. Chat History Response - POST /user/api/v1/chatbot/chat-history

{
  "status": "success",
  "message": "Chat history retrieved successfully",
  "data": [
    {
      "id": "msg_1777286097499_123456",
      "text": "Hello, how can I help you?",
      "sender_id": "admin",
      "sender_name": "Support Agent",
      "conversation_id": "1234567890",
      "role": "admin",
      "timestamp": "2026-04-27T10:30:00Z",
      "is_read": true
    },
    {
      "id": "msg_1777286097499_123457", 
      "text": "I have a question about my bill",
      "sender_id": "1234567890",
      "sender_name": "You",
      "conversation_id": "1234567890",
      "role": "user",
      "timestamp": "2026-04-27T10:31:00Z",
      "is_read": true
    }
  ],
  "pagination": {
    "offset": 0,
    "limit": 50,
    "total": 2,
    "has_more": false
  }
}

4. Mark Read Response - POST /user/api/v1/chatbot/mark-read

{
  "status": "success",
  "message": "Message marked as read",
  "data": {
    "message_id": "msg_1777286097499_123456",
    "marked_read": true,
    "read_at": "2026-04-27T10:32:00Z"
  }
}

5. FCM Push Notification Payload (received by app)

   {
  "to": "fcm_token_string",
  "notification": {
    "title": "New Message",
    "body": "You have a new message from support",
    "sound": "default"
  },
  "data": {
    "type": "chat_message",
    "id": "msg_1777286097499_123458",
    "text": "Thank you for your patience",
    "sender_id": "admin", 
    "sender_name": "Support Agent",
    "conversation_id": "1234567890",
    "role": "admin",
    "timestamp": "2026-04-27T10:33:00Z",
    "is_read": false
  }
}
