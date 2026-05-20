<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h2 class="mb-3">Login</h2>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input v-model="form.username" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-control" required />
        </div>
        <button class="btn btn-primary w-100">Login</button>
        <div class="mt-2">
          <router-link to="/reset-request">Forgot password?</router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, refreshSession, API_URL } from '../api.js';
import { session } from '../stores/session.js';

const router = useRouter();
const error = ref('');
const form = reactive({ username: '', password: '' });

async function submit() {
  error.value = '';
  try {
    // Get CSRF token if not already present
    if (!session.csrfToken) {
      const csrfData = await fetch(`${API_URL}/auth/csrf`).then(r => r.json());
      session.csrfToken = csrfData.csrfToken;
    }
    
    await api('/auth/login', { method: 'POST', body: form });
    await refreshSession();
    router.push('/');
  } catch (err) {
    error.value = err.message;
  }
}
</script>
