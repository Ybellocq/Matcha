<template>
  <div>
    <h2 class="mb-3">Suggested profiles</h2>
    <div class="card mb-3">
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-2">
            <label class="form-label">Age min</label>
            <input v-model.number="filters.ageMin" type="number" class="form-control" />
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Age max</label>
            <input v-model.number="filters.ageMax" type="number" class="form-control" />
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Popularity min</label>
            <input v-model.number="filters.popularityMin" type="number" class="form-control" />
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label">Popularity max</label>
            <input v-model.number="filters.popularityMax" type="number" class="form-control" />
          </div>
        </div>
        <div class="row align-items-end">
          <div class="col-md-3 mb-2">
            <label class="form-label">Distance max (km)</label>
            <input v-model.number="filters.distanceMax" type="number" class="form-control" />
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Tags (comma separated)</label>
            <input v-model="filters.tags" class="form-control" />
          </div>
          <div class="col-md-5 mb-2">
            <label class="form-label">Sort by</label>
            <select v-model="sortBy" class="form-select">
              <option value="score">Match score</option>
              <option value="age">Age</option>
              <option value="distance">Distance</option>
              <option value="popularity">Popularity</option>
              <option value="tags">Shared tags</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="sortedResults.length === 0" class="alert alert-info">No suggestions yet.</div>
    <div class="row">
      <div v-for="item in sortedResults" :key="item.profile.user_id" class="col-md-4 mb-4">
        <div class="card h-100">
          <img :src="photoUrl(item.profile.main_photo_path, item)" class="card-img-top" alt="Profile photo" />
          <div class="card-body">
            <h5 class="card-title">{{ item.profile.first_name }} {{ item.profile.last_name }}</h5>
            <p class="card-text">
              <span v-if="age(item.profile.birthdate)">Age: {{ age(item.profile.birthdate) }}</span>
              <span v-if="item.distanceKm"> • {{ item.distanceKm.toFixed(1) }} km</span>
            </p>
            <p class="card-text">Shared tags: {{ item.sharedTags }}</p>
            <router-link class="btn btn-outline-primary btn-sm" :to="`/users/${item.profile.username}`">View profile</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed, reactive } from 'vue';
import { api, API_URL } from '../api.js';

const results = ref([]);
const error = ref('');
const sortBy = ref('score');
const filters = reactive({
  ageMin: null,
  ageMax: null,
  popularityMin: null,
  popularityMax: null,
  distanceMax: null,
  tags: ''
});

onMounted(async () => {
  try {
    const data = await api('/search/suggestions');
    results.value = data.results || [];
  } catch (err) {
    error.value = err.message;
  }
});

function age(dateString) {
  if (!dateString) return null;
  const diff = Date.now() - new Date(dateString).getTime();
  return Math.floor(diff / 31557600000);
}

function placeholder(item) {
  return `https://picsum.photos/seed/${item.profile.user_id}/400/400`;
}

function photoUrl(path, item) {
  if (!path) return placeholder(item);
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

const filteredResults = computed(() => {
  const tags = filters.tags
    .split(',')
    .map((t) => t.trim().toLowerCase())
    .filter(Boolean);
  return results.value.filter((item) => {
    const candidateAge = age(item.profile.birthdate);
    if (filters.ageMin && (candidateAge === null || candidateAge < filters.ageMin)) return false;
    if (filters.ageMax && (candidateAge === null || candidateAge > filters.ageMax)) return false;
    if (filters.popularityMin && item.profile.popularity_score < filters.popularityMin) return false;
    if (filters.popularityMax && item.profile.popularity_score > filters.popularityMax) return false;
    if (filters.distanceMax && (item.distanceKm === null || item.distanceKm > filters.distanceMax)) return false;
    if (tags.length) {
      const candidateTags = (item.tags || []).map((t) => t.toLowerCase());
      if (!tags.some((tag) => candidateTags.includes(tag))) {
        return false;
      }
    }
    return true;
  });
});

const sortedResults = computed(() => {
  const data = [...filteredResults.value];
  if (sortBy.value === 'age') {
    return data.sort((a, b) => (age(a.profile.birthdate) || 0) - (age(b.profile.birthdate) || 0));
  }
  if (sortBy.value === 'distance') {
    return data.sort((a, b) => (a.distanceKm || 0) - (b.distanceKm || 0));
  }
  if (sortBy.value === 'popularity') {
    return data.sort((a, b) => (b.profile.popularity_score || 0) - (a.profile.popularity_score || 0));
  }
  if (sortBy.value === 'tags') {
    return data.sort((a, b) => (b.sharedTags || 0) - (a.sharedTags || 0));
  }
  return data.sort((a, b) => (b.score || 0) - (a.score || 0));
});
</script>
