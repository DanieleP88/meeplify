# Meeplify

Web app for personal and collaborative checklists with admin panel.

## Setup

1. Clone the repo.
2. Copy .env.example to .env and fill in values: DB creds, Google OAuth, SMTP.
3. Set up MySQL DB with schema from DATA_MODEL (manual creation).
4. Point web server to project root, with PHP 8.x.
5. For admin access, set a user role to 'admin' in DB.

## .env Variables

- DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
- GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
- SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_EMAIL

## Routes

### Auth
- POST /api/auth/google/exchange - Exchange code for token
- GET /api/auth/me - User info
- POST /api/auth/logout - Logout

### Checklists
- GET /api/checklists - List
- POST /api/checklists - Create
- GET /api/checklists/{id} - Detail
- PATCH /api/checklists/{id} - Update
- DELETE /api/checklists/{id} - Soft delete
- POST /api/checklists/{id}/duplicate - Duplicate
- PATCH /api/checklists/{id}/share-view - Share
- GET /api/share/{token} - Public view
- GET /api/checklists/{id}/export - Export JSON
- POST /api/checklists/{id}/import?mode=merge|replace - Import JSON

(Full list in API_SPEC)

## cURL Examples

Login exchange:
```bash
curl -X POST http://localhost/api/auth/google/exchange -d 'code=GOOGLE_CODE'
```

Create checklist:
```bash
curl -X POST http://localhost/api/checklists -H 'Content-Type: application/json' -d '{"title":"New List"}'
```

## Testing

Run smoke tests: php tests/smoke.php
