<template>
  <div class="swipe-page">
    <div class="swipe-topbar">
      <h2 class="swipe-title">Découvrir</h2>
      <button class="filter-toggle" @click="showFilters = !showFilters">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        Filtres
      </button>
    </div>

    <div v-if="noPhoto && !loading" class="alert alert-warning no-photo-banner" role="alert">
      <span>📸 Ajoute une photo de profil pour pouvoir liker les profils.</span>
      <router-link to="/profile" class="btn btn-sm btn-primary ms-2">Compléter mon profil</router-link>
    </div>

    <div v-if="showFilters" class="filter-panel">
      <div class="filter-row">
        <label>
          Trier par
          <select v-model="sortBy" class="filter-input">
            <option value="smart">Score intelligent</option>
            <option value="distance">Distance</option>
            <option value="age">Âge</option>
            <option value="popularity">Popularity</option>
            <option value="sharedTags">Tags communs</option>
          </select>
        </label>
        <label>
          Âge min
          <input v-model.number="filters.age_min" type="number" min="18" class="filter-input" />
        </label>
        <label>
          Âge max
          <input v-model.number="filters.age_max" type="number" class="filter-input" />
        </label>
      </div>
      <div class="filter-row">
        <label>
          Popularité min
          <input v-model.number="filters.popularity_min" type="number" class="filter-input" />
        </label>
        <label>
          Popularité max
          <input v-model.number="filters.popularity_max" type="number" class="filter-input" />
        </label>
        <label>
          Distance max (km)
          <input v-model.number="filters.distance_km" type="number" class="filter-input" />
        </label>
      </div>
      <div class="filter-row">
        <label>
          Ville
          <input v-model="filters.location" class="filter-input" placeholder="Paris" />
        </label>
        <label>
          Tags (séparés par des virgules)
          <input v-model="filters.tags" class="filter-input" placeholder="#vegan, #geek" />
        </label>
      </div>
      <button class="btn btn-danger rounded-pill px-4 apply-btn" @click="loadProfiles">Appliquer</button>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    
    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-border text-danger" role="status"></div>
      <p class="mt-3">On cherche des profils près de toi…</p>
    </div>
    
    <!-- Empty state -->
    <div v-else-if="!currentProfile" class="no-profiles">
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#e0e0e0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="empty-icon">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <h3>Tu as vu tout le monde 🎉</h3>
        <p>Élargis tes filtres, actualise pour repartir du début, ou reviens plus tard : de nouveaux profils arrivent !</p>
        <button @click="loadProfiles" class="btn btn-danger mt-3 rounded-pill px-4">Actualiser</button>
      </div>
    </div>
    
    <!-- Swipe stack -->
    <template v-else>
      <div class="swipe-container">
        <div class="card-stack">
          <!-- Next card -->
          <div v-if="nextProfile" class="profile-card profile-card--next">
            <img :src="photoUrl(nextProfile.main_photo_path, nextProfile)" class="card-img-top" alt="" />
          </div>
          
          <!-- Active card -->
          <div
            class="profile-card profile-card--active"
            :style="cardStyle"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @dragstart.prevent
          >
            <!-- Swipe indicators -->
            <div class="swipe-badge swipe-badge--like" :class="{ 'is-visible': swipeHint === 'like' }">LIKE</div>
            <div class="swipe-badge swipe-badge--nope" :class="{ 'is-visible': swipeHint === 'pass' }">NOPE</div>
            
            <div class="card-image-wrapper">
              <img :src="photoUrl(currentProfile.main_photo_path, currentProfile)" class="card-img-top" alt="" draggable="false" />
              <div class="card-gradient"></div>
            </div>
            
            <div class="card-info">
              <div class="card-name-row">
                <h3 class="card-name">
                  {{ currentProfile.first_name }}
                  <span v-if="age(currentProfile.birthdate)" class="card-age">{{ age(currentProfile.birthdate) }}</span>
                </h3>
                <span v-if="isOnline(currentProfile)" class="online-dot"></span>
              </div>
              
              <div class="card-meta">
                <span v-if="currentProfile.distanceKm" class="meta-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  {{ currentProfile.distanceKm.toFixed(1) }} km
                </span>
                <span v-if="currentProfile.popularity_score" class="meta-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  {{ currentProfile.popularity_score }}
                </span>
                <span v-if="currentProfile.sharedTags" class="meta-item">
                  {{ currentProfile.sharedTags }} tag{{ currentProfile.sharedTags > 1 ? 's' : '' }} en commun
                </span>
              </div>
              
              <div v-if="currentProfile.bio" class="card-bio">{{ currentProfile.bio }}</div>
              
              <div v-if="currentProfile.tags && currentProfile.tags.length" class="card-tags">
                <span v-for="(tag, i) in currentProfile.tags.slice(0, 6)" :key="i" class="tag-pill">#{{ tag }}</span>
              </div>
            </div>
            
            <!-- Details overlay (toggle on/off) -->
            <div v-if="showInfo" class="card-details">
              <button class="details-close" @click.stop="toggleInfo" aria-label="Fermer les détails">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
              
              <div v-if="infoLoading" class="details-loading">
                <div class="spinner-border text-danger" role="status"></div>
              </div>
              <div v-else-if="infoError" class="details-error alert alert-danger">{{ infoError }}</div>
              
              <template v-else-if="infoData">
                <div class="details-carousel" v-if="infoData.photos.length">
                  <img
                    v-for="(photo, i) in infoData.photos"
                    :key="photo.id"
                    :src="detailPhotoUrl(photo.path)"
                    class="details-photo"
                    :class="{ active: currentPhoto === i }"
                    alt=""
                    draggable="false"
                  />
                  <div class="photo-nav">
                    <button
                      v-for="(photo, i) in infoData.photos"
                      :key="i"
                      class="photo-dot"
                      :class="{ active: currentPhoto === i }"
                      @click.stop="currentPhoto = i"
                      :aria-label="`Photo ${i + 1}`"
                    ></button>
                  </div>
                  <button v-if="infoData.photos.length > 1" class="photo-arrow photo-arrow--prev" @click.stop="prevPhoto" aria-label="Photo précédente">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                  </button>
                  <button v-if="infoData.photos.length > 1" class="photo-arrow photo-arrow--next" @click.stop="nextPhoto" aria-label="Photo suivante">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                  </button>
                </div>
                
                <div class="details-body">
                  <div class="card-name-row">
                    <h3 class="card-name">
                      {{ infoData.profile.first_name }}{{ infoData.profile.last_name ? ' ' + infoData.profile.last_name : '' }}
                      <span v-if="age(infoData.profile.birthdate)" class="card-age">{{ age(infoData.profile.birthdate) }}</span>
                    </h3>
                    <span v-if="isOnline(infoData.profile)" class="online-dot"></span>
                  </div>
                  
                  <div v-if="infoData.profile.location_city" class="details-city">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    {{ infoData.profile.location_city }}
                  </div>
                  
                  <div class="details-status">
                    <span class="status-badge" :class="isOnline(infoData.profile) ? 'status-badge--online' : 'status-badge--offline'">
                      {{ isOnline(infoData.profile) ? 'En ligne' : 'Hors ligne' }}
                    </span>
                    <span v-if="infoData.profile.popularity_score" class="popularity-badge">⭐ {{ infoData.profile.popularity_score }}</span>
                  </div>
                  
                  <div v-if="infoData.matched" class="details-indicator">💘 Vous êtes connectés ! 💘</div>
                  <div v-else-if="infoData.liked" class="details-indicator details-indicator--liked">❤️ Vous avez liké ce profil</div>
                  
                  <div v-if="infoData.profile.bio" class="details-section">
                    <h4>À propos</h4>
                    <p>{{ infoData.profile.bio }}</p>
                  </div>
                  
                  <div v-if="infoData.tags && infoData.tags.length" class="details-section">
                    <h4>Centres d'intérêt</h4>
                    <div class="details-tags">
                      <span v-for="(tag, i) in infoData.tags" :key="i" class="tag-item">#{{ tag }}</span>
                    </div>
                  </div>
                  
                  <div class="details-section details-grid">
                    <div v-if="infoData.profile.gender" class="details-item">
                      <span class="details-label">Genre</span>
                      <span class="details-value">{{ infoData.profile.gender }}</span>
                    </div>
                    <div v-if="infoData.profile.orientation" class="details-item">
                      <span class="details-label">Orientation</span>
                      <span class="details-value">{{ infoData.profile.orientation }}</span>
                    </div>
                    <div v-if="!isOnline(infoData.profile) && infoData.profile.last_seen_at" class="details-item">
                      <span class="details-label">Dernière connexion</span>
                      <span class="details-value">{{ formatDate(infoData.profile.last_seen_at) }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
        
        <!-- Action buttons -->
        <div class="action-buttons">
          <button class="action-btn action-btn--nope" @click="passCurrent" :disabled="actionPending" aria-label="Pass">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
          <button class="action-btn action-btn--info" :class="{ 'action-btn--info-active': showInfo }" @click="toggleInfo" :disabled="actionPending" aria-label="Détails du profil">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </button>
          <button class="action-btn action-btn--like" @click="likeCurrent" :disabled="actionPending" aria-label="Like">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </button>
        </div>
      </div>
    </template>
    
    <!-- IT'S A MATCH! overlay -->
    <Teleport to="body">
      <Transition name="match-fade">
        <div v-if="showMatch" class="match-overlay" @click="showMatch = false">
          <div class="match-modal">
            <h1 class="match-title">C'est un Match !</h1>
            <p class="match-subtitle">Tu as matché avec {{ matchUser }}</p>
            <div class="match-photos">
              <img :src="matchMyPhoto" class="match-photo" alt="You" />
              <div class="match-heart">💘</div>
              <img :src="matchTheirPhoto" class="match-photo" alt="Match" />
            </div>
            <div class="match-actions">
              <button class="btn btn-outline-light rounded-pill px-4" @click.stop="showMatch = false; loadProfiles()">Continuer à swiper</button>
              <button class="btn btn-light rounded-pill px-4" @click.stop="showMatch = false; $router.push(`/chat/${matchUser}`)">Envoyer un message</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api, API_URL, friendlyError } from '../api.js';

