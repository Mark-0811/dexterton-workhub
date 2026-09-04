<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime, toDateTimeLocal } from '../../lib/dates';

const props = defineProps<{ requestRecord: any; spaces: any[]; users: any[]; canCreateProject: boolean; canManageProjects: boolean; canApproveRequests: boolean }>();

const projectForm = useForm({
  name: props.requestRecord.title,
  description: props.requestRecord.description || '',
  owner_id: props.users[0]?.id || '',
  space_id: props.requestRecord.space_id || props.spaces[0]?.id || '',
  due_at: toDateTimeLocal(props.requestRecord.sla_due_at),
});
const taskForm = useForm({
  title: props.requestRecord.title,
  description: props.requestRecord.description || '',
  assignee_id: props.users[0]?.id || '',
  priority: props.requestRecord.priority || 'normal',
  status: 'todo',
  due_at: toDateTimeLocal(props.requestRecord.sla_due_at),
});

const canCreateProjectFromRequest = computed(() => props.canCreateProject && props.requestRecord.status === 'approved' && !props.requestRecord.project);
const canCreateTaskFromRequest = computed(() => props.canManageProjects && props.requestRecord.status === 'approved' && props.requestRecord.project);

const actionMap: Record<string, string[]> = {
  draft: ['submit', 'cancel'],
  submitted: ['approve', 'reject', 'cancel'],
  in_review: ['approve', 'reject', 'cancel'],
};

function can(action: string) {
  if (['approve', 'reject'].includes(action) && !props.canApproveRequests) return false;
  return (actionMap[props.requestRecord.status] || []).includes(action);
}

async function transition(id: string, action: string) {
  if (!can(action)) return;
  const labels: Record<string, string> = {
    submit: 'Submit this request?',
    approve: 'Approve this request?',
    reject: 'Reject this request?',
    cancel: 'Cancel this request?',
  };
  const result = await Swal.fire({
    icon: action === 'reject' || action === 'cancel' ? 'warning' : 'question',
    title: labels[action] || 'Update request?',
    text: 'This will update the request stage and write an audit trail entry.',
    showCancelButton: true,
    confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1),
  });
  if (result.isConfirmed) {
    router.post(`/requests/${id}/${action}`, {}, { preserveScroll: true });
  }
}

async function createProject() {
  if (!canCreateProjectFromRequest.value) return;
  const result = await Swal.fire({
    icon: 'question',
    title: 'Create project workspace?',
    text: 'The request will stay linked to the new project for tracking.',
    showCancelButton: true,
    confirmButtonText: 'Create project',
  });
  if (result.isConfirmed) {
    projectForm.post(`/requests/${props.requestRecord.id}/project`);
  }
}

