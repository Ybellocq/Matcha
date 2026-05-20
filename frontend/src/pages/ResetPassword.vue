<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h2 class="mb-3">Set new password</h2>
      <div v-if="success" class="alert alert-success">Password updated. You can log in.</div>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <form v-if="!success" @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label">New password</label>
          <input v-model="password" type="password" class="form-control" required />
        </div>
        <button class="btn btn-success w-100">Update password</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api.js';

const route = useRoute();
const password = ref('');
const error = ref('');
const success = ref(false);

async function submit() {
  error.value = '';
  try {
    await api('/auth/password/reset', {
      method: 'POST',
      body: { token: route.query.token, password: password.value }
    });
    success.value = true;
  } catch (err) {
    error.value = err.message;
  }
}
</script>
