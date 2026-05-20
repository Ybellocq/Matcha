<template>
  <div class="swipe-page">
    <h2 class="swipe-title">Discover</h2>
    
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    
    <div v-if="loading" class="loading-state">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2">Finding profiles for you...</p>
    </div>
    
    <div v-else-if="!currentProfile" class="no-profiles">
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="empty-icon">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <h3>No more profiles</h3>
        <p>Check back later for new matches!</p>
        <button @click="loadProfiles" class="btn btn-primary mt-3">Refresh</button>
      </div>
    </div>
    
    <template v-else>
      <div class="swipe-container">
        <div class="card-stack">
          <!-- Next card (background) -->
          <div v-if="nextProfile" class="profile-card profile-card--next">
            <img :src="photoUrl(nextProfile.main_photo_path, nextProfile)" class="card-img-top" alt="Next profile" />
          </div>
          
          <!-- Current card (active) -->
          <div
            class="profile-card profile-card--active"
            :style="cardStyle"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
          >
            <div class="swipe-indicator swipe-indicator--like" :class="{ 'is-visible': swipeHint === 'like' }">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
              <span>LIKE</span>
            </div>
            <div class="swipe-indicator swipe-indicator--pass" :class="{ 'is-visible': swipeHint === 'pass' }">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              <span>NOPE</span>
            </div>
            
            <div class="card-image-wrapper">
              <img :src="photoUrl(currentProfile.main_photo_path, currentProfile)" class="card-img-top" alt="Profile photo" />
              <div class="card-gradient"></div>
            </div>
            
            <div class="card-info">
              <div class="card-header-info">
                <h3 class="card-name">
                  {{ currentProfile.first_name }}{{ currentProfile.last_name ? ' ' + currentProfile.last_name : '' }}
                  <span v-if="age(currentProfile.birthdate)" class="card-age">{{ age(currentProfile.birthdate) }}</span>
                </h3>
              </div>
              
              <div class="card-details">
                <div v-if="currentProfile.distanceKm" class="detail-item">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  <span>{{ currentProfile.distanceKm.toFixed(1) }} km away</span>
                </div>
                
                <div class="detail-item">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" y1="19" x2="12" y2="22"></line>
                  </svg>
                  <span>Popularity: {{ currentProfile.popularity_score }}</span>
                </div>
              </div>
              
              <div v-if="currentProfile.bio" class="card-bio">
                {{ currentProfile.bio }}
              </div>
              
              <div v-if="currentProfile.tags && currentProfile.tags.length" class="card-tags">
                <span v-for="(tag, index) in currentProfile.tags.slice(0, 5)" :key="index" class="tag">
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="action-buttons">
          <button 
            class="action-btn action-btn--pass" 
            @click="passCurrent" 
            :disabled="actionPending"
            aria-label="Pass"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
          
          <button 
            class="action-btn action-btn--super" 
            @click="viewProfile" 
            :disabled="actionPending"
            aria-label="View profile"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
          </button>
          
          <button 
            class="action-btn action-btn--like" 
            @click="likeCurrent" 
            :disabled="actionPending"
            aria-label="Like"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { api, API_URL } from '../api.js';

const profiles = ref([]);
const currentIndex = ref(0);
const loading = ref(true);
const error = ref('');
const myLocation = ref({ lat: null, lng: null });

// Swipe state
const swipeX = ref(0);
const swipeY = ref(0);
const dragStart = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const isAnimating = ref(false);
const actionPending = ref(false);
const swipeThreshold = 120;

async function loadProfiles() {
  loading.value = true;
  error.value = '';
  try {
    const me = await api('/profile/me');
    myLocation.value = {
      lat: me.profile?.location_lat ?? null,
      lng: me.profile?.location_lng ?? null
    };
    
    const data = await api('/search/suggested');
    profiles.value = (data.results || data.profiles || []).map((profile) => ({
      ...profile,
      distanceKm: computeDistance(
        myLocation.value.lat, 
        myLocation.value.lng, 
        profile.location_lat, 
        profile.location_lng
      )
    }));
    currentIndex.value = 0;
    resetSwipe();
  } catch (err) {
    error.value = err.message || 'Failed to load profiles';
  } finally {
    loading.value = false;
  }
}

function placeholder(profile) {
  return `https://picsum.photos/seed/${profile.user_id || profile.username}/400/400`;
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

function age(dateString) {
  if (!dateString) return null;
  const diff = Date.now() - new Date(dateString).getTime();
  return Math.floor(diff / 31557600000);
}

const currentProfile = computed(() => profiles.value[currentIndex.value] || null);
const nextProfile = computed(() => profiles.value[currentIndex.value + 1] || null);

const swipeHint = computed(() => {
  if (swipeX.value > 40) return 'like';
  if (swipeX.value < -40) return 'pass';
  return '';
});

const cardStyle = computed(() => ({
  transform: `translate3d(${swipeX.value}px, ${swipeY.value}px, 0) rotate(${swipeX.value / 12}deg)`,
  transition: isDragging.value ? 'none' : 'transform 200ms ease'
}));

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
    error.value = err.message || 'Failed to like';
    resetSwipe();
  } finally {
    actionPending.value = false;
  }
}

