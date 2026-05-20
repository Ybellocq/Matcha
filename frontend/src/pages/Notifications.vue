<template>
  <div>
    <h2 class="mb-3">Notifications</h2>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="notifications.length === 0" class="alert alert-info">No notifications yet.</div>
    <ul class="list-group">
      <li v-for="item in notifications" :key="item.id" class="list-group-item d-flex justify-content-between">
        <div>
          <strong>{{ item.type }}</strong> from {{ item.actor_username }}
          <div class="text-muted small">{{ item.created_at }}</div>
        </div>
        <button v-if="!item.read_at" class="btn btn-sm btn-outline-primary" @click="markRead(item.id)">Mark read</button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api.js';

const notifications = ref([]);
const error = ref('');

onMounted(async () => {
  await loadNotifications();
});

async function loadNotifications() {
  try {
    const data = await api('/notifications');
    notifications.value = data.notifications || [];
  } catch (err) {
    error.value = err.message;
  }
}

async function markRead(id) {
  await api(`/notifications/${id}/read`, { method: 'POST' });
  await loadNotifications();
}
</script>
