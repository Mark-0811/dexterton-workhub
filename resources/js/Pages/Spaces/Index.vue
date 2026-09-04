<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';

defineProps<{ spaces: any[]; users: any[] }>();

const form = useForm({
  name: '',
  department: '',
  owner_id: '',
  color: '#435ebe',
  description: '',
  active: true,
});
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-start">
      <div>
        <h3>Spaces</h3>
        <p class="text-muted-workhub mb-0">Create department spaces like SoftDev, IT, Finance, HR, Operations, and assign projects into them.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#spaceCreateModal">
        <i class="bi bi-plus-lg me-2"></i>Add space
      </button>
    </div>

    <div class="row g-3">
      <div v-for="space in spaces" :key="space.id" class="col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div class="avatar avatar-lg">
                <div class="avatar-content text-white" :style="{ backgroundColor: space.color }">
                  <i class="bi bi-grid-1x2-fill"></i>
                </div>
              </div>
              <span class="badge" :class="space.active ? 'badge-soft-success' : 'badge-soft-secondary'">{{ space.active ? 'Active' : 'Inactive' }}</span>
            </div>
            <h5 class="mb-1">{{ space.name }}</h5>
            <p class="text-muted-workhub mb-2">{{ space.department }}</p>
            <p class="small text-muted-workhub">{{ space.description || 'No description yet.' }}</p>
            <div class="d-flex justify-content-between small">
              <span><i class="bi bi-person me-1"></i>{{ space.owner?.name || 'No owner' }}</span>
              <span>{{ space.projects_count }} projects</span>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!spaces.length" class="col-12">
        <div class="empty-state">No spaces yet. Add the first department space.</div>
      </div>
    </div>

    <div id="spaceCreateModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" @submit.prevent="form.post('/spaces', { preserveScroll: true, onSuccess: () => form.reset() })">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Add department space</h5>
              <small class="text-muted">Spaces group projects and tasks by department.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Space name</label>
              <input v-model="form.name" class="form-control" placeholder="Short Term Projects">
              <div v-if="form.errors.name" class="invalid-feedback d-block">{{ form.errors.name }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Department</label>
              <input v-model="form.department" class="form-control" placeholder="SoftDev">
              <div v-if="form.errors.department" class="invalid-feedback d-block">{{ form.errors.department }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Owner</label>
              <select v-model="form.owner_id" class="form-select">
                <option value="">No owner yet</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Color</label>
              <input v-model="form.color" type="color" class="form-control form-control-color">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select v-model="form.active" class="form-select">
                <option :value="true">Active</option>
                <option :value="false">Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" rows="3" class="form-control" placeholder="What kind of projects belong in this space?"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Add space
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
