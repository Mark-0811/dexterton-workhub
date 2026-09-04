<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
const props = defineProps<{ connection: any; batches: any[]; csvTemplate: string }>();
const settings = useForm({ base_url: props.connection.base_url || '', database: props.connection.database || '', username: props.connection.username || '', api_key: '' });
const upload = useForm<{ file: File | null }>({ file: null });
</script>
<template>
  <WorkHubLayout>
    <div class="page-heading mb-4"><h1 class="page-title">Odoo Integration</h1><p class="text-muted-workhub mb-0">Bring employees, projects, tasks, and work assignments from Odoo by CSV or API.</p></div>
    <div class="row g-4">
      <div class="col-xl-6"><form class="card h-100" @submit.prevent="settings.post('/integrations/odoo')"><div class="card-header bg-transparent"><strong>API connection</strong></div><div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Odoo base URL</label><input v-model="settings.base_url" class="form-control" placeholder="https://odoo.dexterton.loc"></div>
        <div class="col-md-6"><label class="form-label">Database</label><input v-model="settings.database" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Username</label><input v-model="settings.username" class="form-control"></div>
        <div class="col-12"><label class="form-label">Password / API key</label><input v-model="settings.api_key" type="password" class="form-control" placeholder="Leave blank to keep saved key"></div>
        <div class="col-12"><span class="badge badge-soft-primary">Status: {{ connection.status }}</span><span v-if="connection.last_result?.message" class="ms-2 text-muted-workhub">{{ connection.last_result.message }}</span></div>
      </div><div class="card-footer d-flex gap-2 justify-content-end"><button class="btn btn-light">Save</button><button class="btn btn-outline-primary" type="button" @click="settings.post('/integrations/odoo/test')">Test</button><button class="btn btn-primary" type="button" @click="settings.post('/integrations/odoo/sync')">Sync now</button></div></form></div>
      <div class="col-xl-6"><form class="card h-100" @submit.prevent="upload.post('/integrations/odoo/imports')"><div class="card-header bg-transparent"><strong>CSV import</strong></div><div class="card-body">
        <label class="form-label">CSV file</label><input class="form-control" type="file" accept=".csv,text/csv" @input="upload.file = ($event.target as HTMLInputElement).files?.[0] || null"><div class="text-danger small">{{ upload.errors.file }}</div>
        <p class="text-muted-workhub mt-3 mb-2">Supported columns:</p><code class="d-block bg-light p-3 rounded small">{{ csvTemplate }}</code>
      </div><div class="card-footer text-end"><button class="btn btn-primary" :disabled="upload.processing">Upload CSV</button></div></form></div>
    </div>
    <div class="card mt-4"><div class="card-header bg-transparent"><strong>Recent imports and syncs</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Source</th><th>Status</th><th>Totals</th><th>Finished</th><th></th></tr></thead><tbody><tr v-for="batch in batches" :key="batch.id"><td>{{ batch.source }}</td><td><span class="badge badge-soft-primary">{{ batch.status }}</span></td><td>{{ batch.totals || '—' }}</td><td>{{ batch.finished_at || '—' }}</td><td class="text-end"><Link :href="`/integrations/odoo/imports/${batch.id}`" class="btn btn-sm btn-light">Open</Link></td></tr></tbody></table></div></div>
  </WorkHubLayout>
</template>
