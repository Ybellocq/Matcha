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
              <span class="nav-label">Discover</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/search">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span class="nav-label">Search</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/matches">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              <span class="nav-label">Matches</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/chat">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <span class="nav-label">Chat</span>
              <span v-if="session.unreadMessages" class="counter">{{ session.unreadMessages }}</span>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link class="nav-link" to="/notifications">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span class="nav-label">Alerts</span>
              <span v-if="session.unreadNotifications" class="counter counter--pink">{{ session.unreadNotifications }}</span>
            </router-link>
          </li>
        </ul>
        
        <ul class="navbar-nav ms-auto">
          <template v-if="!session.user">
            <li class="nav-item">
              <router-link class="nav-link" to="/login">Login</router-link>
            </li>
            <li class="nav-item">
              <router-link class="nav-link btn btn-sm btn-light rounded-pill px-3 ms-2" to="/register">Sign up</router-link>
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
              <li><router-link class="dropdown-item" to="/profile">My Profile</router-link></li>
              <li><hr class="dropdown-divider" /></li>
              <li><button class="dropdown-item text-danger" @click="logout">Logout</button></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { session } from '../stores/session.js';
import { api, refreshSession } from '../api.js';

async function logout() {
  await api('/auth/logout', { method: 'POST' });
  await refreshSession();
}
</script>

<style scoped>
.matcha-nav {
  background: #fff;
  box-shadow: 0 1px 8px rgba(0,0,0,0.06);
  padding: 6px 0;
}

.brand-icon {
  font-size: 1.3rem;
}

.brand-text {
  font-weight: 800;
  font-size: 1.35rem;
  background: linear-gradient(135deg, #fd297b, #ff655b);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #888;
  font-weight: 600;
  font-size: 0.9rem;
  padding: 8px 12px;
  border-radius: 8px;
  transition: all 0.15s;
}

.nav-link:hover {
  color: #fd297b;
  background: #fef2f2;
}

.nav-link.router-link-exact-active,
.nav-link.router-link-active:not(.navbar-brand) {
  color: #fd297b;
}

.counter {
  background: #f59e0b;
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

.counter--pink {
  background: #fd297b;
}

.user-avatar-sm {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #fd297b, #ff655b);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
}

.nav-label {
  display: inline;
}

@media (max-width: 991px) {
  .nav-label { display: inline; }
  .nav-link { padding: 10px 12px; }
}

.navbar-toggler:focus {
  box-shadow: none;
}
</style>
