<template>
  <div>
    <h2 class="mb-3">Matches</h2>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="matches.length === 0" class="alert alert-info">No matches yet.</div>
    <div class="row">
      <div v-for="match in matches" :key="match.username" class="col-md-4 mb-4">
        <div class="card h-100">
          <img :src="photoUrl(match.main_photo_path, match)" class="card-img-top" alt="Match photo" />
          <div class="card-body">
            <h5 class="card-title">{{ match.first_name }} {{ match.last_name }}</h5>
            <p class="card-text">Popularity: {{ match.popularity_score }}</p>
            <router-link class="btn btn-outline-primary btn-sm" :to="`/chat/${match.username}`">Open chat</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api, API_URL } from '../api.js';

const matches = ref([]);
const error = ref('');

onMounted(async () => {
  try {
    const data = await api('/matches');
    matches.value = data.matches || [];
  } catch (err) {
    error.value = err.message;
  }
});

function placeholder(match) {
  return `https://picsum.photos/seed/${match.username}/400/400`;
}

function photoUrl(path, match) {
  if (!path) return placeholder(match);
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}
</script>
