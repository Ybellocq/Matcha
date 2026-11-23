<template>
  <nav class="navbar navbar-expand-lg matcha-nav">
    <div class="container-fluid">
      <router-link class="navbar-brand" to="/">
        <span class="brand-icon">🔥</span>
        <span class="brand-text">matcha</span>
      </router-link>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div id="navMain" class="collapse navbar-collapse">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0" v-if="session.user">
          <li class="nav-item">
            <router-link class="nav-link" to="/swipe">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              <span class="nav-label">Découvrir</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/search">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span class="nav-label">Recherche</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/matches">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span class="nav-label">Matchs</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/chat">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <span class="nav-label">Messages</span>
              <span v-if="session.unreadMessages" class="counter">{{ session.unreadMessages }}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/notifications">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span class="nav-label">Alertes</span>
              <span v-if="session.unreadNotifications" class="counter">{{ session.unreadNotifications }}</span>
            </router-link>
          </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item me-2">
            <button class="theme-toggle" @click="toggleTheme" :title="theme === 'dark' ? 'Passer en clair' : 'Passer en sombre'" aria-label="Changer de thème">
              <svg v-if="theme === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
          </li>
          <template v-if="!session.user">
            <li class="nav-item">
              <router-link class="nav-link" to="/login">Connexion</router-link>
            </li>
            <li class="nav-item">
              <router-link class="nav-link signup-btn" to="/register">Inscription</router-link>
            </li>
          </template>

          <li class="nav-item dropdown" v-if="session.user">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
              <div class="user-avatar-sm">
                {{ session.user.username?.[0]?.toUpperCase() }}
              </div>
              <span class="d-none d-md-inline">{{ session.user.username }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><router-link class="dropdown-item" to="/profile">Mon profil</router-link></li>
              <li><hr class="dropdown-divider" /></li>
              <li><button class="dropdown-item logout-item" @click="logout">Déconnexion</button></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref } from 'vue';
import { api, refreshSession } from '../api.js';
import { session } from '../stores/session.js';
import { applyTheme, currentTheme } from '../theme.js';

const theme = ref(currentTheme());

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  applyTheme(theme.value);
}

async function logout() {
  await api('/auth/logout', { method: 'POST' });
  await refreshSession();
}
</script>

<style scoped>
.matcha-nav {
  background: var(--card);
  border-bottom: 1px solid var(--border);
  padding: 8px 0;
  transition: background 0.25s ease;
}

.brand-icon {
  font-size: 1.3rem;
}

.brand-text {
  font-weight: 900;
  font-size: 1.35rem;
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
  font-weight: 700;
  font-size: 0.9rem;
  padding: 8px 12px;
  border-radius: 10px;
  transition: all 0.15s;
}

.nav-link:hover {
  color: var(--primary);
  background: var(--primary-soft);
}

.nav-link.router-link-exact-active,
.nav-link.router-link-active:not(.navbar-brand) {
  color: var(--primary);
}

.counter {
  background: var(--grad);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 800;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  line-height: 1;
}

.user-avatar-sm {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--grad);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.85rem;
}

.signup-btn {
  background: var(--grad) !important;
  color: #fff !important;
  border-radius: 999px;
  padding: 8px 18px !important;
  margin-left: 8px;
  box-shadow: var(--shadow-sm);
}

.theme-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  background: var(--surface);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.15s;
}

.theme-toggle:hover {
  color: var(--primary);
  border-color: var(--primary);
  transform: rotate(15deg);
}

.logout-item { color: var(--danger) !important; font-weight: 600; }

.navbar-toggler:focus {
  box-shadow: none;
}
</style>
