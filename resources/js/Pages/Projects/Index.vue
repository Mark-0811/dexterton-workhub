<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDate } from '../../lib/dates';
const props = defineProps<{ projects: any[]; users: any[]; spaces: any[]; nextProjectCode: string; canCreateProjects: boolean; canManageProjects: boolean; activeSpaceId?: string }>();
const form = useForm({ code: '', name: '', description: '', owner_id: '', space_id: '', status: 'active', due_at: '' });
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading">
      <div class="page-title">
        <div class="row">
          <div class="col-12 col-md-6 order-md-1 order-last">
            <h3>Projects</h3>
            <p class="text-subtitle text-muted">Create projects here. Assignment happens inside each project workspace.</p>
          </div>
          <div class="col-12 col-md-6 order-md-2 order-first">
            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><Link href="/dashboard">Dashboard</Link></li>
                <li class="breadcrumb-item active">Projects</li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <section class="section">
      <div v-if="!canManageProjects" class="alert alert-light-primary d-flex align-items-start gap-3">
        <i class="bi bi-info-circle fs-4"></i>
        <div>
          <strong>Project user access</strong>
          <div>You can create a project, choose its Space/department, and open existing boards. A manager can assign work, change owners, and move task cards.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <div>
            <h4 class="card-title mb-0">Project list</h4>
            <p class="text-muted mb-0 small">
              <span v-if="activeSpaceId">Showing selected Space only · </span>
              Next project code: <strong>{{ nextProjectCode }}</strong>
            </p>
          </div>
          <div class="d-flex gap-2">
            <Link v-if="activeSpaceId" href="/projects" class="btn btn-light-secondary">
              <i class="bi bi-x-lg me-1"></i>Clear filter
            </Link>
            <button v-if="canCreateProjects" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectCreateModal">
              <i class="bi bi-plus-lg me-2"></i>Create project
            </button>
          </div>
        </div>
      </div>

    <div class="row g-3">
      <div v-for="project in projects" :key="project.id" class="col-md-6 col-xl-4">
        <div class="card h-100 text-body project-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <span class="badge bg-light-primary text-primary">{{ project.code }}</span>
              <span class="badge badge-soft-success text-capitalize">{{ project.status.replace('_', ' ') }}</span>
            </div>
            <h5>{{ project.name }}</h5>
            <p class="text-muted-workhub">{{ project.description || 'No description yet.' }}</p>
            <div class="d-flex justify-content-between small mb-2">
              <span><i class="bi bi-person me-1"></i>{{ project.owner?.name || 'No owner' }}</span>
              <span>{{ project.tasks_count }} tasks</span>
            </div>
            <div class="small text-muted-workhub mb-2"><i class="bi bi-calendar3 me-1"></i>{{ formatDate(project.due_at) }}</div>
            <div class="small mb-2" v-if="project.space">
              <span class="badge text-white" :style="{ backgroundColor: project.space.color }">
                {{ project.space.department }} - {{ project.space.name }}
              </span>
            </div>
            <div class="progress mb-2" style="height: 7px"><div class="progress-bar" :style="{ width: `${project.progress}%` }"></div></div>
            <div class="d-flex gap-2 mt-3">
              <Link :href="`/projects/${project.id}`" class="btn btn-sm btn-primary">
                <i class="bi bi-kanban me-1"></i>Open board
              </Link>
              <Link v-if="canManageProjects" :href="`/projects/${project.id}`" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square me-1"></i>Edit inside
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-if="!projects.length" class="empty-state">No projects yet. Create the first project above.</div>
    </section>

    <div id="projectCreateModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" @submit.prevent="form.post('/projects')">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Create project</h5>
              <small class="text-muted">Code will auto-generate as {{ nextProjectCode }} unless you override it.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body row g-3">
            <div class="col-md-4">
              <label class="form-label">Project code</label>
              <input v-model="form.code" class="form-control" :placeholder="nextProjectCode">
              <small class="text-muted">Optional</small>
              <div v-if="form.errors.code" class="invalid-feedback d-block">{{ form.errors.code }}</div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Project name</label>
              <input v-model="form.name" class="form-control" placeholder="e.g. BGC showroom renovation">
              <div v-if="form.errors.name" class="invalid-feedback d-block">{{ form.errors.name }}</div>
            </div>
            <div v-if="canManageProjects" class="col-md-6">
              <label class="form-label">Project owner</label>
              <select v-model="form.owner_id" class="form-select">
                <option value="">Choose owner</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
              <div v-if="form.errors.owner_id" class="invalid-feedback d-block">{{ form.errors.owner_id }}</div>
            </div>
            <div v-else class="col-md-6">
              <label class="form-label">Project owner</label>
              <div class="form-control bg-light">{{ users[0]?.name || 'You' }}</div>
              <small class="text-muted">New projects you create are assigned to you first. A manager can reassign later.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Space / Department</label>
              <select v-model="form.space_id" class="form-select">
                <option value="">No space yet</option>
                <option v-for="space in spaces" :key="space.id" :value="space.id">{{ space.department }} - {{ space.name }}</option>
              </select>
              <div v-if="form.errors.space_id" class="invalid-feedback d-block">{{ form.errors.space_id }}</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select v-model="form.status" class="form-select">
                <option value="active">Active</option>
                <option value="on_hold">On hold</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Due date</label>
              <input v-model="form.due_at" type="datetime-local" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" class="form-control" rows="4" placeholder="Short project summary"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Create project
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
