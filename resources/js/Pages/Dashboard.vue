<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WorkHubLayout from '../Layouts/WorkHubLayout.vue';
import { formatDate, formatDateTime } from '../lib/dates';

const props = defineProps<{
  metrics: Record<string, number>;
  allTasks: any[];
  myTasks: any[];
  createdTasks: any[];
  followedTasks: any[];
  requestInbox: { pendingApproval: any[]; submittedByMe: any[] };
  spaces: any[];
  requests: any[];
  projects: any[];
  todos: any[];
  activity: any[];
}>();

const page = usePage<any>();
const user = computed(() => page.props.auth?.user);
const activeTaskTab = ref<'mine' | 'created' | 'followed' | 'all'>('mine');
const activeTaskFilter = ref<'active' | 'overdue' | 'done' | 'dueSoon'>('active');

const metricCards = [
  { key: 'openRequests', label: 'Open Requests', hint: 'Waiting for action', href: '/requests', color: 'purple', icon: 'iconly-boldDocument', permission: 'requests.create' },
  { key: 'activeProjects', label: 'Active Projects', hint: 'Currently running', href: '/projects', color: 'blue', icon: 'iconly-boldWork', permission: 'projects.view' },
  { key: 'pendingTasks', label: 'Pending Tasks', hint: 'Todo, doing, review', href: '/projects', color: 'green', icon: 'iconly-boldTick-Square', permission: 'projects.view' },
  { key: 'workflowRuns', label: 'Workflow Runs', hint: 'Automation executions', href: '/workflows', color: 'red', icon: 'iconly-boldTime-Circle', permission: 'workflows.manage' },
];

function can(permission: string) {
  return (user.value?.permissions || []).includes(permission);
}

function isDone(task: any) {
  return ['done', 'cancelled'].includes(task.status);
}

function isOverdue(task: any) {
  return task.due_at && new Date(task.due_at) < new Date() && !isDone(task);
}

function isDueSoon(task: any) {
  if (!task.due_at || isDone(task)) return false;
  const due = new Date(task.due_at).getTime();
  const now = Date.now();
  return due >= now && due <= now + 7 * 24 * 60 * 60 * 1000;
}

const visibleMetricCards = computed(() => metricCards.filter((card) => can(card.permission)));
const taskSource = computed(() => ({
  mine: props.myTasks,
  created: props.createdTasks,
  followed: props.followedTasks,
  all: props.allTasks,
}[activeTaskTab.value] || []));
const filteredTasks = computed(() => taskSource.value.filter((task) => {
  if (activeTaskFilter.value === 'overdue') return isOverdue(task);
  if (activeTaskFilter.value === 'done') return isDone(task);
  if (activeTaskFilter.value === 'dueSoon') return isDueSoon(task);
  return !isDone(task);
}));
const quickActions = computed(() => [
  can('projects.create') ? { href: '/projects', label: 'New project', icon: 'bi-plus-square', class: 'btn-primary' } : null,
  can('requests.create') ? { href: '/requests', label: 'New request', icon: 'bi-inbox', class: 'btn-outline-primary' } : null,
  can('todos.manage') ? { href: '/todos', label: 'New todo', icon: 'bi-check2-square', class: 'btn-light-primary' } : null,
  can('projects.manage') ? { href: '/spaces', label: 'Add Space', icon: 'bi-grid-1x2', class: 'btn-light-secondary' } : null,
].filter(Boolean));
</script>

