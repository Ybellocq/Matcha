<template>
  <div class="row">
    <div class="col-md-4">
      <h4>Matches</h4>
      <ul class="list-group">
        <li v-for="match in matches" :key="match.username" class="list-group-item d-flex justify-content-between align-items-center">
          <router-link :to="`/chat/${match.username}`">{{ match.username }}</router-link>
        </li>
      </ul>
    </div>
    <div class="col-md-8">
      <h4 v-if="activeUser">Chat with {{ activeUser }}</h4>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <div v-if="!activeUser" class="alert alert-info">Select a match to start chatting.</div>
      <div v-else class="card mb-3">
        <div class="card-body" style="height: 300px; overflow-y: auto;">
          <div v-for="message in messages" :key="message.id" class="mb-2">
            <strong>{{ message.sender_id === userId ? 'Me' : activeUser }}:</strong>
            {{ message.body }}
          </div>
        </div>
      </div>
      <form v-if="activeUser" @submit.prevent="sendMessage" class="d-flex gap-2">
        <input v-model="newMessage" class="form-control" placeholder="Type a message" required />
        <button class="btn btn-primary">Send</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch, computed } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api.js';
import { session } from '../stores/session.js';

const route = useRoute();
const matches = ref([]);
const messages = ref([]);
const error = ref('');
const newMessage = ref('');
const activeUser = ref(route.params.username || '');
const userId = computed(() => session.user?.id);
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
  if (intervalId) {
    clearInterval(intervalId);
  }
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
  } catch (err) {
    error.value = err.message;
  }
}

async function loadMessages() {
  try {
    const data = await api(`/messages/${activeUser.value}`);
    messages.value = data.messages || [];
  } catch (err) {
    error.value = err.message;
  }
}

async function sendMessage() {
  if (!newMessage.value) return;
  try {
    await api(`/messages/${activeUser.value}`, { method: 'POST', body: { body: newMessage.value } });
    newMessage.value = '';
    await loadMessages();
  } catch (err) {
    error.value = err.message;
  }
}
</script>
