<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <span class="auth-icon">🔥</span>
        <h1>matcha</h1>
        <p class="auth-sub">Sign in to find your match</p>
      </div>
      
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      
      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input v-model="form.username" class="form-control" placeholder="Your username" required autocomplete="username" />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" required autocomplete="current-password" />
        </div>
        <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 fw-bold">Sign In</button>
      </form>
      
      <div class="auth-footer">
        <router-link to="/reset-request">Forgot password?</router-link>
        <span class="mx-2">·</span>
        <router-link to="/register">Create account</router-link>
      </div>
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
    if (!session.csrfToken) {
      const csrfData = await fetch(`${API_URL}/auth/csrf`).then(r => r.json());
      session.csrfToken = csrfData.csrfToken;
    }
    await api('/auth/login', { method: 'POST', body: form });
    await refreshSession();
    router.push('/swipe');
  } catch (err) {
    error.value = err.message;
  }
}
</script>

<style scoped>
.auth-page {
  width: 100%;
  max-width: 400px;
}

.auth-card {
  background: #fff;
  border-radius: 20px;
  padding: 40px 32px;
  box-shadow: 0 20px 60px rgba(253, 41, 123, 0.1);
}

.auth-header {
  text-align: center;
  margin-bottom: 28px;
}

.auth-icon { font-size: 2rem; }

.auth-header h1 {
  font-size: 1.8rem;
  font-weight: 800;
  background: linear-gradient(135deg, #fd297b, #ff655b);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 4px 0;
}

.auth-sub {
  color: #999;
  font-size: 0.9rem;
  margin: 0;
}

.form-label {
  font-weight: 600;
  font-size: 0.85rem;
  color: #666;
}

.form-control {
  border-radius: 12px;
  padding: 10px 16px;
  border: 1.5px solid #e5e7eb;
}

.auth-footer {
  text-align: center;
  margin-top: 20px;
  font-size: 0.85rem;
}

.auth-footer a {
  color: #fd297b;
  font-weight: 600;
  text-decoration: none;
}
</style>
