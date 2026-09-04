<script setup lang="ts">
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
defineProps<{ batch: any }>();
</script>
<template>
  <WorkHubLayout>
    <div class="page-heading mb-4"><h1 class="page-title">Odoo Import Report</h1><p class="text-muted-workhub mb-0">{{ batch.source }} · {{ batch.status }}</p></div>
    <div class="row g-3 mb-4"><div v-for="(value, key) in batch.totals || {}" :key="key" class="col-md-3"><div class="card"><div class="card-body"><div class="h3 mb-0">{{ value }}</div><div class="text-muted-workhub text-capitalize">{{ key }}</div></div></div></div></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Row</th><th>Type</th><th>External ID</th><th>Status</th><th>Errors</th></tr></thead><tbody><tr v-for="row in batch.rows" :key="row.id"><td>{{ row.row_number }}</td><td>{{ row.entity_type }}</td><td>{{ row.external_id }}</td><td><span class="badge" :class="row.status === 'failed' ? 'badge-soft-danger' : 'badge-soft-success'">{{ row.status }}</span></td><td><code>{{ row.errors?.message || '—' }}</code></td></tr></tbody></table></div></div>
  </WorkHubLayout>
</template>
