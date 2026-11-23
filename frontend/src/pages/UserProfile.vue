<template>
  <div class="user-profile-page">
    <div class="profil-topbar">
      <button class="profil-back" @click="goBack" aria-label="Retour à la sélection">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour
      </button>
    </div>
    <div v-if="loading" class="loading-state">
      <div class="spinner-border text-danger" role="status"></div>
      <p class="mt-2">Chargement du profil…</p>
    </div>
    
    <div v-else-if="error" class="alert alert-danger mx-auto" style="max-width: 500px;">{{ error }}</div>
    
    <div v-else class="profile-card-container">
      <!-- Photo carousel -->
      <div class="photo-section">
        <div v-if="photos.length === 0" class="no-photo">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5-2 4-2 4 2 4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/></svg>
          <p>Aucune photo</p>
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
              {{ isOnline ? 'En ligne' : 'Hors ligne' }}
            </span>
            <span class="popularity-badge" v-if="profile.popularity_score">
              ⭐ {{ profile.popularity_score }}
            </span>
          </div>
        </div>

        <div class="match-indicator" v-if="matched">
          <span class="match-heart">💘</span> Vous êtes connectés ! 💘
        </div>
        <div class="match-indicator match-indicator--liked" v-else-if="liked">
          <span>❤️</span> Vous avez liké ce profil
        </div>

        <div v-if="profile.bio" class="info-section">
          <h4>À propos</h4>
          <p>{{ profile.bio }}</p>
        </div>

        <div v-if="tags.length" class="info-section">
          <h4>Centres d'intérêt</h4>
          <div class="tags-cloud">
            <span v-for="(tag, i) in tags" :key="i" class="tag-item">#{{ tag }}</span>
          </div>
        </div>

        <div class="info-section info-grid">
          <div v-if="profile.gender" class="info-item">
            <span class="info-label">Genre</span>
            <span class="info-value">{{ profile.gender }}</span>
          </div>
          <div v-if="profile.orientation" class="info-item">
            <span class="info-label">Orientation</span>
            <span class="info-value">{{ profile.orientation }}</span>
          </div>
          <div v-if="!isOnline && profile.last_seen_at" class="info-item">
            <span class="info-label">Dernière connexion</span>
            <span class="info-value">{{ formatDate(profile.last_seen_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="profile-actions">
        <button v-if="!liked && !matched" class="action-btn action-btn--like" @click="like">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          Liker
        </button>
        <button v-if="liked || matched" class="action-btn action-btn--unlike" @click="unlike">
          Ne plus liker
        </button>
        <button v-if="matched" class="action-btn action-btn--chat" @click="goChat">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Discuter
        </button>
        <button v-if="matched" class="action-btn action-btn--unmatch" @click="unmatch">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4m0-4l-4 4M3 21l8-8m0 0l8-8m-8 8l-8 8m8-8l8 8"/></svg>
          Unmatch
        </button>
        <button class="action-btn action-btn--report" @click="report">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
          Signaler
        </button>
        <button class="action-btn action-btn--block" @click="block">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          Bloquer
        </button>
        <div v-if="actionError" class="action-error alert alert-danger" style="width: 100%;">
          {{ actionError }}
          <router-link v-if="actionError.includes('photo')" to="/profile" class="alert-link">Ajouter une photo</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, API_URL, friendlyError } from '../api.js';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const profile = ref({});
const photos = ref([]);
const tags = ref([]);
const liked = ref(false);
const matched = ref(false);
const actionError = ref('');
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
  actionError.value = '';
  try {
    await api(`/profile/${route.params.username}/like`, { method: 'POST' });
    await loadProfile();
  } catch (err) {
    actionError.value = friendlyError(err);
  }
}

async function unlike() {
  actionError.value = '';
  try {
    await api(`/profile/${route.params.username}/unlike`, { method: 'POST' });
    await loadProfile();
  } catch (err) {
    actionError.value = friendlyError(err);
  }
}

async function unmatch() {
  if (!confirm('Se dématcher ? La conversation sera supprimée.')) return;
  actionError.value = '';
  try {
    await api(`/profile/${route.params.username}/unmatch`, { method: 'POST' });
    await loadProfile();
  } catch (err) {
    actionError.value = friendlyError(err);
  }
}

async function report() {
  const reason = prompt('Raison du signalement (facultatif) :') || '';
  actionError.value = '';
  try {
    await api(`/profile/${route.params.username}/report`, { method: 'POST', body: { reason } });
    actionError.value = 'Signalement enregistré. Merci !';
  } catch (err) {
    actionError.value = friendlyError(err);
  }
}

async function block() {
  if (!confirm('Bloquer cet utilisateur ? Il disparaîtra de la recherche et le chat sera désactivé.')) return;
  actionError.value = '';
  try {
    await api(`/profile/${route.params.username}/block`, { method: 'POST' });
  } catch (err) {
    actionError.value = friendlyError(err);
  }
}

function goBack() {
  if (window.history.state?.back) {
    router.back();
  } else {
    router.push('/swipe');
  }
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

.profil-topbar {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

.profil-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: 1px solid var(--border, #e5e7eb);
  color: var(--text, #1f2937);
  border-radius: 999px;
  padding: 6px 16px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
}

.profil-back:hover {
  background: var(--primary-soft, #ffe4ec);
  color: var(--primary, #fd297b);
}

.profil-back:active {
  transform: scale(0.96);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
}

.profile-card-container {
  background: var(--card);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

/* Photo carousel */
.photo-section {
  position: relative;
  height: 460px;
  background: var(--surface);
}

.no-photo {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--text-muted);
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
  color: var(--text);
}

.profile-age { font-weight: 400; color: var(--text-muted); margin-left: 4px; }

.profile-location {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--text-muted);
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

.status-badge--online { background: var(--success-soft); color: var(--success); }
.status-badge--offline { background: var(--surface); color: var(--text-muted); }

.popularity-badge {
  font-size: 0.85rem;
  color: #f59e0b;
  font-weight: 600;
}

.match-indicator {
  padding: 10px 16px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  margin-bottom: 16px;
  background: var(--grad);
  color: #fff;
  box-shadow: var(--shadow-sm);
}

.match-indicator--liked {
  background: var(--success-soft);
  color: var(--success);
}

.info-section {
  margin-bottom: 20px;
}

.info-section h4 {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  margin-bottom: 8px;
  font-weight: 700;
}

.info-section p {
  font-size: 0.95rem;
  line-height: 1.5;
  color: var(--text);
  margin: 0;
}

.tags-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-item {
  background: var(--primary-soft);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--primary);
}

.info-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.info-item {
  background: var(--surface);
  padding: 10px 14px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.info-value { font-size: 0.9rem; font-weight: 700; color: var(--text); }

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
  background: var(--grad);
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
  background: var(--surface);
  color: var(--text-muted);
}

.action-btn--report {
  background: rgba(239, 71, 111, 0.1);
  color: var(--danger);
}

.action-btn--block {
  background: rgba(245, 166, 35, 0.12);
  color: #c07c00;
}

.action-btn--unmatch {
  background: #fdf2f8;
  color: #db2777;
}

@media (max-width: 480px) {
  .photo-section { height: 380px; }
  .profile-actions { flex-wrap: wrap; }
  .action-btn { flex: 1 1 auto; justify-content: center; }
}
</style>
