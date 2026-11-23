#!/usr/bin/env bash
# 🔥 Matcha — lancement backend (PHP Slim :8000) + frontend (Vite :5173)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BACKEND_PIDS=()

cleanup() {
  echo ""
  echo "🛑 Arrêt des serveurs…"
  for pid in "${BACKEND_PIDS[@]:-}"; do
    [[ -n "$pid" ]] && kill "$pid" 2>/dev/null || true
  done
  # Détachés avec disown : on nettoie aussi par nom de commande
  pkill -f "php -S localhost:8000 -t public" 2>/dev/null || true
  pkill -f "vite" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

# ---------- Prérequis ----------
command -v php >/dev/null || { echo "❌ PHP manquant (8.1+ requis)"; exit 1; }
command -v composer >/dev/null || { echo "❌ Composer manquant"; exit 1; }
command -v npm >/dev/null || { echo "❌ npm manquant (Node 18+ requis)"; exit 1; }

# ---------- Backend ----------
cd "$ROOT/backend"
[[ -f .env ]] || { cp .env.example .env; echo "⚠️  backend/.env créé depuis .env.example — vérifie les identifiants DB"; }
[[ -d vendor ]] || { echo "📦 composer install (backend)…"; composer install --no-interaction; }

# ---------- Frontend ----------
cd "$ROOT/frontend"
[[ -f .env ]] || { cp .env.example .env; echo "⚠️  frontend/.env créé — VITE_API_URL pointe sur :8000 par défaut"; }
[[ -d node_modules ]] || { echo "📦 npm install (frontend)…"; npm install; }

# ---------- Lancement ----------
echo ""
echo "🚀 Lancement…"
cd "$ROOT/backend"
php -S localhost:8000 -t public > "$ROOT/backend.log" 2>&1 &
BACKEND_PIDS+=($!)
echo "   • API      → http://localhost:8000   (log : backend.log)"

cd "$ROOT/frontend"
npm run dev > "$ROOT/frontend.log" 2>&1 &
disown
echo "   • Frontend → http://localhost:5173   (log : frontend.log)"

echo ""
echo "✅ Matcha est lancé (Ctrl+C pour tout arrêter)"
echo "   Les e-mails (validation/reset) sont dans backend/storage/logs/mail.log si MAIL_MODE=log"
echo ""
tail -f backend.log frontend.log
