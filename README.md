# 🔥 Matcha — Dating Web App

Tinder-like dating application built with **Vue 3 + PHP/Slim + PostgreSQL**.

## Requirements
- PHP 8.1+
- Composer
- Node.js 18+
- PostgreSQL 14+

## Quick start

### Backend
```bash
cd backend
cp .env.example .env         # Edit DB credentials if needed
composer install
psql -U postgres -c "CREATE DATABASE matcha"
psql -U postgres -d matcha -f db/schema.sql
php db/seed.php               # Creates 500 fake users
composer start                # Runs on localhost:8000
```

### Frontend
```bash
cd frontend
cp .env.example .env          # Edit VITE_API_URL if backend is not on :8000
npm install
npm run dev                   # Runs on localhost:5173
```

## Email
- `MAIL_MODE=log` → emails written to `backend/storage/logs/mail.log`
- `MAIL_MODE=smtp` → set SMTP credentials in `backend/.env`

## Features
- 🔥 Tinder-style swipe UI with drag gestures
- 💘 Real-time matching with "It's a Match!" animation
- 💬 Real-time chat between matched users (polling ≤ 5s)
- 🔔 Real-time notifications (like, view, message, match, unmatch)
- 📍 GPS geolocation with manual city input
- 📸 Photo upload (up to 5, with main photo selection)
- 🏷️ Interest tags with smart suggestions
- 🔍 Advanced search with age, popularity, distance, and tag filters
- 👁️ Profile view tracking with popularity score
- 🚫 Block & report users
- 📱 Fully responsive (mobile-first)

## Popularity formula
`views + (likes × 2) + (matches × 3) + activity`
where `activity = 5` if active in the last 24h, else `1`.

## Security
- Passwords hashed with bcrypt
- All queries use prepared statements (no SQL injection)
- Input sanitization (strip_tags)
- CSRF protection on all mutating endpoints
- CORS with explicit origin whitelist
- Email verification + password reset tokens (hashed, time-limited)
