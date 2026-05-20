<template>
  <div>
    <h2 class="mb-3">Advanced search</h2>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <form @submit.prevent="search" class="mb-4">
      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label">Age min</label>
          <input v-model.number="filters.age_min" type="number" class="form-control" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Age max</label>
          <input v-model.number="filters.age_max" type="number" class="form-control" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Popularity min</label>
          <input v-model.number="filters.popularity_min" type="number" class="form-control" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Popularity max</label>
          <input v-model.number="filters.popularity_max" type="number" class="form-control" />
        </div>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Distance max (km)</label>
          <input v-model.number="filters.distance_km" type="number" class="form-control" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Location (city)</label>
          <input v-model="filters.location" class="form-control" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Tags (comma separated)</label>
          <input v-model="filters.tags" class="form-control" />
        </div>
      </div>
      <button class="btn btn-primary">Search</button>
    </form>

    <div class="d-flex align-items-center mb-3" v-if="results.length">
      <label class="me-2">Sort by</label>
      <select v-model="sortBy" class="form-select w-auto">
        <option value="popularity">Popularity</option>
        <option value="age">Age</option>
        <option value="distance">Distance</option>
      </select>
    </div>
    <div v-if="sortedResults.length === 0" class="alert alert-info">No results yet.</div>
    <div v-else class="swipe-wrapper">
      <div v-if="!currentProfile" class="alert alert-info">No more profiles in this search.</div>
      <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-2 swipe-header">
          <div class="text-muted">Profile {{ currentIndex + 1 }} / {{ sortedResults.length }}</div>
          <div class="text-muted small">Swipe left/right or use the buttons</div>
        </div>
        <div class="swipe-stage">
          <div v-if="nextProfile" class="card swipe-card swipe-card--next">
            <img :src="photoUrl(nextProfile.main_photo_path, nextProfile)" class="card-img-top" alt="Next profile photo" />
          </div>
          <div
            class="card swipe-card"
            :style="cardStyle"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
          >
            <div class="swipe-badge swipe-badge--like" :class="{ 'is-visible': swipeHint === 'like' }">Like</div>
            <div class="swipe-badge swipe-badge--pass" :class="{ 'is-visible': swipeHint === 'pass' }">Pass</div>
            <img :src="photoUrl(currentProfile.main_photo_path, currentProfile)" class="card-img-top" alt="Profile photo" />
            <div class="card-body">
              <h5 class="card-title">{{ currentProfile.first_name }} {{ currentProfile.last_name }}</h5>
              <p class="card-text">
                <span v-if="age(currentProfile.birthdate)">Age: {{ age(currentProfile.birthdate) }}</span>
                <span v-if="currentProfile.distanceKm"> • {{ currentProfile.distanceKm.toFixed(1) }} km</span>
              </p>
              <p class="card-text">Popularity: {{ currentProfile.popularity_score }}</p>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-center gap-3 mt-3 swipe-actions">
          <button class="btn swipe-action swipe-action--pass" @click="passCurrent" :disabled="actionPending">
            Pass
          </button>
          <router-link class="btn swipe-action swipe-action--view" :to="`/users/${currentProfile.username}`">
            View profile
          </router-link>
          <button class="btn swipe-action swipe-action--like" @click="likeCurrent" :disabled="actionPending">
            Like
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { api, API_URL } from '../api.js';

const filters = reactive({
  age_min: null,
  age_max: null,
  popularity_min: null,
  popularity_max: null,
  distance_km: null,
  location: '',
  tags: ''
});
const results = ref([]);
const error = ref('');
const sortBy = ref('popularity');
const myLocation = ref({ lat: null, lng: null });
const currentIndex = ref(0);
const swipeX = ref(0);
const swipeY = ref(0);
const dragStart = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const isAnimating = ref(false);
const actionPending = ref(false);
const swipeThreshold = 120;

function buildQuery() {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== null && value !== '') {
      params.append(key, value);
    }
  }
  return params.toString();
}

function placeholder(profile) {
  return `https://picsum.photos/seed/${profile.user_id}/400/400`;
}

function photoUrl(path, profile) {
  if (!path) return placeholder(profile);
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

function computeDistance(lat1, lon1, lat2, lon2) {
  if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) return null;
  const toRad = (deg) => (deg * Math.PI) / 180;
  const earth = 6371;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  const c = 2 * Math.asin(Math.min(1, Math.sqrt(a)));
  return earth * c;
}

async function search() {
  error.value = '';
  try {
    const me = await api('/profile/me');
    myLocation.value = {
      lat: me.profile?.location_lat ?? null,
      lng: me.profile?.location_lng ?? null
    };
    const query = buildQuery();
    const data = await api(`/search/advanced?${query}`);
    results.value = (data.results || []).map((profile) => ({
      ...profile,
      distanceKm: computeDistance(myLocation.value.lat, myLocation.value.lng, profile.location_lat, profile.location_lng)
    }));
    currentIndex.value = 0;
    resetSwipe();
  } catch (err) {
    error.value = err.message;
  }
}

function age(dateString) {
  if (!dateString) return null;
  const diff = Date.now() - new Date(dateString).getTime();
  return Math.floor(diff / 31557600000);
}

