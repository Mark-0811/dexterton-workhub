<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';

const props = defineProps<{ profile: any; accessSummary: any }>();

const form = useForm({
  name: props.profile.name || '',
  username: props.profile.username || '',
  title: props.profile.title || '',
  timezone: props.profile.timezone || 'Asia/Manila',
  preferences: {
    theme: props.profile.preferences?.theme || localStorage.getItem('theme') || 'light',
    email_deadline_alerts: props.profile.preferences?.email_deadline_alerts ?? true,
    in_app_notifications: props.profile.preferences?.in_app_notifications ?? true,
  },
  current_password: '',
  password: '',
  password_confirmation: '',
});

function save() {
  form.patch('/profile', {
    preserveScroll: true,
    onSuccess: () => {
      if (form.preferences.theme) {
        localStorage.setItem('theme', form.preferences.theme);
        localStorage.setItem('workhub-theme', form.preferences.theme);
        document.documentElement.setAttribute('data-bs-theme', form.preferences.theme);
        document.body.classList.remove('light', 'dark');
        document.body.classList.add(form.preferences.theme);
      }
      form.current_password = '';
      form.password = '';
      form.password_confirmation = '';
    },
  });
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading">
      <h3>Profile settings</h3>
      <p class="text-muted-workhub mb-0">Update your name, job title, notification preferences, theme, and password.</p>
    </div>

    <form class="row g-4" @submit.prevent="save">
      <div class="col-xl-7">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Personal details</h4>
            <p class="text-muted mb-0 small">{{ profile.email }}</p>
          </div>
          <div class="card-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input v-model="form.name" class="form-control">
              <div v-if="form.errors.name" class="invalid-feedback d-block">{{ form.errors.name }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Username</label>
              <input v-model="form.username" class="form-control">
              <div v-if="form.errors.username" class="invalid-feedback d-block">{{ form.errors.username }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Job title</label>
              <input v-model="form.title" class="form-control" placeholder="e.g. Software Developer">
              <div v-if="form.errors.title" class="invalid-feedback d-block">{{ form.errors.title }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Timezone</label>
              <select v-model="form.timezone" class="form-select">
                <option value="Asia/Manila">Asia/Manila</option>
                <option value="Asia/Singapore">Asia/Singapore</option>
                <option value="UTC">UTC</option>
              </select>
              <div v-if="form.errors.timezone" class="invalid-feedback d-block">{{ form.errors.timezone }}</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h4 class="card-title">Change password</h4></div>
          <div class="card-body row g-3">
            <div class="col-md-4">
              <label class="form-label">Current password</label>
              <input v-model="form.current_password" type="password" class="form-control">
              <div v-if="form.errors.current_password" class="invalid-feedback d-block">{{ form.errors.current_password }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">New password</label>
              <input v-model="form.password" type="password" class="form-control">
              <div v-if="form.errors.password" class="invalid-feedback d-block">{{ form.errors.password }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Confirm password</label>
              <input v-model="form.password_confirmation" type="password" class="form-control">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h4 class="card-title">My granted access</h4>
            <p class="text-muted mb-0 small">What your admin has enabled for your account.</p>
          </div>
          <div class="card-body">
            <div class="form-section-title mb-2">Apps</div>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <span v-for="app in accessSummary.apps" :key="app" class="badge badge-soft-primary text-capitalize">{{ app.replace('-', ' ') }}</span>
              <span v-if="!accessSummary.apps?.length" class="text-muted-workhub">All apps allowed by role</span>
            </div>
            <div class="form-section-title mb-2">Visible Spaces</div>
            <div class="d-flex flex-wrap gap-2">
              <span v-for="space in accessSummary.spaces" :key="space.id" class="space-pill" :style="{ backgroundColor: space.color }">{{ space.department }} · {{ space.name }}</span>
              <span v-if="!accessSummary.spaces?.length" class="text-muted-workhub">No Spaces assigned yet</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-5">
        <div class="card">
          <div class="card-header"><h4 class="card-title">Preferences</h4></div>
          <div class="card-body">
            <label class="form-label">Theme</label>
            <div class="d-flex gap-2 mb-4">
              <label class="btn" :class="form.preferences.theme === 'light' ? 'btn-primary' : 'btn-outline-primary'">
                <input v-model="form.preferences.theme" class="d-none" type="radio" value="light"> Light
              </label>
              <label class="btn" :class="form.preferences.theme === 'dark' ? 'btn-primary' : 'btn-outline-primary'">
                <input v-model="form.preferences.theme" class="d-none" type="radio" value="dark"> Dark
              </label>
            </div>

            <div class="form-check form-switch mb-3">
              <input id="emailDeadlineAlerts" v-model="form.preferences.email_deadline_alerts" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="emailDeadlineAlerts">Email me project/task deadline alerts</label>
            </div>
            <div class="form-check form-switch">
              <input id="inAppNotifications" v-model="form.preferences.in_app_notifications" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="inAppNotifications">Show in-app notifications</label>
            </div>
          </div>
          <div class="card-footer text-end">
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Save profile
            </button>
          </div>
        </div>
      </div>
    </form>
  </WorkHubLayout>
</template>
