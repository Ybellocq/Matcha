<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <span class="auth-icon">🔥</span>
        <h1>matcha</h1>
        <p class="auth-sub">Create your account</p>
      </div>

      <div v-if="success" class="text-center">
        <div class="success-icon">📧</div>
        <h3 class="fw-bold">Check your email</h3>
        <p class="text-muted">We sent a verification link to <strong>{{ form.email }}</strong>. Click it to activate your account.</p>
      </div>

      <div v-if="error" class="alert alert-danger">{{ error }}</div>

      <form v-if="!success" @submit.prevent="submit">
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">First name</label>
            <input v-model="form.first_name" class="form-control" placeholder="John" required />
          </div>
          <div class="col-6">
            <label class="form-label">Last name</label>
            <input v-model="form.last_name" class="form-control" placeholder="Doe" required />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required autocomplete="email" />
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input v-model="form.username" class="form-control" placeholder="johndoe" required autocomplete="username" />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="At least 8 characters" required autocomplete="new-password" minlength="8" />
        </div>
        <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 fw-bold">Create Account</button>
      </form>

      <div class="auth-footer" v-if="!success">
        Already have an account? <router-link to="/login">Sign in</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { api } from '../api.js';

const form = reactive({ email: '', username: '', first_name: '', last_name: '', password: '' });
const error = ref('');
const success = ref(false);

async function submit() {
  error.value = '';
  try {
    await api('/auth/register', { method: 'POST', body: form });
    success.value = true;
  } catch (err) {
    error.value = err.message;
  }
}
</script>

<style scoped>
.auth-page {
  width: 100%;
  max-width: 440px;
}

.auth-card {
  background: #fff;
  border-radius: 20px;
  padding: 36px 32px;
  box-shadow: 0 20px 60px rgba(253, 41, 123, 0.1);
}

.auth-header {
  text-align: center;
  margin-bottom: 24px;
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

.success-icon { font-size: 3rem; margin-bottom: 12px; }

.form-label {
  font-weight: 600;
  font-size: 0.8rem;
  color: #666;
  margin-bottom: 4px;
}

.form-control {
  border-radius: 12px;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  font-size: 0.9rem;
}

.auth-footer {
  text-align: center;
  margin-top: 20px;
  font-size: 0.85rem;
  color: #999;
}

.auth-footer a {
  color: #fd297b;
  font-weight: 600;
  text-decoration: none;
}
</style>
