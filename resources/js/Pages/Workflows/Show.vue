<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
defineProps<{ workflow: any }>();
</script>

<template>
  <WorkHubLayout>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div><h1 class="page-title">{{ workflow.name }}</h1><p class="text-muted-workhub mb-0">{{ workflow.description }}</p></div>
      <button class="btn btn-success" @click="router.post(`/workflows/${workflow.id}/run`)"><i class="bi bi-play-fill me-2"></i>Run</button>
    </div>
    <div class="card"><div class="card-body">
      <div class="row g-3">
        <div v-for="version in workflow.versions" :key="version.id" class="col-lg-6">
          <div class="border rounded p-3 h-100">
            <div class="d-flex justify-content-between mb-2"><strong>Version {{ version.version }}</strong><span class="badge badge-soft-success">published</span></div>
            <div class="small text-muted-workhub mb-3">Trigger: {{ version.trigger.type }}</div>
            <pre class="bg-body-tertiary p-3 rounded small mb-0">{{ JSON.stringify({ nodes: version.nodes, edges: version.edges }, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div></div>
  </WorkHubLayout>
</template>