const profiles = ref([]);
const currentIndex = ref(0);
const loading = ref(true);
const error = ref('');
const myPhotoUrl = ref('');
const noPhoto = ref(false);
const myProfile = ref(null);
const sortBy = ref('smart');
const showFilters = ref(false);
const filters = reactive({
  age_min: null,
  age_max: null,
  popularity_min: null,
  popularity_max: null,
  distance_km: null,
  location: '',
  tags: ''
});

// Swipe state
const swipeX = ref(0);
const swipeY = ref(0);
const dragStart = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const isAnimating = ref(false);
const actionPending = ref(false);
const swipeThreshold = 100;

// Match popup
const showMatch = ref(false);
const matchUser = ref('');
const matchMyPhoto = ref('');
const matchTheirPhoto = ref('');

// Details overlay (toggle on/off)
const showInfo = ref(false);
const infoLoading = ref(false);
const infoError = ref('');
const infoData = ref(null);
const currentPhoto = ref(0);

async function loadProfiles() {
  loading.value = true;
  error.value = '';
  try {
    const me = await api('/profile/me');
    myProfile.value = me.profile || null;
    myPhotoUrl.value = me.photos?.[0]?.path ? photoUrlRaw(me.photos[0].path) : '';
    noPhoto.value = myPhotoUrl.value === '';

    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== null && value !== '') params.append(key, value);
    }
    const query = params.toString();
    const data = await api(`/search/suggestions${query ? `?${query}` : ''}`);
    profiles.value = (data.results || []).map((p) => ({
      ...p.profile,
      ...p,
      distanceKm: computeDistance(
        me.profile?.location_lat, me.profile?.location_lng,
        p.profile?.location_lat, p.profile?.location_lng
      )
    }));
    currentIndex.value = 0;
    closeInfo();
    resetSwipe();
  } catch (err) {
    error.value = friendlyError(err) || 'Impossible de charger les profils';
  } finally {
    loading.value = false;
  }
}

