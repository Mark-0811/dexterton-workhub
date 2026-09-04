<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
defineProps<{ workflows: any[]; runs: any[] }>();
</script>

<template>
  <WorkHubLayout>
    <h1 class="page-title mb-4">Workflows</h1>
    <div class="row g-4">
      <div class="col-xl-8">
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
          <thead><tr><th>Name</th><th>Status</th><th>Version</th><th></th></tr></thead>
          <tbody><tr v-for="workflow in workflows" :key="workflow.id">
            <td><strong>{{ workflow.name }}</strong><br><span class="text-muted-workhub">{{ workflow.description }}</span></td>
            <td><span class="badge badge-soft-primary">{{ workflow.status }}</span></td>
            <td>v{{ workflow.current_version }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-success me-2" @click="router.post(`/workflows/${workflow.id}/run`)"><i class="bi bi-play-fill"></i></button>
              <Link class="btn btn-sm btn-light" :href="`/workflows/${workflow.id}`"><i class="bi bi-arrow-right"></i></Link>
            </td>
          </tr></tbody>
        </table></div></div>
      </div>
      <div class="col-xl-4">
        <div class="card"><div class="card-header bg-transparent"><strong>Latest runs</strong></div>
          <ul class="list-group list-group-flush"><li v-for="run in runs" :key="run.id" class="list-group-item d-flex justify-content-between">
            <span>{{ run.id.slice(0, 8) }}</span><span class="badge badge-soft-success">{{ run.status }}</span>
          </li></ul>
        </div>
      </div>
    </div>
  </WorkHubLayout>
</template>
