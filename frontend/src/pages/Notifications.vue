<template>
  <div class="notifications-page">
    <h2 class="notif-title">Notifications</h2>
    
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    
    <div v-if="notifications.length === 0" class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <p>No notifications yet</p>
      <span class="text-muted">Likes, messages, and views will appear here</span>
    </div>
    
    <div v-else class="notif-list">
      <div
        v-for="item in notifications"
        :key="item.id"
        class="notif-item"
        :class="{ 'notif-item--unread': !item.read_at }"
        @click="handleClick(item)"
      >
        <div class="notif-icon">
          <span v-if="item.type === 'like'">❤️</span>
          <span v-else-if="item.type === 'match'">💘</span>
          <span v-else-if="item.type === 'message'">💬</span>
          <span v-else-if="item.type === 'view'">👁️</span>
          <span v-else-if="item.type === 'unlike'">💔</span>
          <span v-else>🔔</span>
        </div>
        <div class="notif-body">
          <div class="notif-text">
            <strong>{{ item.actor_username }}</strong>
            <span v-if="item.type === 'like'"> liked your profile</span>
            <span v-else-if="item.type === 'match'"> matched with you!</span>
            <span v-else-if="item.type === 'message'"> sent you a message</span>
            <span v-else-if="item.type === 'view'"> viewed your profile</span>
            <span v-else-if="item.type === 'unlike'"> unmatched you</span>
            <span v-else> {{ item.type }}</span>
          </div>
          <div class="notif-time">{{ formatTime(item.created_at) }}</div>
        </div>
        <div v-if="!item.read_at" class="notif-dot"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';

const router = useRouter();
const notifications = ref([]);
const error = ref('');

onMounted(async () => { await loadNotifications(); });

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

function handleClick(item) {
  if (!item.read_at) markRead(item.id);
  if (['like', 'match', 'message', 'view', 'unlike'].includes(item.type)) {
    router.push(`/users/${item.actor_username}`);
  }
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const now = new Date();
  const diff = Math.floor((now - d) / 1000);
  if (diff < 60) return 'Just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return d.toLocaleDateString();
}
</script>

<style scoped>
.notifications-page {
  max-width: 500px;
  margin: 0 auto;
  padding: 16px;
}

.notif-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1a1a2e;
  margin-bottom: 20px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #999;
}

.notif-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #fff;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.notif-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
}

.notif-item:hover { background: #fafafa; }
.notif-item--unread { background: #fef2f2; }
.notif-item--unread:hover { background: #fde8e8; }

.notif-icon {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.notif-body {
  flex: 1;
  min-width: 0;
}

.notif-text {
  font-size: 0.9rem;
  color: #333;
  line-height: 1.3;
}

.notif-time {
  font-size: 0.75rem;
  color: #999;
  margin-top: 2px;
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #fd297b;
  flex-shrink: 0;
}
</style>