const sortedResults = computed(() => {
  const data = [...results.value];
  if (sortBy.value === 'age') {
    return data.sort((a, b) => (age(a.birthdate) || 0) - (age(b.birthdate) || 0));
  }
  if (sortBy.value === 'distance') {
    return data.sort((a, b) => (a.distanceKm || 0) - (b.distanceKm || 0));
  }
  return data.sort((a, b) => (b.popularity_score || 0) - (a.popularity_score || 0));
});

const currentProfile = computed(() => sortedResults.value[currentIndex.value] || null);
const nextProfile = computed(() => sortedResults.value[currentIndex.value + 1] || null);
const swipeHint = computed(() => {
  if (swipeX.value > 40) return 'like';
  if (swipeX.value < -40) return 'pass';
  return '';
});
const cardStyle = computed(() => ({
  transform: `translate3d(${swipeX.value}px, ${swipeY.value}px, 0) rotate(${swipeX.value / 12}deg)`,
  transition: isDragging.value ? 'none' : 'transform 200ms ease'
}));

watch([results, sortBy], () => {
  currentIndex.value = 0;
  resetSwipe();
});

function resetSwipe() {
  swipeX.value = 0;
  swipeY.value = 0;
  isDragging.value = false;
  isAnimating.value = false;
}

function onPointerDown(event) {
  if (isAnimating.value || actionPending.value || !currentProfile.value) return;
  isDragging.value = true;
  dragStart.value = { x: event.clientX, y: event.clientY };
  swipeX.value = 0;
  swipeY.value = 0;
  event.currentTarget.setPointerCapture(event.pointerId);
}

function onPointerMove(event) {
  if (!isDragging.value) return;
  swipeX.value = event.clientX - dragStart.value.x;
  swipeY.value = event.clientY - dragStart.value.y;
}

function onPointerUp(event) {
  if (!isDragging.value) return;
  isDragging.value = false;
  event.currentTarget.releasePointerCapture(event.pointerId);
  if (swipeX.value > swipeThreshold) {
    likeCurrent();
    return;
  }
  if (swipeX.value < -swipeThreshold) {
    passCurrent();
    return;
  }
  resetSwipe();
}

async function animateSwipe(direction) {
  isAnimating.value = true;
  swipeX.value = direction === 'right' ? 500 : -500;
  swipeY.value = 0;
  await new Promise((resolve) => setTimeout(resolve, 200));
  currentIndex.value += 1;
  resetSwipe();
}

async function likeCurrent() {
  if (!currentProfile.value || actionPending.value) return;
  actionPending.value = true;
  error.value = '';
  try {
    await api(`/profile/${currentProfile.value.username}/like`, { method: 'POST' });
    await animateSwipe('right');
  } catch (err) {
    error.value = err.message;
    resetSwipe();
  } finally {
    actionPending.value = false;
  }
}

async function passCurrent() {
  if (!currentProfile.value || actionPending.value) return;
  actionPending.value = true;
  error.value = '';
  await animateSwipe('left');
  actionPending.value = false;
}
</script>

<style scoped>
.swipe-wrapper {
  max-width: 440px;
  margin: 0 auto;
  padding: 18px;
  border-radius: 22px;
  background: linear-gradient(180deg, #fff2f2 0%, #ffffff 100%);
  box-shadow: 0 12px 30px rgba(13, 37, 62, 0.1);
}

.swipe-stage {
  position: relative;
  height: 560px;
}

.swipe-card {
  position: absolute;
  inset: 0;
  overflow: hidden;
  touch-action: none;
  user-select: none;
  cursor: grab;
  border-radius: 20px;
  box-shadow: 0 14px 30px rgba(13, 37, 62, 0.16);
}

.swipe-card:active {
  cursor: grabbing;
}

.swipe-card img {
  height: 360px;
  object-fit: cover;
}

.swipe-card--next {
  transform: scale(0.96) translateY(10px);
  filter: blur(0.6px);
  opacity: 0.9;
}

.swipe-badge {
  position: absolute;
  top: 20px;
  padding: 6px 14px;
  border: 2px solid;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.95rem;
  text-transform: uppercase;
  opacity: 0;
  transition: opacity 120ms ease;
  z-index: 2;
  background: rgba(255, 255, 255, 0.85);
}

.swipe-badge--like {
  left: 16px;
  color: #198754;
  border-color: #198754;
  transform: rotate(-12deg);
}

.swipe-badge--pass {
  right: 16px;
  color: #dc3545;
  border-color: #dc3545;
  transform: rotate(12deg);
}

.swipe-badge.is-visible {
  opacity: 1;
}

.swipe-header {
  font-weight: 600;
}

.swipe-actions .swipe-action {
  min-width: 110px;
  border-radius: 999px;
  font-weight: 600;
  padding: 8px 16px;
}

.swipe-action--pass {
  background: #fff1f1;
  border: 2px solid #dc3545;
  color: #dc3545;
}

.swipe-action--like {
  background: #ecfff3;
  border: 2px solid #198754;
  color: #198754;
}

.swipe-action--view {
  background: #eef2ff;
  border: 2px solid #4f46e5;
  color: #4f46e5;
}

.swipe-action:disabled {
  opacity: 0.6;
}
</style>
