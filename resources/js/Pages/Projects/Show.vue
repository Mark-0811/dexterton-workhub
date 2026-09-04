<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDate, formatDateTime, toDateTimeLocal } from '../../lib/dates';
const props = defineProps<{ project: any; users: any[]; spaces: any[]; taskStatusColors: Record<string, string>; canManageProjects: boolean; relatedRequests: any[] }>();
const lanes = [
  { key: 'todo', title: 'To do', icon: 'bi-list-check' },
  { key: 'doing', title: 'Doing', icon: 'bi-lightning-charge' },
  { key: 'review', title: 'Review', icon: 'bi-eye' },
  { key: 'done', title: 'Done', icon: 'bi-check2-circle' },
];
const projectForm = useForm({
  name: props.project.name,
  description: props.project.description || '',
  owner_id: props.project.owner_id,
  space_id: props.project.space_id || '',
  status: props.project.status,
  progress: props.project.progress,
  due_at: toDateTimeLocal(props.project.due_at),
});
const taskForm = useForm({ title: '', description: '', assignee_id: '', priority: 'normal', status: 'todo', due_at: '' });
const taskForms: Record<string, any> = {};
const showProjectSetup = ref(false);
const showAssignWork = ref(false);
const draggingTaskId = ref<string | null>(null);
const selectedTask = ref<any | null>(null);
const activeView = ref<'dashboard' | 'board' | 'list' | 'calendar' | 'requests'>('board');
const openTaskCount = computed(() => props.project.tasks.filter((task: any) => !['done', 'cancelled'].includes(task.status)).length);
const overdueTaskCount = computed(() => props.project.tasks.filter((task: any) => task.due_at && new Date(task.due_at) < new Date() && !['done', 'cancelled'].includes(task.status)).length);
const completedTaskCount = computed(() => props.project.tasks.filter((task: any) => task.status === 'done').length);

props.project.tasks.forEach((task: any) => {
  taskForms[task.id] = useForm({
    assignee_id: task.assignee_id || '',
    priority: task.priority,
    status: task.status,
    due_at: toDateTimeLocal(task.due_at),
  });
});

async function createTask() {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Assign this work?',
    text: 'The task will appear on this project board and in the assignee dashboard.',
    showCancelButton: true,
    confirmButtonText: 'Assign task',
  });
  if (!result.isConfirmed) return;

  taskForm.post(`/projects/${props.project.id}/tasks`, {
    preserveScroll: true,
    onSuccess: () => {
      taskForm.reset();
      showAssignWork.value = false;
    },
  });
}

function saveTask(task: any) {
  taskForms[task.id].patch(`/projects/${props.project.id}/tasks/${task.id}`, {
    preserveScroll: true,
  });
}

function openTaskEditor(task: any) {
  selectedTask.value = task;
}

