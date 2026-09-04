<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: 'john.llavanes@dexterton.com', password: 'WorkHub2026!', remember: true });
</script>

<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center px-3">
    <div class="card" style="max-width: 460px; width: 100%">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex align-items-center gap-3 mb-4">
          <span class="brand-mark">DW</span>
          <div>
            <h1 class="h4 mb-0">Dexterton WorkHub</h1>
            <div class="text-muted-workhub">Local secure sign-in</div>
          </div>
        </div>
        <form @submit.prevent="form.post('/login')">
          <label class="form-label">Email</label>
          <input v-model="form.email" class="form-control mb-3" type="email" autofocus>
          <label class="form-label">Password</label>
          <input v-model="form.password" class="form-control mb-3" type="password">
          <div class="form-check mb-3">
            <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="remember">Keep me signed in</label>
          </div>
          <div v-if="form.errors.email" class="alert alert-danger py-2">{{ form.errors.email }}</div>
          <button class="btn btn-primary w-100" :disabled="form.processing">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign in
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
