<template>
  <NavBar />
  <main :class="{ 'main-guest': !session.user, 'main-auth': session.user }">
    <router-view />
  </main>
  <footer class="app-footer">
    <div class="footer-inner">
      <span class="footer-brand">🔥 <strong>matcha</strong></span>
      <nav class="footer-links">
        <router-link to="/swipe">Découvrir</router-link>
        <router-link to="/search">Recherche</router-link>
        <router-link to="/matches">Matchs</router-link>
        <router-link to="/notifications">Notifications</router-link>
      </nav>
      <span class="footer-copy">© {{ new Date().getFullYear() }} Matcha — Vue 3 &amp; Slim</span>
    </div>
  </footer>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import NavBar from './components/NavBar.vue';
import { refreshSession, refreshCounters } from './api.js';
import { initTheme } from './theme.js';
import { session } from './stores/session.js';

let intervalId;

onMounted(async () => {
  initTheme();
  await refreshSession();
  await refreshCounters();
  intervalId = setInterval(() => {
    if (session.user) {
      refreshCounters();
    }
  }, 5000);
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* ============ Design tokens — Tinder vibrant ============ */
:root {
  /* Flame gradient */
  --grad: linear-gradient(135deg, #fd267a 0%, #ff6036 100%);
  --primary: #fd267a;
  --primary-2: #ff6036;
  --primary-soft: #ffe4ef;
  --success: #0fbf7a;
  --success-soft: #e2f9ef;
  --info: #6b74f8;
  --info-soft: #eceeff;
  --warning: #f5a623;
  --danger: #ef476f;

  /* Light surfaces */
  --bg: #fff7f8;
  --bg-soft: #fdeef3;
  --card: #ffffff;
  --surface: #faf7fa;
  --text: #2a1a24;
  --text-muted: #9b8a94;
  --border: #f4e3ea;
  --shadow-sm: 0 2px 12px rgba(253, 38, 122, 0.08);
  --shadow: 0 8px 32px rgba(253, 38, 122, 0.14);
  --shadow-lg: 0 18px 48px rgba(253, 38, 122, 0.2);
}

html[data-theme='dark'] {
  --bg: #17121a;
  --bg-soft: #1f1723;
  --card: #241b28;
  --surface: #2c2130;
  --text: #fbf4f7;
  --text-muted: #a892a0;
  --border: #3a2c40;
  --primary-soft: rgba(253, 38, 122, 0.16);
  --success-soft: rgba(15, 191, 122, 0.14);
  --info-soft: rgba(107, 116, 248, 0.16);
  --shadow-sm: 0 2px 12px rgba(0, 0, 0, 0.35);
  --shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
  --shadow-lg: 0 18px 48px rgba(0, 0, 0, 0.55);
}

* {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

body {
  background: var(--bg);
  color: var(--text);
  margin: 0;
  padding: 0;
  transition: background 0.25s ease, color 0.25s ease;
}

#app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.main-guest {
  min-height: calc(100vh - 56px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 100%);
}

.main-auth {
  padding: 20px 16px 60px;
  flex: 1;
}

/* ============ Bootstrap overrides — éclat Tinder ============ */
.btn-danger {
  background: var(--grad) !important;
  border: none !important;
  font-weight: 700;
}

.btn-primary {
  background: var(--grad) !important;
  border: none !important;
  font-weight: 700;
}

.btn-outline-danger {
  color: var(--primary) !important;
  border-color: var(--primary) !important;
  font-weight: 600;
}

.btn-outline-danger:hover {
  background: var(--grad) !important;
  color: #fff !important;
  border-color: transparent !important;
}

.btn-outline-primary {
  color: var(--primary) !important;
  border-color: var(--primary) !important;
}

.btn-outline-primary:hover {
  background: var(--grad) !important;
  color: #fff !important;
  border-color: transparent !important;
}

.form-control,
.form-select {
  background: var(--card);
  border-color: var(--border);
  color: var(--text);
}

.form-control:focus,
.form-select:focus {
  border-color: var(--primary) !important;
  box-shadow: 0 0 0 0.2rem rgba(253, 38, 122, 0.15) !important;
  background: var(--card);
  color: var(--text);
}

.form-label { color: var(--text-muted); }

.card {
  background: var(--card);
  border-color: var(--border);
  color: var(--text);
}

.list-group-item {
  background: var(--card);
  border-color: var(--border);
  color: var(--text);
}

.alert-danger {
  background: rgba(239, 71, 111, 0.12);
  border-color: rgba(239, 71, 111, 0.3);
  color: #e0355e;
}

html[data-theme='dark'] .alert-danger { color: #ff8aa5; }

.alert-success {
  background: var(--success-soft);
  border-color: rgba(15, 191, 122, 0.3);
  color: #0d9e66;
}

.alert-info {
  background: var(--info-soft);
  border-color: rgba(107, 116, 248, 0.3);
  color: #5a63e0;
}

html[data-theme='dark'] .alert-success { color: #5fe3ae; }
html[data-theme='dark'] .alert-info { color: #a3aaff; }

.text-muted { color: var(--text-muted) !important; }
.text-primary { color: var(--primary) !important; }
.bg-primary { background: var(--grad) !important; }
.badge.bg-warning { background: var(--warning) !important; }
.badge.bg-info { background: var(--primary) !important; }
.badge.bg-success { background: var(--success) !important; }

.dropdown-menu {
  background: var(--card);
  border-color: var(--border);
}

.dropdown-item { color: var(--text); }
.dropdown-item:hover { background: var(--primary-soft); }

hr.dropdown-divider { border-color: var(--border); }

/* ============ Footer ============ */
.app-footer {
  border-top: 1px solid var(--border);
  background: var(--card);
  padding: 18px 24px;
  min-height: 56px;
}

.footer-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.footer-brand {
  font-size: 1rem;
}

.footer-links {
  display: flex;
  gap: 14px;
}

.footer-links a {
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 600;
}

.footer-links a:hover {
  color: var(--primary);
}
</style>