async function createTask() {
  if (!canCreateTaskFromRequest.value) return;
  const result = await Swal.fire({
    icon: 'question',
    title: 'Create task from request?',
    text: 'This will add assigned work inside the linked project.',
    showCancelButton: true,
    confirmButtonText: 'Create task',
  });
  if (result.isConfirmed) {
    taskForm.post(`/requests/${props.requestRecord.id}/task`);
  }
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading">
      <div class="page-title">
        <div class="row">
          <div class="col-12 col-md-7 order-md-1 order-last">
            <h3>{{ requestRecord.reference }}</h3>
            <p class="text-subtitle text-muted">{{ requestRecord.title }}</p>
          </div>
          <div class="col-12 col-md-5 order-md-2 order-first text-md-end">
            <span :class="`badge badge-soft-${requestRecord.flow_stage?.color || 'primary'} text-capitalize`">{{ requestRecord.flow_stage?.label || requestRecord.status.replace('_', ' ') }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <div class="request-stage-tracker">
              <div v-for="step in requestRecord.stage_steps" :key="step.key" class="request-stage-step" :class="{ done: step.done }">
                <span><i class="bi" :class="step.done ? 'bi-check-lg' : 'bi-circle'"></i></span>
                <strong>{{ step.label }}</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4 class="mb-0">Request details</h4>
              <small class="text-muted-workhub">Submitted work request and approval state</small>
            </div>
            <span class="badge badge-soft-secondary text-capitalize">{{ requestRecord.priority }}</span>
          </div>
          <div class="card-body">
            <p>{{ requestRecord.description || 'No description was added.' }}</p>
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="request-fact-card">
                  <span>Space</span>
                  <strong>{{ requestRecord.space ? `${requestRecord.space.department} · ${requestRecord.space.name}` : 'No Space' }}</strong>
                </div>
              </div>
              <div class="col-md-4">
                <div class="request-fact-card">
                  <span>Flow</span>
                  <strong>{{ requestRecord.service_flow?.name || 'General request' }}</strong>
                </div>
              </div>
              <div class="col-md-4">
                <div class="request-fact-card">
                  <span>Submitted</span>
                  <strong>{{ formatDateTime(requestRecord.submitted_at) }}</strong>
                </div>
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="request-fact-card">
                  <span>Resolved</span>
                  <strong>{{ formatDateTime(requestRecord.resolved_at) }}</strong>
                </div>
              </div>
              <div class="col-md-4">
                <div class="request-fact-card">
                  <span>SLA due</span>
                  <strong>{{ formatDateTime(requestRecord.sla_due_at) }}</strong>
                </div>
              </div>
            </div>
            <div class="alert alert-light-primary mb-0">
              <strong>Next step:</strong>
              <span v-if="requestRecord.status === 'draft'">Submit this draft before it can be approved or rejected.</span>
              <span v-else-if="['submitted', 'in_review'].includes(requestRecord.status)">Approver can approve, reject, or cancel this request.</span>
              <span v-else-if="requestRecord.status === 'approved' && !requestRecord.project">Create a project from this approved request so the work can be assigned and tracked.</span>
              <span v-else>This request is already {{ requestRecord.status.replace('_', ' ') }}.</span>
            </div>
          </div>
          <div class="card-footer d-flex flex-wrap gap-2">
            <button v-if="can('submit')" class="btn btn-primary" @click="transition(requestRecord.id, 'submit')">
              <i class="bi bi-send me-2"></i>Submit request
            </button>
            <button v-if="can('approve')" class="btn btn-success" @click="transition(requestRecord.id, 'approve')">
              <i class="bi bi-check2-circle me-2"></i>Approve
            </button>
            <button v-if="can('reject')" class="btn btn-danger" @click="transition(requestRecord.id, 'reject')">
              <i class="bi bi-x-circle me-2"></i>Reject
            </button>
            <button v-if="can('cancel')" class="btn btn-light-danger" @click="transition(requestRecord.id, 'cancel')">
              <i class="bi bi-slash-circle me-2"></i>Cancel
            </button>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card" v-if="requestRecord.project">
          <div class="card-header"><h4>Linked project</h4></div>
          <div class="card-body">
            <span class="badge bg-light-primary text-primary mb-2">{{ requestRecord.project.code }}</span>
            <h5>{{ requestRecord.project.name }}</h5>
            <p class="text-muted-workhub small mb-3">This request has already been converted into a project workspace.</p>
            <Link :href="`/projects/${requestRecord.project.id}`" class="btn btn-primary w-100">
              <i class="bi bi-kanban me-2"></i>Open project
            </Link>
          </div>
        </div>

        <form v-else class="card" @submit.prevent="createProject">
          <div class="card-header">
            <h4>Create project from request</h4>
            <p class="text-muted-workhub small mb-0">Available after the request is approved.</p>
          </div>
          <div class="card-body">
            <div v-if="requestRecord.status !== 'approved'" class="empty-state py-4">
              Approve this request first. Then WorkHub can create a project with the same context.
            </div>
            <template v-else>
              <div class="mb-3">
                <label class="form-label">Project name</label>
                <input v-model="projectForm.name" class="form-control" :class="{ 'is-invalid': projectForm.errors.name }">
                <div v-if="projectForm.errors.name" class="invalid-feedback">{{ projectForm.errors.name }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Space</label>
                <select v-model="projectForm.space_id" class="form-select" :class="{ 'is-invalid': projectForm.errors.space_id }">
                  <option value="">No Space yet</option>
                  <option v-for="space in spaces" :key="space.id" :value="space.id">{{ space.department }} - {{ space.name }}</option>
                </select>
                <div v-if="projectForm.errors.space_id" class="invalid-feedback">{{ projectForm.errors.space_id }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Project owner</label>
                <select v-model="projectForm.owner_id" class="form-select">
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
                <small class="text-muted-workhub">Normal users become the owner of projects they create.</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Due date and time</label>
                <input v-model="projectForm.due_at" type="datetime-local" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Project brief</label>
                <textarea v-model="projectForm.description" class="form-control" rows="3"></textarea>
              </div>
              <button class="btn btn-primary w-100" :disabled="projectForm.processing || !canCreateProjectFromRequest">
                <span v-if="projectForm.processing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-kanban me-2"></i>Create project workspace
              </button>
            </template>
          </div>
        </form>

        <div class="card">
          <div class="card-header"><h4>Approval route</h4></div>
          <ul class="list-group list-group-flush">
            <li v-for="step in requestRecord.approvals" :key="step.id" class="list-group-item d-flex justify-content-between">
              <span>Step {{ step.sequence }} <small class="text-muted-workhub">{{ step.approver?.name || 'No approver' }}</small></span><span class="badge badge-soft-warning text-capitalize">{{ step.status }}</span>
            </li>
          </ul>
        </div>

        <form v-if="canCreateTaskFromRequest" class="card" @submit.prevent="createTask">
          <div class="card-header">
            <h4>Create task from request</h4>
            <p class="text-muted-workhub small mb-0">Assign specific work inside {{ requestRecord.project.name }}.</p>
          </div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label">Task title</label>
              <input v-model="taskForm.title" class="form-control" :class="{ 'is-invalid': taskForm.errors.title }">
              <div v-if="taskForm.errors.title" class="invalid-feedback">{{ taskForm.errors.title }}</div>
            </div>
            <div class="col-12">
              <label class="form-label">Assignee</label>
              <select v-model="taskForm.assignee_id" class="form-select">
                <option value="">Unassigned</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Priority</label>
              <select v-model="taskForm.priority" class="form-select">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Due date and time</label>
              <input v-model="taskForm.due_at" type="datetime-local" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Instructions</label>
              <textarea v-model="taskForm.description" rows="3" class="form-control"></textarea>
            </div>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary w-100" :disabled="taskForm.processing">
              <span v-if="taskForm.processing" class="spinner-border spinner-border-sm me-2"></span>
              Create assigned task
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
