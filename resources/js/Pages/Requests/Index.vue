<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime } from '../../lib/dates';

const props = defineProps<{ requests: any[]; inboxCounts: Record<string, number>; serviceFlows: any[]; spaces: any[] }>();
const page = usePage<any>();
const user = computed(() => page.props.auth?.user);
const activeTab = ref<'all' | 'drafts' | 'submittedByMe' | 'pendingApproval' | 'approved' | 'rejected'>('all');
const form = useForm({ title: '', description: '', service_flow_id: '', space_id: '', priority: 'normal' });

function isPendingForMe(request: any) {
  return ['submitted', 'in_review'].includes(request.status)
    && (request.approvals || []).some((step: any) => step.approver_id === user.value?.id && step.status === 'pending');
}

const filteredRequests = computed(() => props.requests.filter((request) => {
  if (activeTab.value === 'drafts') return request.status === 'draft' && request.requested_by === user.value?.id;
  if (activeTab.value === 'submittedByMe') return request.requested_by === user.value?.id;
  if (activeTab.value === 'pendingApproval') return isPendingForMe(request);
  if (activeTab.value === 'approved') return request.status === 'approved';
  if (activeTab.value === 'rejected') return request.status === 'rejected';
  return true;
}));

function createRequest() {
  form.post('/requests', {
    preserveScroll: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Request drafted', text: 'Open the request to submit it when ready.', timer: 1800, showConfirmButton: false }),
  });
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-start">
      <div>
        <h3>Requests & Approvals</h3>
        <p class="text-muted-workhub mb-0">Draft, submit, approve, and convert approved requests into project work.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal"><i class="bi bi-plus-lg me-2"></i>New Request</button>
    </div>

    <div class="card">
      <div class="card-header pb-0">
        <ul class="nav nav-tabs">
          <li v-for="tab in [
            ['all', 'All', inboxCounts.all],
            ['drafts', 'Drafts', inboxCounts.drafts],
            ['submittedByMe', 'Submitted by me', inboxCounts.submittedByMe],
            ['pendingApproval', 'Pending my approval', inboxCounts.pendingApproval],
            ['approved', 'Approved', inboxCounts.approved],
            ['rejected', 'Rejected', inboxCounts.rejected],
          ]" :key="tab[0]" class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab[0] }" @click="activeTab = tab[0] as any">
              {{ tab[1] }} <span class="badge bg-light-secondary text-secondary ms-1">{{ tab[2] || 0 }}</span>
            </button>
          </li>
        </ul>
      </div>
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead><tr><th>Reference</th><th>Request</th><th>Space</th><th>Flow / Stage</th><th>Priority</th><th>Submitted</th><th></th></tr></thead>
          <tbody>
            <tr v-for="request in filteredRequests" :key="request.id">
              <td><strong>{{ request.reference }}</strong></td>
              <td>
                <strong>{{ request.title }}</strong>
                <div class="small text-muted-workhub">By {{ request.requester?.name || 'Unknown' }}</div>
              </td>
              <td>
                <Link v-if="request.space" :href="`/spaces/${request.space.id}`" class="space-pill d-inline-block" :style="{ backgroundColor: request.space.color }">{{ request.space.department }} · {{ request.space.name }}</Link>
                <span v-else class="text-muted-workhub">No Space</span>
              </td>
              <td>
                <span :class="`badge badge-soft-${request.flow_stage?.color || 'primary'}`">{{ request.flow_stage?.label || request.status }}</span>
                <div class="small text-muted-workhub">{{ request.service_flow?.name || 'General request' }}</div>
              </td>
              <td><span class="badge badge-soft-secondary text-capitalize">{{ request.priority }}</span></td>
              <td class="text-muted-workhub small">{{ formatDateTime(request.submitted_at) }}</td>
              <td class="text-end"><Link class="btn btn-sm btn-light-primary" :href="`/requests/${request.id}`"><i class="bi bi-arrow-right me-1"></i>Open</Link></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!filteredRequests.length" class="card-body"><div class="empty-state">No requests in this inbox.</div></div>
    </div>

    <div id="requestModal" class="modal fade" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" @submit.prevent="createRequest">
          <div class="modal-header">
            <div>
              <h2 class="modal-title h5">Draft request</h2>
              <small class="text-muted">Choose the Space so the request routes to the right department.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Service flow</label>
              <select v-model="form.service_flow_id" class="form-select">
                <option value="">General request</option>
                <option v-for="flow in serviceFlows" :key="flow.id" :value="flow.id">{{ flow.name }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Request Space</label>
              <select v-model="form.space_id" class="form-select" :class="{ 'is-invalid': form.errors.space_id }">
                <option value="">Choose where this request belongs</option>
                <option v-for="space in spaces" :key="space.id" :value="space.id">{{ space.department }} - {{ space.name }}</option>
              </select>
              <div v-if="form.errors.space_id" class="invalid-feedback">{{ form.errors.space_id }}</div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Title</label>
              <input v-model="form.title" class="form-control" :class="{ 'is-invalid': form.errors.title }" placeholder="What do you need?">
              <div v-if="form.errors.title" class="invalid-feedback">{{ form.errors.title }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Priority</label>
              <select v-model="form.priority" class="form-select">
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" class="form-control" rows="4" placeholder="Add context, desired outcome, files needed, deadline, or approval reason."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Save draft
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
