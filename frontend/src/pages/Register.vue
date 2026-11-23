<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <span class="auth-icon">🔥</span>
        <h1>matcha</h1>
        <p class="auth-sub">Crée ton compte en 30 secondes</p>
      </div>

      <div v-if="success" class="text-center">
        <div class="success-icon">📧</div>
        <h3 class="fw-bold">Vérifie ta boîte mail</h3>
        <p class="text-muted">On t'a envoyé un lien de vérification à <strong>{{ form.email }}</strong>. Clique dessus pour activer ton compte.</p>
      </div>

      <div v-if="error" class="alert alert-danger">{{ error }}</div>

      <form v-if="!success" @submit.prevent="submit">
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Prénom</label>
            <input v-model="form.first_name" class="form-control" placeholder="Jean" required />
          </div>
          <div class="col-6">
            <label class="form-label">Nom</label>
            <input v-model="form.last_name" class="form-control" placeholder="Dupont" required />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required autocomplete="email" />
        </div>
        <div class="mb-3">
          <label class="form-label">Nom d'utilisateur</label>
          <input v-model="form.username" class="form-control" placeholder="johndoe" required autocomplete="username" />
        </div>
        <div class="mb-3">
          <label class="form-label">Mot de passe</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="Au moins 8 caractères" required autocomplete="new-password" minlength="8" />
        </div>
        <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 fw-bold">Créer mon compte</button>
      </form>

      <div class="auth-footer" v-if="!success">
        Déjà inscrit ? <router-link to="/login">Connecte-toi</router-link>
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
  background: var(--card);
  border-radius: 20px;
  padding: 36px 32px;
  box-shadow: var(--shadow-lg);
}

.auth-header {
  text-align: center;
  margin-bottom: 24px;
}

.auth-icon { font-size: 2rem; }

.auth-header h1 {
  font-size: 1.8rem;
  font-weight: 800;
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 4px 0;
}

.auth-sub {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

.success-icon { font-size: 3rem; margin-bottom: 12px; }

.form-label {
  font-weight: 600;
  font-size: 0.8rem;
  color: var(--text-muted);
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
  color: var(--text-muted);
}

.auth-footer a {
  color: #fd297b;
  font-weight: 600;
  text-decoration: none;
}
</style>
