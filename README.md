# Matcha Dating Web App

## Requirements
- PHP 8.1+
- Composer
- Node.js 18+
- PostgreSQL 14+

## Backend setup
1. Create a database (example: `matcha`) and user.
2. Copy environment file:
   ```bash
   cd backend
   cp .env.example .env
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Create schema:
   ```bash
   psql -U matcha -d matcha -f db/schema.sql
   ```
5. Seed 500 users:
   ```bash
   php db/seed.php
   ```
6. Start API server:
   ```bash
   composer start
   ```

## Frontend setup
1. Install dependencies:
   ```bash
   cd frontend
   npm install
   ```
2. Start dev server:
   ```bash
   npm run dev
   ```
3. Set API URL if needed:
   ```bash
   export VITE_API_URL=http://localhost:8000
   ```

## Mail configuration
- Default `MAIL_MODE=log` writes emails to `backend/storage/logs/mail.log`.
- For SMTP, set `MAIL_MODE=smtp` and provide SMTP credentials in `.env`.

## Notes
- Uploads are saved under `backend/public/uploads/`.
- The frontend uses polling every 5 seconds for chat and notifications to meet the 10-second requirement.
- Popularity score formula: `views + (likes * 2) + (matches * 3) + activity` where activity is `5` if active in the last day, otherwise `1`.
