<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h2 class="mb-3">Reset password</h2>
      <div v-if="success" class="alert alert-success">Check your email for the reset link.</div>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <form v-if="!success" @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input v-model="email" type="email" class="form-control" required />
        </div>
        <button class="btn btn-primary w-100">Send reset link</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { api } from '../api.js';

const email = ref('');
const error = ref('');
const success = ref(false);

async function submit() {
  error.value = '';
  try {
    await api('/auth/password/request', { method: 'POST', body: { email: email.value } });
    success.value = true;
  } catch (err) {
    error.value = err.message;
  }
}
</script>