function placeholder(profile) {
  return `https://ui-avatars.com/api/?name=${profile.first_name}+${profile.last_name || ''}&background=db3e54&color=fff&size=400&bold=true`;
}

function photoUrl(path, profile) {
  if (!path) return placeholder(profile);
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function photoUrlRaw(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function computeDistance(lat1, lon1, lat2, lon2) {
  if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) return null;
  const toRad = (deg) => (deg * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
}

function age(dateString) {
  if (!dateString) return null;
  return Math.floor((Date.now() - new Date(dateString).getTime()) / 31557600000);
}

function isOnline(profile) {
  if (!profile.last_seen_at) return false;
  const diff = Date.now() - new Date(profile.last_seen_at).getTime();
  return diff < 10 * 60 * 1000;
}

const sortedProfiles = computed(() => {
  const list = [...profiles.value];
  if (sortBy.value === 'distance') {
    list.sort((a, b) => (a.distanceKm ?? Infinity) - (b.distanceKm ?? Infinity));
  } else if (sortBy.value === 'age') {
    list.sort((a, b) => (age(a.birthdate) ?? Infinity) - (age(b.birthdate) ?? Infinity));
  } else if (sortBy.value === 'popularity') {
    list.sort((a, b) => (b.popularity_score || 0) - (a.popularity_score || 0));
  } else if (sortBy.value === 'sharedTags') {
    list.sort((a, b) => (b.sharedTags || 0) - (a.sharedTags || 0));
  }
  if (profiles.value.length && sortBy.value === 'smart') {
    list.sort((a, b) => (b.score || 0) - (a.score || 0));
  }
  return list;
});

const currentProfile = computed(() => sortedProfiles.value[currentIndex.value] || null);
const nextProfile = computed(() => sortedProfiles.value[currentIndex.value + 1] || null);

watch([sortBy, sortedProfiles], () => {
  currentIndex.value = 0;
  closeInfo();
  resetSwipe();
});

const swipeHint = computed(() => {
  if (Math.abs(swipeX.value) < 40) return '';
  return swipeX.value > 0 ? 'like' : 'pass';
});

const cardStyle = computed(() => ({
  transform: `translate3d(${swipeX.value}px, ${swipeY.value}px, 0) rotate(${swipeX.value / 15}deg)`,
  transition: isDragging.value ? 'none' : 'transform 300ms cubic-bezier(0.175, 0.885, 0.32, 1.275)'
}));

function resetSwipe() {
  swipeX.value = 0; swipeY.value = 0;
  isDragging.value = false; isAnimating.value = false;
}

// Pointer events for drag
function onPointerDown(e) {
  if (isAnimating.value || actionPending.value || !currentProfile.value) return;
  isDragging.value = true;
  dragStart.value = { x: e.clientX, y: e.clientY };
  swipeX.value = 0; swipeY.value = 0;
  e.currentTarget.setPointerCapture(e.pointerId);
}

function onPointerMove(e) {
  if (!isDragging.value) return;
  swipeX.value = e.clientX - dragStart.value.x;
  swipeY.value = e.clientY - dragStart.value.y;
}

function onPointerUp(e) {
  if (!isDragging.value) return;
  isDragging.value = false;
  e.currentTarget.releasePointerCapture(e.pointerId);
  if (swipeX.value > swipeThreshold) { likeCurrent(); return; }
  if (swipeX.value < -swipeThreshold) { passCurrent(); return; }
  resetSwipe();
}

async function animateSwipe(dir) {
  isAnimating.value = true;
  closeInfo();
  swipeX.value = dir === 'right' ? 600 : -600;
  await new Promise(r => setTimeout(r, 250));
  currentIndex.value++;
  resetSwipe();
}

function closeInfo() {
  showInfo.value = false;
  infoData.value = null;
  infoError.value = '';
  currentPhoto.value = 0;
}

async function toggleInfo() {
  if (actionPending.value) return;
  if (showInfo.value) {
    closeInfo();
    return;
  }
  const profile = currentProfile.value;
  if (!profile) return;
  currentPhoto.value = 0;
  showInfo.value = true;
  infoLoading.value = true;
  infoError.value = '';
  try {
    const data = await api(`/profile/${profile.username}`);
    infoData.value = {
      profile: data.profile || profile,
      photos: data.photos || [],
      tags: data.tags || [],
      liked: data.liked,
      matched: data.matched
    };
  } catch (err) {
    infoError.value = friendlyError(err) || 'Impossible de charger les détails';
  } finally {
    infoLoading.value = false;
  }
}

async function likeCurrent() {
  if (!currentProfile.value || actionPending.value) return;
  actionPending.value = true;
  error.value = '';
  const profile = currentProfile.value;
  try {
    const result = await api(`/profile/${profile.username}/like`, { method: 'POST' });
    await animateSwipe('right');
    // Check if it was a mutual match
    if (result.matched) {
      matchUser.value = profile.username;
      matchMyPhoto.value = myPhotoUrl.value;
      matchTheirPhoto.value = photoUrl(profile.main_photo_path, profile);
      setTimeout(() => { showMatch.value = true; }, 400);
    }
  } catch (err) {
    error.value = friendlyError(err);
    resetSwipe();
  } finally {
    actionPending.value = false;
  }
}

async function passCurrent() {
  if (!currentProfile.value || actionPending.value) return;
  actionPending.value = true;
  const profile = currentProfile.value;
  // Persister le pass pour ne plus représenter ce profil dans le deck
  api(`/profile/${profile.username}/pass`, { method: 'POST' }).catch(() => {});
  await animateSwipe('left');
  actionPending.value = false;
}

function detailPhotoUrl(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString();
}

function nextPhoto() {
  if (!infoData.value?.photos?.length) return;
  currentPhoto.value = (currentPhoto.value + 1) % infoData.value.photos.length;
}

function prevPhoto() {
  if (!infoData.value?.photos?.length) return;
  currentPhoto.value = (currentPhoto.value - 1 + infoData.value.photos.length) % infoData.value.photos.length;
}

onMounted(() => { loadProfiles(); });
</script>

<style scoped>
.swipe-page {
  max-width: 440px;
  margin: 0 auto;
  padding: 12px 12px 0;
}

.swipe-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.filter-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 8px 16px;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--primary);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.filter-panel {
  background: var(--card);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.07);
  padding: 16px;
  margin: 12px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.filter-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-row label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 100px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.filter-input {
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.9rem;
  background: var(--surface);
  color: var(--text);
}

.apply-btn {
  align-self: flex-end;
}

.swipe-title {
  text-align: center;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.loading-state,
.no-profiles {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 420px;
  text-align: center;
}

.empty-state { padding: 40px 20px; }
.empty-icon { margin-bottom: 12px; }
.empty-state h3 { font-size: 1.2rem; color: var(--text); margin-bottom: 6px; }
.empty-state p { color: var(--text-muted); font-size: 0.9rem; }

.swipe-container { position: relative; }

.card-stack {
  position: relative;
  height: 540px;
}

.profile-card {
  position: absolute;
  inset: 0;
  border-radius: 20px;
  overflow: hidden;
  background: var(--card);
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  cursor: grab;
  touch-action: none;
  user-select: none;
}
.profile-card:active { cursor: grabbing; }
.profile-card img { -webkit-user-drag: none; user-select: none; pointer-events: none; }

.profile-card--next {
  transform: scale(0.95) translateY(10px);
  opacity: 0.5;
  z-index: 1;
}

.profile-card--active { z-index: 2; }

.card-image-wrapper {
  position: relative;
  height: 400px;
  overflow: hidden;
  background: var(--surface);
}

.card-image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.card-gradient {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 160px;
  background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 100%);
  pointer-events: none;
}

.card-info {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: 16px 18px 20px;
  color: #fff;
}

.card-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.card-name {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
}

.card-age {
  font-weight: 400;
  font-size: 1.3rem;
  opacity: 0.85;
}

.online-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #4ade80;
  box-shadow: 0 0 8px rgba(74, 222, 128, 0.5);
}

