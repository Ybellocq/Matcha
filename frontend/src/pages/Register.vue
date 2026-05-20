<template>
  <div class="row justify-content-center">
    <div class="col-md-7">
      <h2 class="mb-3">Register</h2>
      <div v-if="success" class="alert alert-success">
        Registration complete. Check your email to verify your account.
      </div>
      <div v-if="error" class="alert alert-danger">{{ error }}</div>
      <form v-if="!success" @submit.prevent="submit">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First name</label>
            <input v-model="form.first_name" class="form-control" required />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Last name</label>
            <input v-model="form.last_name" class="form-control" required />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input v-model="form.username" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-control" required />
        </div>
        <button class="btn btn-success w-100">Create account</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { api } from '../api.js';

const form = reactive({
  email: '',
  username: '',
  first_name: '',
  last_name: '',
  password: ''
});
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