async function passCurrent() {
  if (!currentProfile.value || actionPending.value) return;
  actionPending.value = true;
  error.value = '';
  try {
    await api(`/profile/${currentProfile.value.username}/pass`, { method: 'POST' });
  } catch (err) {
    // Pass might not be implemented on backend, that's ok
  }
  await animateSwipe('left');
  actionPending.value = false;
}

function viewProfile() {
  if (!currentProfile.value || actionPending.value) return;
  window.location.href = `/users/${currentProfile.value.username}`;
}

onMounted(() => {
  loadProfiles();
});
</script>

<style scoped>
.swipe-page {
  max-width: 500px;
  margin: 0 auto;
  padding: 16px;
}

.swipe-title {
  text-align: center;
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: 20px;
}

.loading-state,
.no-profiles {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  text-align: center;
}

.empty-state {
  padding: 40px 20px;
}

.empty-icon {
  color: #e0e0e0;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 1.25rem;
  color: #666;
  margin-bottom: 8px;
}

.empty-state p {
  color: #999;
  font-size: 0.95rem;
}

.swipe-container {
  position: relative;
}

.card-stack {
  position: relative;
  height: 580px;
  perspective: 1000px;
}

.profile-card {
  position: absolute;
  inset: 0;
  border-radius: 24px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
  cursor: grab;
  touch-action: none;
  user-select: none;
}

.profile-card:active {
  cursor: grabbing;
}

.profile-card--next {
  transform: scale(0.95) translateY(12px);
  filter: blur(1px);
  opacity: 0.7;
  z-index: 1;
}

.profile-card--active {
  z-index: 2;
}

.card-image-wrapper {
  position: relative;
  height: 380px;
  overflow: hidden;
}

.card-image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.card-gradient {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 140px;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0) 100%);
  pointer-events: none;
}

.card-info {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px 20px 20px;
  color: #fff;
}

.card-header-info {
  margin-bottom: 8px;
}

.card-name {
  font-size: 1.6rem;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-age {
  font-weight: 400;
  opacity: 0.9;
}

.card-details {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 10px;
  font-size: 0.9rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 6px;
  opacity: 0.95;
}

.card-bio {
  font-size: 0.9rem;
  line-height: 1.4;
  margin-bottom: 12px;
  opacity: 0.95;
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

.tag {
  background: rgba(255, 255, 255, 0.25);
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.swipe-indicator {
  position: absolute;
  top: 24px;
  padding: 10px 18px;
  border: 4px solid;
  border-radius: 12px;
  font-weight: 800;
  font-size: 1.4rem;
  text-transform: uppercase;
  opacity: 0;
  transition: opacity 150ms ease;
  z-index: 10;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.swipe-indicator svg {
  width: 40px;
  height: 40px;
}

.swipe-indicator--like {
  left: 20px;
  border-color: #198754;
  color: #198754;
  transform: rotate(-15deg);
}

.swipe-indicator--pass {
  right: 20px;
  border-color: #dc3545;
  color: #dc3545;
  transform: rotate(15deg);
}

.swipe-indicator.is-visible {
  opacity: 1;
}

.action-buttons {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
  padding: 16px 0;
}

.action-btn {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.action-btn:hover:not(:disabled) {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.action-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn--pass {
  background: #fff;
  border: 2px solid #dc3545;
  color: #dc3545;
}

.action-btn--pass:hover:not(:disabled) {
  background: #dc3545;
  color: #fff;
}

.action-btn--super {
  width: 50px;
  height: 50px;
  background: #fff;
  border: 2px solid #4f46e5;
  color: #4f46e5;
}

.action-btn--super:hover:not(:disabled) {
  background: #4f46e5;
  color: #fff;
}

.action-btn--like {
  background: #fff;
  border: 2px solid #198754;
  color: #198754;
}

.action-btn--like:hover:not(:disabled) {
  background: #198754;
  color: #fff;
}

/* Mobile responsive */
@media (max-width: 576px) {
  .swipe-page {
    padding: 12px;
  }
  
  .card-stack {
    height: 520px;
  }
  
  .card-image-wrapper {
    height: 320px;
  }
  
  .card-name {
    font-size: 1.4rem;
  }
  
  .action-btn {
    width: 56px;
    height: 56px;
  }
  
  .action-btn--super {
    width: 44px;
    height: 44px;
  }
}
</style>
