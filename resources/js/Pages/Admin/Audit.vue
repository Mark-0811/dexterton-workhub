<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime, toDateTimeLocal } from '../../lib/dates';
const props = defineProps<{ events: any[]; users: any[]; filters: any; modules: string[] }>();
const form = useForm({ actor_id: props.filters.actor_id || '', module: props.filters.module || '', event: props.filters.event || '', from: toDateTimeLocal(props.filters.from), to: toDateTimeLocal(props.filters.to) });
function applyFilters() { router.get('/admin/audit', form.data(), { preserveState: true }); }
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading mb-4"><h1 class="page-title">Audit Trail</h1><p class="text-muted-workhub mb-0">Plain-English history of privileged and business actions.</p></div>
    <form class="card mb-4" @submit.prevent="applyFilters"><div class="card-body row g-3">
      <div class="col-md-2"><label class="form-label">Actor</label><select v-model="form.actor_id" class="form-select"><option value="">All</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
      <div class="col-md-2"><label class="form-label">Module</label><select v-model="form.module" class="form-select"><option value="">All</option><option v-for="module in modules" :key="module">{{ module }}</option></select></div>
      <div class="col-md-3"><label class="form-label">Event</label><input v-model="form.event" class="form-control" placeholder="request.submit"></div>
      <div class="col-md-2"><label class="form-label">From</label><input v-model="form.from" type="datetime-local" class="form-control"></div>
      <div class="col-md-2"><label class="form-label">To</label><input v-model="form.to" type="datetime-local" class="form-control"></div>
      <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Go</button></div>
    </div></form>
    <div class="card"><div class="card-body">
      <div v-if="!events.length" class="empty-state">No audit events match these filters.</div>
      <div v-for="event in events" :key="event.id" class="border-bottom py-3">
        <div class="d-flex justify-content-between gap-3"><div><div class="audit-message">{{ event.message }}</div><div class="text-muted-workhub small">{{ event.module }} · {{ event.event }} · {{ event.ip_address || 'No IP captured' }}</div></div><span class="text-muted-workhub small">{{ formatDateTime(event.created_at) }}</span></div>
        <div v-if="event.changed?.length" class="mt-2 small"><span v-for="change in event.changed" :key="change.field" class="badge badge-soft-secondary me-2">{{ change.field }}: {{ change.from ?? 'blank' }} → {{ change.to ?? 'blank' }}</span></div>
        <details class="mt-2"><summary class="small text-muted-workhub">View technical details</summary><pre class="technical-details bg-light rounded p-3 mt-2">{{ event.technical }}</pre></details>
      </div>
    </div></div>
  </WorkHubLayout>
</template>
