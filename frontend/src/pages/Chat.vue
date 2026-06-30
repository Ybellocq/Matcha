<template>
  <div class="chat-layout">
    <!-- Match list sidebar -->
    <div class="chat-sidebar">
      <h4 class="chat-sidebar-title">Matches</h4>
      <ul class="list-group list-group-flush">
        <li v-if="matches.length === 0" class="list-group-item text-muted text-center py-3">
          No matches yet. Start liking people!
        </li>
        <li
          v-for="match in matches"
          :key="match.username"
          class="list-group-item chat-contact"
          :class="{ active: activeUser === match.username }"
          @click="selectMatch(match.username)"
        >
          <div class="contact-avatar">
            <img
              v-if="match.main_photo_path"
              :src="photoUrl(match.main_photo_path)"
              class="avatar-img"
              alt=""
            />
            <span v-else class="avatar-placeholder">{{ match.username[0]?.toUpperCase() }}</span>
          </div>
          <div class="contact-info">
            <div class="contact-name">{{ match.first_name }}</div>
            <div class="contact-status">
              <span class="online-badge" v-if="isOnline(match)">Online</span>
              <span class="text-muted" v-else>Offline</span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Chat area -->
    <div class="chat-main">
      <div v-if="!activeUser" class="chat-placeholder">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <h4>Your Messages</h4>
        <p class="text-muted">Select a match to start chatting</p>
      </div>

      <template v-else>
        <!-- Chat header -->
        <div class="chat-header">
          <div class="chat-header-user">
            <div class="contact-avatar">
              <span class="avatar-placeholder">{{ activeUser[0]?.toUpperCase() }}</span>
            </div>
            <div>
              <div class="fw-bold">{{ activeUser }}</div>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" ref="messagesContainer">
          <div v-if="messages.length === 0" class="text-center text-muted py-5">
            No messages yet. Say hello!
          </div>
          <div
            v-for="message in messages"
            :key="message.id"
            class="message-row"
            :class="{ 'message-row--me': message.sender_id === userId }"
          >
            <div class="message-bubble" :class="{ 'message-bubble--me': message.sender_id === userId }">
              {{ message.body }}
              <div class="message-time">{{ formatTime(message.created_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Input -->
        <form class="chat-input" @submit.prevent="sendMessage">
          <input
            v-model="newMessage"
            class="form-control chat-input-field"
            placeholder="Type a message..."
            required
            autocomplete="off"
          />
          <button class="btn btn-danger rounded-pill px-3" type="submit">Send</button>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch, computed, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, API_URL } from '../api.js';
import { session } from '../stores/session.js';

const route = useRoute();
const router = useRouter();
const matches = ref([]);
const messages = ref([]);
const newMessage = ref('');
const activeUser = ref(route.params.username || '');
const userId = computed(() => session.user?.id);
const messagesContainer = ref(null);
let intervalId;

onMounted(async () => {
  await loadMatches();
  if (activeUser.value) {
    await loadMessages();
  }
  intervalId = setInterval(() => {
    if (activeUser.value) {
      loadMessages();
    }
  }, 5000);
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});

watch(
  () => route.params.username,
  async (value) => {
    activeUser.value = value || '';
    if (activeUser.value) {
      await loadMessages();
    }
  }
);

async function loadMatches() {
  try {
    const data = await api('/matches');
    matches.value = data.matches || [];
  } catch {}
}

async function loadMessages() {
  try {
    const data = await api(`/messages/${activeUser.value}`);
    messages.value = data.messages || [];
    await nextTick();
    scrollToBottom();
  } catch {}
}

function selectMatch(username) {
  router.push(`/chat/${username}`);
}

async function sendMessage() {
  if (!newMessage.value.trim()) return;
  try {
    await api(`/messages/${activeUser.value}`, { method: 'POST', body: { body: newMessage.value.trim() } });
    newMessage.value = '';
    await loadMessages();
  } catch {}
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

function photoUrl(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function isOnline(match) {
  if (!match.last_seen_at) return false;
  return Date.now() - new Date(match.last_seen_at).getTime() < 10 * 60 * 1000;
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.chat-layout {
  display: flex;
  height: calc(100vh - 80px);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 20px rgba(0,0,0,0.08);
  background: #fff;
}

/* Sidebar */
.chat-sidebar {
  width: 280px;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.chat-sidebar-title {
  padding: 16px 16px 12px;
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #1a1a2e;
}

.chat-contact {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  border: none;
  transition: background 0.15s;
}

.chat-contact:hover { background: #f9fafb; }
.chat-contact.active { background: #fef2f2; border-left: 3px solid #ef4444; }

.contact-avatar {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.avatar-img {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ef4444, #f97316);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
}

.contact-info { flex: 1; min-width: 0; }
.contact-name { font-weight: 600; font-size: 0.9rem; color: #1a1a2e; }
.contact-status { font-size: 0.8rem; }

.online-badge { color: #22c55e; font-weight: 600; }

/* Chat main */
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.chat-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #999;
}

.chat-header {
  padding: 12px 20px;
  border-bottom: 1px solid #e5e7eb;
  background: #fafafa;
}

.chat-header-user {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
  background: #f9fafb;
}

.message-row {
  display: flex;
  margin-bottom: 12px;
}

.message-row--me {
  justify-content: flex-end;
}

.message-bubble {
  max-width: 70%;
  padding: 10px 14px;
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  font-size: 0.9rem;
  line-height: 1.4;
  word-wrap: break-word;
}

.message-bubble--me {
  background: linear-gradient(135deg, #ef4444, #f97316);
  color: #fff;
  border-bottom-right-radius: 4px;
}

.message-bubble:not(.message-bubble--me) {
  border-bottom-left-radius: 4px;
}

.message-time {
  font-size: 0.7rem;
  opacity: 0.6;
  margin-top: 4px;
  text-align: right;
}

.chat-input {
  display: flex;
  gap: 8px;
  padding: 12px 20px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
}

.chat-input-field {
  border-radius: 24px;
  background: #f3f4f6;
  border: none;
  padding: 8px 16px;
}

.chat-input-field:focus {
  background: #fff;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}

@media (max-width: 768px) {
  .chat-layout {
    height: calc(100vh - 60px);
    border-radius: 0;
    flex-direction: column;
  }
  
  .chat-sidebar {
    width: 100%;
    max-height: 40vh;
    border-right: none;
    border-bottom: 1px solid #e5e7eb;
  }
  
  .chat-main {
    flex: 1;
    min-height: 0;
  }
}
</style>
