<template>
  <div v-if="loading" class="alert alert-info">Chargement du profil…</div>
  <div v-else>
    <h2 class="mb-3">Mon profil</h2>
    <p class="text-muted">Popularité : {{ form.popularity_score || 0 }}</p>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="success" class="alert alert-success">Profile updated.</div>

    <form @submit.prevent="updateProfile" class="mb-4">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Date de naissance</label>
          <input v-model="form.birthdate" type="date" class="form-control" />
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Prénom</label>
          <input v-model="form.first_name" class="form-control" />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Nom</label>
          <input v-model="form.last_name" class="form-control" />
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Genre</label>
          <select v-model="form.gender" class="form-select">
            <option value="">Non renseigné</option>
            <option value="male">Homme</option>
            <option value="female">Femme</option>
            <option value="non_binary">Non binaire</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Orientation</label>
          <select v-model="form.orientation" class="form-select">
            <option value="bisexual">Bissexuel</option>
            <option value="heterosexual">Hétérosexuel</option>
            <option value="homosexual">Homosexuel</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Biographie</label>
        <textarea v-model="form.bio" class="form-control" rows="3"></textarea>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Ville</label>
          <input v-model="form.location_city" class="form-control" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Latitude</label>
          <input v-model.number="form.location_lat" type="number" step="0.000001" class="form-control" />
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Longitude</label>
          <input v-model.number="form.location_lng" type="number" step="0.000001" class="form-control" />
        </div>
      </div>
      <div class="mb-3">
        <button type="button" class="btn btn-outline-secondary me-2" @click="useGps">📍 GPS</button>
        <button type="button" class="btn btn-outline-secondary" @click="geocodeCity" :disabled="!form.location_city">Géocoder la ville</button>
      </div>
      <div class="mb-3">
        <label class="form-label">Tags (séparés par des virgules)</label>
        <input v-model="tagsInput" class="form-control" />
      </div>
      <button class="btn btn-primary">Enregistrer</button>
    </form>

    <h4 class="mb-2">Photos</h4>
    <div class="mb-3">
      <input type="file" @change="uploadPhoto" accept="image/png,image/jpeg" />
    </div>
    <div class="row">
      <div v-for="photo in photos" :key="photo.id" class="col-md-3 mb-3">
        <div class="card">
          <img :src="photoUrl(photo.path)" class="card-img-top" alt="Photo" />
          <div class="card-body d-flex justify-content-between align-items-center">
            <span class="badge bg-success" v-if="photo.is_main">Principale</span>
            <div class="btn-group">
              <button class="btn btn-sm btn-outline-primary" @click="setMain(photo.id)">Définir principale</button>
              <button class="btn btn-sm btn-outline-danger" @click="deletePhoto(photo.id)">Supprimer</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-md-6">
        <h5>Visites du profil</h5>
        <ul class="list-group">
          <li v-for="view in views" :key="view.created_at + view.username" class="list-group-item">
            {{ view.username }} • {{ view.created_at }}
          </li>
        </ul>
      </div>
      <div class="col-md-6">
        <h5>J'aime reçus</h5>
        <ul class="list-group">
          <li v-for="like in likes" :key="like.created_at + like.username" class="list-group-item">
            {{ like.username }} • {{ like.created_at }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, API_URL } from '../api.js';

const loading = ref(true);
const error = ref('');
const success = ref(false);
const form = reactive({
  email: '',
  first_name: '',
  last_name: '',
  gender: '',
  orientation: 'bisexual',
  bio: '',
  location_city: '',
  location_lat: null,
  location_lng: null,
  birthdate: '',
  popularity_score: 0
});
const photos = ref([]);
const views = ref([]);
const likes = ref([]);
const tagsInput = ref('');

onMounted(async () => {
  await loadProfile();
});

async function loadProfile() {
  loading.value = true;
  try {
    const data = await api('/profile/me');
    Object.assign(form, data.profile || {});
    photos.value = data.photos || [];
    tagsInput.value = (data.tags || []).join(', ');
    const [viewsData, likesData] = await Promise.all([
      api('/profile/views'),
      api('/profile/likes')
    ]);
    views.value = viewsData.views || [];
    likes.value = likesData.likes || [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function updateProfile() {
  error.value = '';
  success.value = false;
  try {
    const tags = tagsInput.value
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean);
    await api('/profile/me', { method: 'PUT', body: { ...form, tags } });
    success.value = true;
  } catch (err) {
    error.value = err.message;
  }
}

async function uploadPhoto(event) {
  const file = event.target.files[0];
  if (!file) return;
  const body = new FormData();
  body.append('photo', file);
  try {
    await api('/photos', { method: 'POST', body });
    await loadProfile();
  } catch (err) {
    error.value = err.message;
  }
}

async function deletePhoto(id) {
  try {
    await api(`/photos/${id}`, { method: 'DELETE' });
    await loadProfile();
  } catch (err) {
    error.value = err.message;
  }
}

async function setMain(id) {
  try {
    await api(`/photos/${id}/main`, { method: 'PATCH' });
    await loadProfile();
  } catch (err) {
    error.value = err.message;
  }
}

function useGps() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition((pos) => {
    form.location_lat = pos.coords.latitude;
    form.location_lng = pos.coords.longitude;
  });
}

function photoUrl(path) {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  return `${API_URL}${path}`;
}

async function geocodeCity() {
  try {
    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(form.location_city)}`);
    const data = await response.json();
    if (data.length > 0) {
      form.location_lat = parseFloat(data[0].lat);
      form.location_lng = parseFloat(data[0].lon);
    }
  } catch {
    error.value = 'Geocoding failed';
  }
}
</script>
