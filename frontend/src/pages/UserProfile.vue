<template>
  <div v-if="loading" class="alert alert-info">Loading profile...</div>
  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
  <div v-else>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ profile.first_name }} {{ profile.last_name }}</h2>
      <div>
        <span class="badge bg-success me-2" v-if="matched">Matched</span>
        <span class="badge bg-primary" v-else-if="liked">Liked</span>
      </div>
    </div>
    <p>Popularity: {{ profile.popularity_score }}</p>
    <p>
      Status:
      <span class="badge" :class="isOnline ? 'bg-success' : 'bg-secondary'">
        {{ isOnline ? 'Online' : 'Offline' }}
      </span>
      <span class="ms-2">Last seen: {{ profile.last_seen_at || 'Unknown' }}</span>
    </p>
    <div class="row mb-3">
      <div v-for="photo in photos" :key="photo.id" class="col-md-3 mb-3">
        <img :src="photoUrl(photo.path)" class="img-fluid rounded" alt="Photo" />
      </div>
    </div>
    <p>{{ profile.bio }}</p>
    <p><strong>Tags:</strong> {{ tags.join(', ') }}</p>
    <div class="d-flex gap-2 mb-3">
      <button class="btn btn-outline-primary" v-if="!liked" @click="like">Like</button>
      <button class="btn btn-outline-secondary" v-if="liked" @click="unlike">Unlike</button>
      <button class="btn btn-outline-danger" @click="report">Report</button>
      <button class="btn btn-outline-warning" @click="block">Block</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { api, API_URL } from '../api.js';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const profile = ref({});
const photos = ref([]);
const tags = ref([]);
const liked = ref(false);
const matched = ref(false);
const isOnline = computed(() => {
  if (!profile.value.last_seen_at) return false;
  const lastSeen = new Date(profile.value.last_seen_at).getTime();
  return Date.now() - lastSeen < 10 * 60 * 1000;
});

onMounted(async () => {
  await loadProfile();
});

async function loadProfile() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api(`/profile/${route.params.username}`);
    profile.value = data.profile;
    photos.value = data.photos || [];
    tags.value = data.tags || [];
    liked.value = data.liked;
    matched.value = data.matched;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function like() {
  await api(`/profile/${route.params.username}/like`, { method: 'POST' });
  await loadProfile();
}

async function unlike() {
  await api(`/profile/${route.params.username}/unlike`, { method: 'POST' });
  await loadProfile();
}

async function report() {
  const reason = prompt('Reason for report (optional):') || '';
  await api(`/profile/${route.params.username}/report`, { method: 'POST', body: { reason } });
}

async function block() {
  if (!confirm('Block this user? They will be removed from search and chat.')) return;
  await api(`/profile/${route.params.username}/block`, { method: 'POST' });
}

function photoUrl(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}
</script>
