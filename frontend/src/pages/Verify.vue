<template>
  <div class="text-center">
    <h2>Vérification de l'e-mail</h2>
    <div v-if="status === 'pending'" class="alert alert-info">Vérification en cours…</div>
    <div v-if="status === 'success'" class="alert alert-success">Compte vérifié ! Tu peux te connecter.</div>
    <div v-if="status === 'error'" class="alert alert-danger">{{ error }}</div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api.js';

const route = useRoute();
const status = ref('pending');
const error = ref('');

onMounted(async () => {
  try {
    const token = route.query.token;
    await api('/auth/verify', { method: 'POST', body: { token } });
    status.value = 'success';
  } catch (err) {
    status.value = 'error';
    error.value = err.message;
  }
});
</script>