function dropTask(status: string) {
  if (!props.canManageProjects) return;
  if (!draggingTaskId.value) return;

  const task = props.project.tasks.find((item: any) => item.id === draggingTaskId.value);
  draggingTaskId.value = null;

  if (!task || task.status === status) return;

  taskForms[task.id].status = status;
  task.status = status;
  saveTask(task);
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-start">
      <div>
        <h3>{{ project.name }}</h3>
        <p class="text-muted-workhub mb-0">
          <span class="badge bg-light-primary text-primary me-2">{{ project.code }}</span>
          <span v-if="project.space" class="badge text-white me-2" :style="{ backgroundColor: project.space.color }">{{ project.space.department }} - {{ project.space.name }}</span>
          {{ project.description || 'No description yet.' }}
        </p>
      </div>
      <div v-if="canManageProjects" class="d-flex flex-wrap gap-2 justify-content-end">
        <button class="btn btn-outline-primary" @click="showProjectSetup = !showProjectSetup">
          <i class="bi bi-pencil-square me-2"></i>{{ showProjectSetup ? 'Close edit' : 'Edit project' }}
        </button>
        <button class="btn btn-primary" @click="showAssignWork = !showAssignWork">
          <i class="bi bi-person-plus me-2"></i>{{ showAssignWork ? 'Hide assign work' : 'Assign work' }}
        </button>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="card"><div class="card-body px-4 py-4-5"><h6 class="text-muted font-semibold">Owner</h6><h6 class="font-extrabold mb-0">{{ project.owner?.name || 'No owner' }}</h6></div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card"><div class="card-body px-4 py-4-5"><h6 class="text-muted font-semibold">Status</h6><h6 class="font-extrabold mb-0 text-capitalize">{{ project.status.replace('_', ' ') }}</h6></div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card"><div class="card-body px-4 py-4-5"><h6 class="text-muted font-semibold">Progress</h6><h6 class="font-extrabold mb-0">{{ project.progress }}%</h6></div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card"><div class="card-body px-4 py-4-5"><h6 class="text-muted font-semibold">Due date</h6><h6 class="font-extrabold mb-0">{{ formatDate(project.due_at) }}</h6></div></div>
      </div>
    </div>

    <div v-if="!canManageProjects" class="alert alert-light-primary d-flex align-items-start gap-3">
      <i class="bi bi-eye fs-4"></i>
      <div>
        <strong>View-only project board</strong>
        <div>You can select and review this project. Project setup, task assignment, and drag-and-drop stage changes are available to managers and admins.</div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header pb-0">
        <ul class="nav nav-tabs">
          <li v-for="view in [
            ['dashboard', 'Dashboard', 'bi-speedometer2'],
            ['board', 'Board', 'bi-kanban'],
            ['list', 'List', 'bi-list-ul'],
            ['calendar', 'Calendar', 'bi-calendar3'],
            ['requests', 'Requests', 'bi-inbox'],
          ]" :key="view[0]" class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeView === view[0] }" @click="activeView = view[0] as any">
              <i :class="`bi ${view[2]} me-1`"></i>{{ view[1] }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="canManageProjects && (showProjectSetup || showAssignWork)" class="row g-4 mb-4">
      <div v-if="showProjectSetup" class="col-xl-4">
        <form v-if="showProjectSetup" class="card h-100" @submit.prevent="projectForm.patch(`/projects/${project.id}`, { preserveScroll: true, onSuccess: () => showProjectSetup = false })">
          <div class="card-header">
            <h4>Edit project</h4>
            <p class="text-muted mb-0 small">Edit inside this project workspace only.</p>
          </div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label">Project name</label>
              <input v-model="projectForm.name" class="form-control">
              <div v-if="projectForm.errors.name" class="invalid-feedback d-block">{{ projectForm.errors.name }}</div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea v-model="projectForm.description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Project owner</label>
              <select v-model="projectForm.owner_id" class="form-select">
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
              <div v-if="projectForm.errors.owner_id" class="invalid-feedback d-block">{{ projectForm.errors.owner_id }}</div>
            </div>
            <div class="col-12">
              <label class="form-label">Space / Department</label>
              <select v-model="projectForm.space_id" class="form-select">
                <option value="">No space yet</option>
                <option v-for="space in spaces" :key="space.id" :value="space.id">{{ space.department }} - {{ space.name }}</option>
              </select>
              <div v-if="projectForm.errors.space_id" class="invalid-feedback d-block">{{ projectForm.errors.space_id }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select v-model="projectForm.status" class="form-select">
                <option value="active">Active</option>
                <option value="on_hold">On hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Progress</label>
              <input v-model="projectForm.progress" type="number" min="0" max="100" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Due date</label>
              <input v-model="projectForm.due_at" type="datetime-local" class="form-control">
            </div>
          </div>
          <div class="card-footer text-end">
            <button class="btn btn-primary" :disabled="projectForm.processing">
              <span v-if="projectForm.processing" class="spinner-border spinner-border-sm me-2"></span>
              Save project
            </button>
          </div>
        </form>
      </div>

      <div v-if="showAssignWork" class="col-xl-8">
        <form v-if="showAssignWork" class="card h-100" @submit.prevent="createTask">
          <div class="card-header">
            <h4>Assign work</h4>
          </div>
          <div class="card-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Task title</label>
              <input v-model="taskForm.title" class="form-control" placeholder="e.g. Prepare site measurement">
              <div v-if="taskForm.errors.title" class="invalid-feedback d-block">{{ taskForm.errors.title }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assignee</label>
              <select v-model="taskForm.assignee_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Priority</label>
              <select v-model="taskForm.priority" class="form-select">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select v-model="taskForm.status" class="form-select">
                <option value="todo">To do</option>
                <option value="doing">Doing</option>
                <option value="review">Review</option>
                <option value="done">Done</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Due date</label>
              <input v-model="taskForm.due_at" type="datetime-local" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Instructions / notes</label>
              <textarea v-model="taskForm.description" class="form-control" rows="3" placeholder="What should the assignee do?"></textarea>
            </div>
          </div>
          <div class="card-footer text-end">
            <button class="btn btn-primary" :disabled="taskForm.processing">
              <span v-if="taskForm.processing" class="spinner-border spinner-border-sm me-2"></span>
              Assign task
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="activeView === 'dashboard'" class="row g-4">
      <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">Open work</h6><h3>{{ openTaskCount }}</h3><p class="text-muted-workhub mb-0">Tasks not done or cancelled</p></div></div>
      </div>
      <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">Overdue</h6><h3>{{ overdueTaskCount }}</h3><p class="text-muted-workhub mb-0">Needs deadline attention</p></div></div>
      </div>
      <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">Completed</h6><h3>{{ completedTaskCount }}</h3><p class="text-muted-workhub mb-0">Finished work</p></div></div>
      </div>
      <div class="col-12">
        <div class="card">
          <div class="card-header"><h4>Project activity summary</h4></div>
          <div class="card-body">
            <p class="text-muted-workhub mb-0">This project has {{ project.tasks.length }} tasks, {{ relatedRequests.length }} linked requests, and is {{ project.progress }}% complete.</p>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="activeView === 'board'" class="row g-3">
      <div v-for="lane in lanes" :key="lane" class="col-lg-3">
        <div
          class="kanban-column drop-zone"
          :class="{ 'drop-zone-active': draggingTaskId }"
          :style="{ backgroundColor: taskStatusColors[lane.key] || undefined }"
          @dragover.prevent
          @drop.prevent="dropTask(lane.key)"
        >
          <div class="d-flex align-items-center justify-content-between mb-3">
            <strong><i :class="['bi me-2', lane.icon]"></i>{{ lane.title }}</strong>
            <span class="badge badge-soft-secondary">{{ props.project.tasks.filter((t: any) => t.status === lane.key).length }}</span>
          </div>
            <div
            v-for="task in props.project.tasks.filter((t: any) => t.status === lane.key)"
            :key="task.id"
            class="kanban-card"
            :draggable="canManageProjects"
            @dragstart="draggingTaskId = task.id"
            @dragend="draggingTaskId = null"
          >
            <div class="d-flex justify-content-between gap-2">
              <strong>{{ task.title }}</strong>
              <span class="badge badge-soft-warning text-capitalize">{{ task.priority }}</span>
            </div>
            <p class="text-muted-workhub small mb-2">{{ task.description }}</p>
            <div class="small text-muted-workhub mb-3">
              <div><i class="bi bi-person me-1"></i>{{ task.assignee?.name || 'Unassigned' }}</div>
              <div><i class="bi bi-calendar3 me-1"></i>{{ formatDateTime(task.due_at) }}</div>
            </div>
            <div v-if="canManageProjects" class="d-grid">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskEditModal" @click="openTaskEditor(task)">
                <i class="bi bi-pencil-square me-1"></i>Edit
              </button>
            </div>
          </div>
          <div v-if="!props.project.tasks.filter((t: any) => t.status === lane.key).length" class="text-muted-workhub small">No {{ lane.title.toLowerCase() }} work.</div>
        </div>
      </div>
    </div>

    <div v-else-if="activeView === 'list'" class="card">
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead><tr><th>Task</th><th>Assignee</th><th>Status</th><th>Priority</th><th>Due</th><th></th></tr></thead>
          <tbody>
            <tr v-for="task in project.tasks" :key="task.id">
              <td><strong>{{ task.title }}</strong><div class="small text-muted-workhub">{{ task.description || 'No notes' }}</div></td>
              <td>{{ task.assignee?.name || 'Unassigned' }}</td>
              <td><span class="badge badge-soft-primary text-capitalize">{{ task.status }}</span></td>
              <td><span class="badge badge-soft-warning text-capitalize">{{ task.priority }}</span></td>
              <td>{{ formatDateTime(task.due_at) }}</td>
              <td class="text-end">
                <button v-if="canManageProjects" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskEditModal" @click="openTaskEditor(task)">Edit</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!project.tasks.length" class="card-body"><div class="empty-state">No tasks yet.</div></div>
    </div>

    <div v-else-if="activeView === 'calendar'" class="card">
      <div class="card-header"><h4>Calendar view</h4><small class="text-muted-workhub">Tasks grouped by due date.</small></div>
      <div class="card-body">
        <div class="row g-3">
          <div v-for="task in project.tasks.filter((item: any) => item.due_at)" :key="task.id" class="col-md-6 col-xl-4">
            <div class="calendar-task-card">
              <strong>{{ formatDateTime(task.due_at) }}</strong>
              <h6 class="mb-1 mt-2">{{ task.title }}</h6>
              <p class="text-muted-workhub small mb-0">{{ task.assignee?.name || 'Unassigned' }} · {{ task.status }}</p>
            </div>
          </div>
          <div v-if="!project.tasks.filter((item: any) => item.due_at).length" class="empty-state">No dated tasks yet.</div>
        </div>
      </div>
    </div>

    <div v-else class="card">
      <div class="card-header"><h4>Linked requests</h4><small class="text-muted-workhub">Requests converted into this project.</small></div>
      <div class="card-body">
        <div class="dashboard-list">
          <div v-for="request in relatedRequests" :key="request.id" class="dashboard-list-item">
            <div class="min-w-0">
              <strong>{{ request.reference }}</strong>
              <div class="text-muted-workhub small">{{ request.title }} · {{ request.requester?.name || 'Unknown requester' }}</div>
            </div>
            <span class="badge badge-soft-success text-capitalize">{{ request.status }}</span>
          </div>
          <div v-if="!relatedRequests.length" class="empty-state">No linked requests yet.</div>
        </div>
      </div>
    </div>

    <div id="taskEditModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <form v-if="selectedTask" class="modal-content" @submit.prevent="saveTask(selectedTask)">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Edit task</h5>
              <small class="text-muted">{{ selectedTask.title }}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body row g-3">
            <div class="col-12">
              <label class="form-label">Assign to</label>
              <select v-model="taskForms[selectedTask.id].assignee_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select v-model="taskForms[selectedTask.id].status" class="form-select">
                <option value="todo">To do</option>
                <option value="doing">Doing</option>
                <option value="review">Review</option>
                <option value="done">Done</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Priority</label>
              <select v-model="taskForms[selectedTask.id].priority" class="form-select">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Due date</label>
              <input v-model="taskForms[selectedTask.id].due_at" type="datetime-local" class="form-control">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="taskForms[selectedTask.id].processing">
              <span v-if="taskForms[selectedTask.id].processing" class="spinner-border spinner-border-sm me-2"></span>
              Save task
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
