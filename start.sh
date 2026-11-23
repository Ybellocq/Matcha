#!/usr/bin/env bash
# 🔥 Matcha — lancement backend (PHP Slim :8000) + frontend (Vite :5173)
# - Grâce à PHP_CLI_SERVER_WORKERS, l'API accepte les requêtes concurrentes
#   (polling du chat/notifications) sans blocage.
# - Watchdog : si un serveur meurt, il est relancé automatiquement.
set -u

ROOT="$(cd "$(dirname "$0")" && pwd)"
export PHP_CLI_SERVER_WORKERS=8

log() { echo "[$(date '+%H:%M:%S')] $*"; }

# ---------- Arrêt propre ----------
BACKEND_PID=""
FRONTEND_PID=""
cleanup() {
  echo ""
  log "🛑 Arrêt…"
  [[ -n "$FRONTEND_PID" ]] && kill "$FRONTEND_PID" 2>/dev/null
  [[ -n "$BACKEND_PID" ]] && kill "$BACKEND_PID" 2>/dev/null
  pkill -f "php -S localhost:8000 -t public" 2>/dev/null
  pkill -f "vite" 2>/dev/null
  exit 0
}
trap cleanup EXIT INT TERM

# ---------- Ports : libère les instances obsolètes ----------
free_port() {
  local port="$1" name="$2"
  local pids
  pids=$(lsof -ti tcp:"$port" -sTCP:LISTEN 2>/dev/null || true)
  if [[ -n "$pids" ]]; then
    log "♻️  Port $port occupé — arrêt des instances obsolètes de $name"
    echo "$pids" | xargs kill 2>/dev/null
    sleep 1
    pids=$(lsof -ti tcp:"$port" -sTCP:LISTEN 2>/dev/null || true)
    if [[ -n "$pids" ]]; then
      log "❌ Port $port toujours occupé par un autre programme (PID : $pids) — arrête-le puis relance."
      exit 1
    fi
  fi
}
free_port 8000 "l'API"
free_port 5173 "le frontend"

# ---------- Prérequis ----------
command -v php >/dev/null || { echo "❌ PHP manquant (8.1+ requis)"; exit 1; }
command -v composer >/dev/null || { echo "❌ Composer manquant"; exit 1; }
command -v npm >/dev/null || { echo "❌ npm manquant (Node 18+ requis)"; exit 1; }

# ---------- Dépendances ----------
cd "$ROOT/backend"
[[ -f .env ]] || { cp .env.example .env; log "⚠️  backend/.env créé depuis .env.example — vérifie les identifiants DB"; }
[[ -f storage/logs/mail.log ]] || mkdir -p storage/logs
[[ -d vendor ]] || { log "📦 composer install (backend)…"; composer install --no-interaction; }

cd "$ROOT/frontend"
[[ -f .env ]] || { cp .env.example .env; log "⚠️  frontend/.env créé — VITE_API_URL pointe sur :8000 par défaut"; }
[[ -d node_modules ]] || { log "📦 npm install (frontend)…"; npm install; }

# ---------- Watchdogs ----------
run_backend() {
  cd "$ROOT/backend"
  while true; do
    php -S localhost:8000 -t public >> "$ROOT/backend.log" 2>&1
    log "⚠️  API interrompue (code $?) — relance dans 1 s…"
    sleep 1
  done
}

run_frontend() {
  cd "$ROOT/frontend"
  while true; do
    npx vite >> "$ROOT/frontend.log" 2>&1
    log "⚠️  Frontend interrompu (code $?) — relance dans 1 s…"
    sleep 1
  done
}

# ---------- Lancement ----------
: > "$ROOT/backend.log"
: > "$ROOT/frontend.log"
run_backend & BACKEND_PID=$!
run_frontend & FRONTEND_PID=$!

log "🚀 Matcha lancé (Ctrl+C pour tout arrêter)"
log "   • API      → http://localhost:8000   (log : backend.log, watchdog auto-restart)"
log "   • Frontend → http://localhost:5173   (log : frontend.log)"
log "   • Boîte mail dev → http://localhost:8000/dev/mailbox"
log "   • E-mails (validation/reset) : backend/storage/logs/mail.log si MAIL_MODE=log"

wait
