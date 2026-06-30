<template>
  <div class="user-profile-page">
    <div v-if="loading" class="loading-state">
      <div class="spinner-border text-danger" role="status"></div>
      <p class="mt-2">Loading profile...</p>
    </div>
    
    <div v-else-if="error" class="alert alert-danger mx-auto" style="max-width: 500px;">{{ error }}</div>
    
    <div v-else class="profile-card-container">
      <!-- Photo carousel -->
      <div class="photo-section">
        <div v-if="photos.length === 0" class="no-photo">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5-2 4-2 4 2 4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/></svg>
          <p>No photos yet</p>
        </div>
        <div v-else class="photo-carousel">
          <img v-for="(photo, i) in photos" :key="photo.id" :src="photoUrl(photo.path)" class="carousel-photo" :class="{ active: currentPhoto === i }" alt="" />
          <div class="photo-nav">
            <button v-for="(photo, i) in photos" :key="i" class="photo-dot" :class="{ active: currentPhoto === i }" @click="currentPhoto = i"></button>
          </div>
          <button v-if="photos.length > 1" class="photo-arrow photo-arrow--prev" @click="prevPhoto">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button v-if="photos.length > 1" class="photo-arrow photo-arrow--next" @click="nextPhoto">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
      
      <!-- Profile info -->
      <div class="profile-info">
        <div class="info-header">
          <div>
            <h2 class="profile-name">
              {{ profile.first_name }}{{ profile.last_name ? ' ' + profile.last_name : '' }}
              <span v-if="age(profile.birthdate)" class="profile-age">{{ age(profile.birthdate) }}</span>
            </h2>
            <div class="profile-location" v-if="profile.location_city">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              {{ profile.location_city }}
            </div>
          </div>
          <div class="status-badges">
            <span class="status-badge" :class="isOnline ? 'status-badge--online' : 'status-badge--offline'">
              {{ isOnline ? 'Online' : 'Offline' }}
            </span>
            <span class="popularity-badge" v-if="profile.popularity_score">
              ⭐ {{ profile.popularity_score }}
            </span>
          </div>
        </div>

        <div class="match-indicator" v-if="matched">
          <span class="match-heart">💘</span> You matched!
        </div>
        <div class="match-indicator match-indicator--liked" v-else-if="liked">
          <span>❤️</span> You liked this profile
        </div>

        <div v-if="profile.bio" class="info-section">
          <h4>About</h4>
          <p>{{ profile.bio }}</p>
        </div>

        <div v-if="tags.length" class="info-section">
          <h4>Interests</h4>
          <div class="tags-cloud">
            <span v-for="(tag, i) in tags" :key="i" class="tag-item">#{{ tag }}</span>
          </div>
        </div>

        <div class="info-section info-grid">
          <div v-if="profile.gender" class="info-item">
            <span class="info-label">Gender</span>
            <span class="info-value">{{ profile.gender }}</span>
          </div>
          <div v-if="profile.orientation" class="info-item">
            <span class="info-label">Orientation</span>
            <span class="info-value">{{ profile.orientation }}</span>
          </div>
          <div v-if="!isOnline && profile.last_seen_at" class="info-item">
            <span class="info-label">Last seen</span>
            <span class="info-value">{{ formatDate(profile.last_seen_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="profile-actions">
        <button v-if="!liked && !matched" class="action-btn action-btn--like" @click="like">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          Like
        </button>
        <button v-if="liked || matched" class="action-btn action-btn--unlike" @click="unlike">
          Unlike
        </button>
        <button v-if="matched" class="action-btn action-btn--chat" @click="goChat">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Chat
        </button>
        <button class="action-btn action-btn--report" @click="report">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
          Report
        </button>
        <button class="action-btn action-btn--block" @click="block">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          Block
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, API_URL } from '../api.js';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const profile = ref({});
const photos = ref([]);
const tags = ref([]);
const liked = ref(false);
const matched = ref(false);
const currentPhoto = ref(0);

const isOnline = computed(() => {
  if (!profile.value.last_seen_at) return false;
  return Date.now() - new Date(profile.value.last_seen_at).getTime() < 10 * 60 * 1000;
});

onMounted(async () => { await loadProfile(); });

async function loadProfile() {
  loading.value = true; error.value = '';
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

function goChat() {
  router.push(`/chat/${route.params.username}`);
}

function nextPhoto() {
  currentPhoto.value = (currentPhoto.value + 1) % photos.value.length;
}

function prevPhoto() {
  currentPhoto.value = (currentPhoto.value - 1 + photos.value.length) % photos.value.length;
}

function photoUrl(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function age(dateString) {
  if (!dateString) return null;
  return Math.floor((Date.now() - new Date(dateString).getTime()) / 31557600000);
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString();
}
</script>

<style scoped>
.user-profile-page {
  max-width: 480px;
  margin: 0 auto;
  padding: 12px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
}

.profile-card-container {
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

/* Photo carousel */
.photo-section {
  position: relative;
  height: 460px;
  background: #f3f4f6;
}

.no-photo {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #ccc;
}

.photo-carousel {
  position: relative;
  height: 100%;
}

.carousel-photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity 0.3s;
}

.carousel-photo.active { opacity: 1; }

.photo-nav {
  position: absolute;
  top: 16px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 6px;
  z-index: 2;
}

.photo-dot {
  width: 32px;
  height: 4px;
  border-radius: 2px;
  background: rgba(255,255,255,0.5);
  border: none;
  padding: 0;
  cursor: pointer;
}

.photo-dot.active { background: #fff; }

.photo-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0,0,0,0.3);
  border: none;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 2;
  transition: background 0.2s;
}

.photo-arrow:hover { background: rgba(0,0,0,0.5); }
.photo-arrow--prev { left: 8px; }
.photo-arrow--next { right: 8px; }

/* Profile info */
.profile-info { padding: 20px; }

.info-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.profile-name {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  color: #1a1a2e;
}

.profile-age { font-weight: 400; color: #666; margin-left: 4px; }

.profile-location {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #999;
  font-size: 0.85rem;
  margin-top: 4px;
}

.status-badges {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-end;
}

.status-badge {
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-badge--online { background: #dcfce7; color: #16a34a; }
.status-badge--offline { background: #f3f4f6; color: #9ca3af; }

.popularity-badge {
  font-size: 0.85rem;
  color: #f59e0b;
  font-weight: 600;
}

.match-indicator {
  padding: 10px 16px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.9rem;
  margin-bottom: 16px;
  background: linear-gradient(135deg, #fef2f2, #fdf2f8);
  color: #e11d48;
}

.match-indicator--liked {
  background: #f0fdf4;
  color: #16a34a;
}

.info-section {
  margin-bottom: 20px;
}

.info-section h4 {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #999;
  margin-bottom: 8px;
  font-weight: 700;
}

.info-section p {
  font-size: 0.95rem;
  line-height: 1.5;
  color: #444;
  margin: 0;
}

.tags-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-item {
  background: #f3f4f6;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #6366f1;
}

.info-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.info-item {
  background: #f9fafb;
  padding: 10px 14px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label { font-size: 0.75rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
.info-value { font-size: 0.9rem; font-weight: 600; color: #333; }

/* Actions */
.profile-actions {
  display: flex;
  gap: 8px;
  padding: 16px 20px;
  border-top: 1px solid #f3f4f6;
  flex-wrap: wrap;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 24px;
  border: none;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s;
}

.action-btn:hover { transform: translateY(-1px); }

.action-btn--like {
  background: linear-gradient(135deg, #ef4444, #f97316);
  color: #fff;
  flex: 1;
  justify-content: center;
}

.action-btn--chat {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  flex: 1;
  justify-content: center;
}

.action-btn--unlike {
  background: #f3f4f6;
  color: #666;
}

.action-btn--report {
  background: #fef2f2;
  color: #ef4444;
}

.action-btn--block {
  background: #fefce8;
  color: #ca8a04;
}

@media (max-width: 480px) {
  .photo-section { height: 380px; }
  .profile-actions { flex-wrap: wrap; }
  .action-btn { flex: 1 1 auto; justify-content: center; }
}
</style>
