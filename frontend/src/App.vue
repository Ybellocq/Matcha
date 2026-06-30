<template>
  <NavBar />
  <main :class="{ 'main-guest': !session.user, 'main-auth': session.user }">
    <router-view />
  </main>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import NavBar from './components/NavBar.vue';
import { refreshSession, refreshCounters } from './api.js';
import { session } from './stores/session.js';

let intervalId;

onMounted(async () => {
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

:root {
  --tinder-pink: #fd297b;
  --tinder-orange: #ff655b;
  --tinder-gradient: linear-gradient(135deg, #fd297b, #ff655b);
}

* {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

body {
  background: #f8f9fa;
  margin: 0;
  padding: 0;
}

.main-guest {
  min-height: calc(100vh - 56px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(180deg, #fdf2f4 0%, #f8f9fa 100%);
}

.main-auth {
  padding: 20px 16px 60px;
}

/* Override Bootstrap defaults for more Tinder-like look */
.btn-danger {
  background: linear-gradient(135deg, #fd297b, #ff655b) !important;
  border: none !important;
}

.btn-outline-danger {
  color: #fd297b !important;
  border-color: #fd297b !important;
}

.btn-outline-danger:hover {
  background: linear-gradient(135deg, #fd297b, #ff655b) !important;
  color: #fff !important;
  border-color: transparent !important;
}

.form-control:focus,
.form-select:focus {
  border-color: #fd297b !important;
  box-shadow: 0 0 0 0.2rem rgba(253, 41, 123, 0.15) !important;
}

.text-primary { color: #fd297b !important; }
.bg-primary { background: linear-gradient(135deg, #fd297b, #ff655b) !important; }
.badge.bg-warning { background: #f59e0b !important; }
.badge.bg-info { background: #fd297b !important; }
</style>
