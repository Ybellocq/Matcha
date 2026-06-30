<template>
  <div class="matches-page">
    <h2 class="matches-title">Your Matches</h2>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    
    <div v-if="matches.length === 0" class="empty-matches">
      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      <h4>No matches yet</h4>
      <p>Keep swiping to find your perfect match!</p>
    </div>
    
    <div v-else class="matches-grid">
      <div v-for="match in matches" :key="match.username" class="match-card">
        <div class="match-card-photo">
          <img :src="photoUrl(match.main_photo_path, match)" alt="" />
          <div class="match-online" v-if="isOnline(match)"></div>
        </div>
        <div class="match-card-info">
          <h4>{{ match.first_name }}</h4>
          <div class="match-card-meta">
            <span v-if="match.popularity_score">⭐ {{ match.popularity_score }}</span>
          </div>
          <router-link class="btn btn-danger btn-sm rounded-pill px-3" :to="`/chat/${match.username}`">
            Message
          </router-link>
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
  return `https://ui-avatars.com/api/?name=${match.first_name}+${match.last_name || ''}&background=db3e54&color=fff&size=400&bold=true`;
}

function photoUrl(path, match) {
  if (!path) return placeholder(match);
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function isOnline(match) {
  if (!match.last_seen_at) return false;
  return Date.now() - new Date(match.last_seen_at).getTime() < 10 * 60 * 1000;
}
</script>

<style scoped>
.matches-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 16px;
}

.matches-title {
  text-align: center;
  font-size: 1.5rem;
  font-weight: 800;
  color: #1a1a2e;
  margin-bottom: 24px;
}

.empty-matches {
  text-align: center;
  padding: 60px 20px;
  color: #999;
}

.empty-matches h4 {
  margin-top: 12px;
  font-weight: 700;
  color: #666;
}

.matches-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 16px;
}

.match-card {
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  transition: transform 0.2s;
}

.match-card:hover { transform: translateY(-3px); }

.match-card-photo {
  position: relative;
  height: 200px;
  overflow: hidden;
  background: #f3f4f6;
}

.match-card-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.match-online {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #22c55e;
  border: 2px solid #fff;
  box-shadow: 0 0 8px rgba(34,197,94,0.4);
}

.match-card-info {
  padding: 12px 14px;
  text-align: center;
}

.match-card-info h4 {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 4px;
  color: #1a1a2e;
}

.match-card-meta {
  font-size: 0.8rem;
  color: #f59e0b;
  font-weight: 600;
  margin-bottom: 10px;
}

@media (max-width: 400px) {
  .matches-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .match-card-photo { height: 160px; }
}
</style>
