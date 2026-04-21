# Authentication API — Admin (Sanctum)

এই ডকটি API ব্যবহার করে `Admin`-এর জন্য কীভাবে লগইন/লগআউট করতে হবে তা বলে।

## Endpoint: Admin Login
- URL: `POST /api/v1/admin/login`
- Payload:
  - `email` (string, required)
  - `password` (string, required)
- Response (200):
  - `access_token`: Bearer token
  - `token_type`: "Bearer"
  - `admin`: object (id, first_name, last_name, email, role_id)
- Errors: 401 Invalid credentials, 403 Account inactive

Example cURL:

```bash
curl -X POST "https://example.test/api/v1/admin/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.test","password":"secret"}'
```

## Endpoint: Admin Logout
- URL: `POST /api/v1/admin/logout`
- Auth: `Authorization: Bearer {access_token}` (Sanctum token)
- Response: 200 {"message": "Logged out"}

Example cURL:

```bash
curl -X POST "https://example.test/api/v1/admin/logout" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json"
```

## Notes
- Admin model now uses `HasApiTokens` (Laravel Sanctum) to create personal access tokens.
- Existing session-based admin login (web) is unchanged and continues to work.
- For mobile clients, prefer token-based login; for web SPA use Sanctum SPA authentication or session-based login.