.card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 8px;
  font-size: 0.85rem;
  opacity: 0.9;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.card-bio {
  font-size: 0.85rem;
  line-height: 1.4;
  margin-bottom: 10px;
  opacity: 0.9;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tag-pill {
  background: rgba(255,255,255,0.2);
  backdrop-filter: blur(4px);
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Swipe badges */
.swipe-badge {
  position: absolute;
  top: 24px;
  padding: 8px 20px;
  border: 4px solid;
  border-radius: 10px;
  font-weight: 900;
  font-size: 1.6rem;
  letter-spacing: 2px;
  z-index: 10;
  opacity: 0;
  transition: opacity 120ms ease;
  pointer-events: none;
}

.swipe-badge--like {
  left: 20px;
  border-color: #22c55e;
  color: #22c55e;
  transform: rotate(-15deg);
}

.swipe-badge--nope {
  right: 20px;
  border-color: #ef4444;
  color: #ef4444;
  transform: rotate(15deg);
}

.swipe-badge.is-visible { opacity: 1; }

/* Details overlay (toggle on/off) */
.card-details {
  position: absolute;
  inset: 0;
  z-index: 20;
  border-radius: 20px;
  background: linear-gradient(180deg, rgba(20,10,20,0.88) 0%, rgba(20,10,20,0.96) 100%);
  backdrop-filter: blur(10px);
  color: #fff;
  overflow-y: auto;
  padding: 16px;
}

.details-close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 5;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: rgba(0,0,0,0.45);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s ease;
}

.details-close:hover { background: #fd267a; }

.details-loading {
  display: flex;
  justify-content: center;
  padding: 60px 0;
}

.details-error { margin: 40px 8px 0; }

.details-carousel {
  position: relative;
  height: 280px;
  border-radius: 14px;
  overflow: hidden;
  background: var(--surface);
  margin-bottom: 16px;
}

.details-photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.details-photo.active { opacity: 1; }

.photo-nav {
  position: absolute;
  top: 12px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 5px;
  z-index: 2;
}

.photo-dot {
  width: 26px;
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
  width: 34px;
  height: 34px;
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

.details-body { padding: 0 4px 8px; }

.details-city {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.85rem;
  opacity: 0.85;
  margin-top: 4px;
}

.details-status {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 10px 0;
}

.status-badge {
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-badge--online { background: #22c55e; color: #fff; }
.status-badge--offline { background: rgba(255,255,255,0.18); color: rgba(255,255,255,0.7); }

.popularity-badge { color: #fbbf24; font-weight: 700; font-size: 0.85rem; }

.details-indicator {
  padding: 8px 14px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.85rem;
  background: linear-gradient(135deg, #fd267a, #ff6036);
  margin-bottom: 12px;
}

.details-indicator--liked { background: #22c55e; }

.details-section { margin-bottom: 16px; }

.details-section h4 {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.65;
  margin: 0 0 6px;
  font-weight: 700;
}

.details-section p {
  font-size: 0.9rem;
  line-height: 1.5;
  margin: 0;
  opacity: 0.95;
}

.details-tags { display: flex; flex-wrap: wrap; gap: 6px; }

.tag-item {
  background: rgba(253, 38, 122, 0.25);
  border: 1px solid rgba(253, 38, 122, 0.6);
  color: #fff;
  padding: 3px 12px;
  border-radius: 14px;
  font-size: 0.8rem;
  font-weight: 700;
}

.details-grid { display: flex; flex-wrap: wrap; gap: 8px; }

.details-item {
  background: rgba(255,255,255,0.1);
  padding: 8px 12px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.details-label { font-size: 0.7rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px; }
.details-value { font-size: 0.85rem; font-weight: 700; }

/* Action buttons */
.action-buttons {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin-top: 20px;
  padding: 8px 0;
}

.action-btn {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: var(--card);
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.action-btn:hover:not(:disabled) { transform: scale(1.12); }
.action-btn:active:not(:disabled) { transform: scale(0.92); }
.action-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.action-btn--nope { color: #ef4444; border: 2px solid #fee2e2; }
.action-btn--nope:hover:not(:disabled) { background: #fef2f2; }

.action-btn--info { 
  width: 46px; height: 46px; 
  color: #6366f1; 
  border: 2px solid #e0e7ff; 
}

.action-btn--info-active {
  background: #6366f1;
  color: #fff;
  border-color: #6366f1;
}

.action-btn--like { color: #22c55e; border: 2px solid #dcfce7; }
.action-btn--like:hover:not(:disabled) { background: #f0fdf4; }

/* Match overlay */
.match-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.match-modal {
  text-align: center;
  padding: 40px;
}

.match-title {
  font-size: 2.8rem;
  font-weight: 900;
  color: #fff;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #f472b6, #fb923c, #facc15);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.match-subtitle {
  color: rgba(255,255,255,0.7);
  font-size: 1.1rem;
  margin-bottom: 30px;
}

.match-photos {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 30px;
}

.match-photo {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #fff;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
}

.match-heart {
  font-size: 2rem;
  animation: match-pulse 0.6s ease-in-out infinite alternate;
}

@keyframes match-pulse {
  from { transform: scale(1); }
  to { transform: scale(1.25); }
}

.match-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.match-actions .btn {
  font-weight: 600;
}

.match-fade-enter-active { transition: opacity 0.3s ease; }
.match-fade-leave-active { transition: opacity 0.2s ease; }
.match-fade-enter-from, .match-fade-leave-to { opacity: 0; }

@media (max-width: 576px) {
  .card-stack { height: 480px; }
  .card-image-wrapper { height: 340px; }
  .action-btn { width: 52px; height: 52px; }
  .action-btn--info { width: 40px; height: 40px; }
  .details-carousel { height: 220px; }
  .match-title { font-size: 2rem; }
  .match-photo { width: 90px; height: 90px; }
}
</style>
