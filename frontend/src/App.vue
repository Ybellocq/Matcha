<template>
  <NavBar />
  <main class="container py-4">
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
  if (intervalId) {
    clearInterval(intervalId);
  }
});
</script>
