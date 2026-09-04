<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDate, formatDateTime } from '../../lib/dates';

const props = defineProps<{ space: any; metrics: Record<string, number>; projects: any[]; tasks: any[]; requests: any[]; members: any[] }>();
const activeTab = ref<'overview' | 'projects' | 'tasks' | 'requests' | 'members'>('overview');
const openTasks = computed(() => props.tasks.filter((task) => !['done', 'cancelled'].includes(task.status)));
</script>

<template>
  <WorkHubLayout>
    <div class="space-hero card mb-4" :style="{ '--space-color': space.color }">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <span class="badge text-white mb-3" :style="{ backgroundColor: space.color }">{{ space.department }}</span>
            <h2 class="mb-1">{{ space.name }}</h2>
            <p class="text-muted-workhub mb-0">{{ space.description || 'Department workspace for projects, tasks, requests, and reports.' }}</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <Link :href="`/projects?space=${space.id}`" class="btn btn-primary"><i class="bi bi-kanban me-2"></i>Open projects</Link>
            <Link href="/requests" class="btn btn-outline-primary"><i class="bi bi-inbox me-2"></i>New request</Link>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div v-for="metric in [
        ['Projects', metrics.projects, 'bi-kanban', 'primary'],
        ['Active projects', metrics.activeProjects, 'bi-briefcase', 'success'],
        ['Open tasks', metrics.openTasks, 'bi-check2-square', 'warning'],
        ['Open requests', metrics.openRequests, 'bi-inbox', 'info'],
      ]" :key="metric[0]" class="col-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div :class="`avatar avatar-lg bg-light-${metric[3]} text-${metric[3]}`"><div class="avatar-content"><i :class="`bi ${metric[2]}`"></i></div></div>
            <div><h4 class="mb-0">{{ metric[1] }}</h4><small class="text-muted-workhub">{{ metric[0] }}</small></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header pb-0">
        <ul class="nav nav-tabs">
          <li v-for="tab in ['overview', 'projects', 'tasks', 'requests', 'members']" :key="tab" class="nav-item">
            <button class="nav-link text-capitalize" :class="{ active: activeTab === tab }" type="button" @click="activeTab = tab as any">{{ tab }}</button>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div v-if="activeTab === 'overview'" class="row g-4">
          <div class="col-xl-7">
            <h4>Work needing attention</h4>
            <div class="dashboard-list">
              <Link v-for="task in openTasks.slice(0, 8)" :key="task.id" :href="`/projects/${task.project_id}`" class="dashboard-list-item">
                <div class="min-w-0">
                  <strong class="d-block text-truncate">{{ task.title }}</strong>
                  <small class="text-muted-workhub">{{ task.project?.name }} · {{ task.assignee?.name || 'Unassigned' }}</small>
                </div>
                <span class="badge badge-soft-warning text-capitalize">{{ task.priority }}</span>
              </Link>
              <div v-if="!openTasks.length" class="empty-state">No open tasks in this Space.</div>
            </div>
          </div>
          <div class="col-xl-5">
            <h4>Recent requests</h4>
            <div class="dashboard-list">
              <Link v-for="request in requests.slice(0, 6)" :key="request.id" :href="`/requests/${request.id}`" class="dashboard-list-item">
                <div class="min-w-0">
                  <strong class="d-block">{{ request.reference }}</strong>
                  <small class="text-muted-workhub">{{ request.title }}</small>
                </div>
                <span class="badge badge-soft-primary text-capitalize">{{ request.status }}</span>
              </Link>
              <div v-if="!requests.length" class="empty-state">No requests yet.</div>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'projects'" class="row g-3">
          <div v-for="project in projects" :key="project.id" class="col-md-6 col-xl-4">
            <Link :href="`/projects/${project.id}`" class="card project-card h-100 text-body">
              <div class="card-body">
                <span class="badge bg-light-primary text-primary mb-2">{{ project.code }}</span>
                <h5>{{ project.name }}</h5>
                <p class="text-muted-workhub small">{{ project.tasks_count }} tasks · due {{ formatDate(project.due_at) }}</p>
                <div class="progress" style="height: 7px"><div class="progress-bar" :style="{ width: `${project.progress}%` }"></div></div>
              </div>
            </Link>
          </div>
          <div v-if="!projects.length" class="empty-state">No projects in this Space.</div>
        </div>

        <div v-else-if="activeTab === 'tasks'" class="dashboard-list">
          <Link v-for="task in tasks" :key="task.id" :href="`/projects/${task.project_id}`" class="dashboard-list-item">
            <div class="min-w-0">
              <strong>{{ task.title }}</strong>
              <div class="text-muted-workhub small">{{ task.project?.name }} · {{ task.assignee?.name || 'Unassigned' }} · {{ formatDateTime(task.due_at) }}</div>
            </div>
            <span class="badge badge-soft-secondary text-capitalize">{{ task.status }}</span>
          </Link>
          <div v-if="!tasks.length" class="empty-state">No tasks yet.</div>
        </div>

        <div v-else-if="activeTab === 'requests'" class="dashboard-list">
          <Link v-for="request in requests" :key="request.id" :href="`/requests/${request.id}`" class="dashboard-list-item">
            <div class="min-w-0">
              <strong>{{ request.reference }}</strong>
              <div class="text-muted-workhub small">{{ request.title }} · {{ request.requester?.name || 'Unknown requester' }}</div>
            </div>
            <span class="badge badge-soft-primary text-capitalize">{{ request.status }}</span>
          </Link>
          <div v-if="!requests.length" class="empty-state">No requests yet.</div>
        </div>

        <div v-else class="row g-3">
          <div v-for="member in members" :key="member.id" class="col-md-6 col-xl-4">
            <div class="border rounded-4 p-3 h-100">
              <h6 class="mb-1">{{ member.name }}</h6>
              <p class="text-muted-workhub small mb-0">{{ member.title || member.email }}</p>
            </div>
          </div>
          <div v-if="!members.length" class="empty-state">No members assigned yet.</div>
        </div>
      </div>
    </div>
  </WorkHubLayout>
</template>
