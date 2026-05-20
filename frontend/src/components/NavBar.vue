<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <router-link class="navbar-brand" to="/">Matcha</router-link>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div id="navMain" class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item" v-if="session.user">
            <router-link class="nav-link" to="/search">Search</router-link>
          </li>
          <li class="nav-item" v-if="session.user">
            <router-link class="nav-link" to="/matches">Matches</router-link>
          </li>
          <li class="nav-item" v-if="session.user">
            <router-link class="nav-link" to="/chat">Chat
              <span v-if="session.unreadMessages" class="badge bg-warning text-dark ms-1">{{ session.unreadMessages }}</span>
            </router-link>
          </li>
          <li class="nav-item" v-if="session.user">
            <router-link class="nav-link" to="/notifications">Notifications
              <span v-if="session.unreadNotifications" class="badge bg-info text-dark ms-1">{{ session.unreadNotifications }}</span>
            </router-link>
          </li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item" v-if="!session.user">
            <router-link class="nav-link" to="/login">Login</router-link>
          </li>
          <li class="nav-item" v-if="!session.user">
            <router-link class="nav-link" to="/register">Register</router-link>
          </li>
          <li class="nav-item dropdown" v-if="session.user">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              {{ session.user.username || 'Account' }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><router-link class="dropdown-item" to="/profile">My profile</router-link></li>
              <li><button class="dropdown-item" @click="logout">Logout</button></li>
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