<template>
  <WorkHubLayout>
    <div class="dashboard-page">
    <section class="dashboard-hero card mb-4">
      <div class="card-body">
        <div class="row align-items-center g-4">
          <div class="col-xl-7">
            <span class="badge bg-light-primary text-primary mb-3">My Work</span>
            <h2 class="mb-2">Good day, {{ user?.name?.split(' ')[0] || 'there' }}</h2>
            <p class="text-muted-workhub mb-0">Your Rework-style command center: assignments, approvals, Spaces, projects, todos, and deadlines in one place.</p>
          </div>
          <div class="col-xl-5">
            <div class="dashboard-quick-actions">
              <Link v-for="action in quickActions" :key="action.href" :href="action.href" :class="['btn', action.class]">
                <i :class="['bi me-2', action.icon]"></i>{{ action.label }}
              </Link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="row g-3 mb-4">
      <div v-for="card in visibleMetricCards" :key="card.key" class="col-12 col-sm-6 col-xl-3">
        <Link :href="card.href" class="card dashboard-stat h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-3">
              <div :class="['stats-icon flex-shrink-0', card.color]">
                <i :class="card.icon"></i>
              </div>
              <i class="bi bi-arrow-up-right text-muted-workhub"></i>
            </div>
            <div class="mt-4">
              <h3 class="font-extrabold mb-1">{{ metrics[card.key] || 0 }}</h3>
              <h6 class="text-muted font-semibold mb-1">{{ card.label }}</h6>
              <small class="text-muted-workhub">{{ card.hint }}</small>
            </div>
          </div>
        </Link>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-xxl-8">
        <div class="card mb-4">
          <div class="card-header pb-0">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
              <div>
                <h4 class="mb-0">Work inbox</h4>
                <small class="text-muted-workhub">What I own, created, follow, and need to act on.</small>
              </div>
              <Link href="/projects" class="btn btn-sm btn-primary">Open boards</Link>
            </div>
            <ul class="nav nav-tabs workhub-scroll-tabs">
              <li v-for="tab in [
                ['mine', 'My tasks', myTasks.length],
                ['created', 'Created by me', createdTasks.length],
                ['followed', 'Followed', followedTasks.length],
                ['all', 'All visible', allTasks.length],
              ]" :key="tab[0]" class="nav-item">
                <button class="nav-link" :class="{ active: activeTaskTab === tab[0] }" type="button" @click="activeTaskTab = tab[0] as any">
                  {{ tab[1] }} <span class="badge bg-light-secondary text-secondary ms-1">{{ tab[2] }}</span>
                </button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button v-for="filter in [
                ['active', 'Active'],
                ['overdue', 'Overdue'],
                ['dueSoon', 'Due soon'],
                ['done', 'Done'],
              ]" :key="filter[0]" type="button" class="btn btn-sm" :class="activeTaskFilter === filter[0] ? 'btn-primary' : 'btn-light-secondary'" @click="activeTaskFilter = filter[0] as any">{{ filter[1] }}</button>
            </div>
            <div class="dashboard-list">
              <Link v-for="task in filteredTasks" :key="task.id" :href="task.project ? `/projects/${task.project.id}` : '/projects'" class="dashboard-list-item task-row">
                <div class="task-row-main min-w-0">
                  <strong class="text-truncate d-block">{{ task.title }}</strong>
                  <span class="text-muted-workhub small dashboard-subline">{{ task.project?.name || 'No project' }} · {{ task.assignee?.name || 'Unassigned' }}</span>
                </div>
                <div class="task-row-meta">
                  <span v-if="task.project?.space" class="space-pill" :style="{ backgroundColor: task.project.space.color }">{{ task.project.space.department }}</span>
                  <span class="badge badge-soft-primary text-capitalize">{{ task.status }}</span>
                  <span class="badge badge-soft-warning text-capitalize">{{ task.priority }}</span>
                  <span class="text-muted-workhub small"><i class="bi bi-calendar3 me-1"></i>{{ formatDateTime(task.due_at) }}</span>
                </div>
              </Link>
              <div v-if="!filteredTasks.length" class="empty-state py-4">
                <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success"></i>
                Nothing here for this filter.
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4 class="mb-0">Projects by Space</h4>
              <small class="text-muted-workhub">Department work areas shown in the sidenav too.</small>
            </div>
            <Link v-if="can('projects.manage')" href="/spaces" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Add Space</Link>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div v-for="space in spaces" :key="space.id" class="col-md-6">
                <Link :href="`/spaces/${space.id}`" class="space-summary-card">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-lg">
                      <div class="avatar-content text-white" :style="{ backgroundColor: space.color }"><i class="bi bi-pie-chart"></i></div>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                      <h6 class="mb-1 text-truncate">{{ space.department }} · {{ space.name }}</h6>
                      <p class="text-muted-workhub small mb-0 dashboard-subline">{{ space.projects_count }} projects · {{ space.tasks_count }} tasks</p>
                    </div>
                  </div>
                  <div class="space-summary-footer">
                    <span>{{ space.due_soon_count }} tasks due soon</span>
                    <i class="bi bi-chevron-right"></i>
                  </div>
                </Link>
              </div>
              <div v-if="!spaces.length" class="col-12">
                <div class="empty-state">No Spaces assigned yet.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xxl-4">
        <div class="card mb-4">
          <div class="card-header">
            <h4 class="mb-0">Approval inbox</h4>
            <small class="text-muted-workhub">Requests waiting for your decision.</small>
          </div>
          <div class="card-body">
            <div class="dashboard-list dashboard-list-compact">
              <Link v-for="request in requestInbox.pendingApproval" :key="request.id" :href="`/requests/${request.id}`" class="dashboard-list-item">
                <div class="min-w-0">
                  <strong class="text-truncate d-block">{{ request.reference }}</strong>
                  <span class="text-muted-workhub small dashboard-subline">{{ request.title }}</span>
                </div>
                <span class="badge badge-soft-warning text-capitalize">{{ request.status }}</span>
              </Link>
              <div v-if="!requestInbox.pendingApproval.length" class="empty-state py-4">No approvals waiting.</div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4 class="mb-0">Active projects</h4>
              <small class="text-muted-workhub">Progress and due dates</small>
            </div>
            <Link href="/projects" class="text-muted-workhub"><i class="bi bi-arrow-up-right"></i></Link>
          </div>
          <div class="card-body">
            <Link v-for="project in projects" :key="project.id" :href="`/projects/${project.id}`" class="project-progress-card">
              <div class="d-flex justify-content-between gap-3 mb-2">
                <strong class="text-truncate">{{ project.name }}</strong>
                <span>{{ project.progress }}%</span>
              </div>
              <div class="progress mb-2" style="height: 7px"><div class="progress-bar" :style="{ width: `${project.progress}%` }"></div></div>
              <small class="text-muted-workhub"><i class="bi bi-calendar3 me-1"></i>{{ formatDate(project.due_at) }}</small>
            </Link>
            <div v-if="!projects.length" class="empty-state py-4">No active projects.</div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header">
            <h4 class="mb-0">Submitted by me</h4>
            <small class="text-muted-workhub">Your latest requests and their stage.</small>
          </div>
          <div class="card-body">
            <div class="dashboard-list dashboard-list-compact">
              <Link v-for="request in requestInbox.submittedByMe" :key="request.id" :href="`/requests/${request.id}`" class="dashboard-list-item">
                <div class="min-w-0">
                  <strong class="text-truncate d-block">{{ request.reference }}</strong>
                  <span class="text-muted-workhub small dashboard-subline">{{ request.title }}</span>
                </div>
                <span class="badge badge-soft-primary text-capitalize flex-shrink-0">{{ request.status }}</span>
              </Link>
              <div v-if="!requestInbox.submittedByMe.length" class="empty-state py-4">No submitted requests yet.</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h4 class="mb-0">My todos</h4>
            <small class="text-muted-workhub">Personal reminders</small>
          </div>
          <div class="card-body">
            <div class="dashboard-list dashboard-list-compact">
              <Link v-for="todo in todos" :key="todo.id" href="/todos" class="dashboard-list-item">
                <div class="avatar avatar-md flex-shrink-0">
                  <div class="avatar-content bg-light-primary text-primary"><i class="bi bi-check2-square"></i></div>
                </div>
                <div class="min-w-0 flex-grow-1">
                  <strong class="d-block text-truncate">{{ todo.title }}</strong>
                  <span class="text-muted-workhub small dashboard-subline">{{ formatDateTime(todo.due_at) }}</span>
                </div>
                <span class="badge badge-soft-warning text-capitalize">{{ todo.priority }}</span>
              </Link>
              <div v-if="!todos.length" class="empty-state py-4">No todos due.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </WorkHubLayout>
</template>
