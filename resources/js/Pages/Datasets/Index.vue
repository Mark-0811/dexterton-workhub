<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime } from '../../lib/dates';

const props = defineProps<{
  reports: any[];
  sources: Record<string, { label: string; description: string; columns: string[] }>;
  stats: Record<string, number>;
}>();

const sourceEntries = computed(() => Object.entries(props.sources));
const form = useForm({
  name: '',
  source: sourceEntries.value[0]?.[0] || 'projects',
  description: '',
  columns: [] as string[],
  filter_notes: '',
  visibility: 'admins',
});

const selectedSource = computed(() => props.sources[form.source]);

watch(() => form.source, () => {
  form.columns = selectedSource.value?.columns.slice(0, 5) || [];
}, { immediate: true });

function submit() {
  form.post('/apps/datasets/reports', {
    preserveScroll: true,
    onSuccess: () => form.reset('name', 'description', 'filter_notes'),
  });
}

function sourceLabel(source: string) {
  return props.sources[source]?.label || source;
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-start mb-4">
      <div>
        <h1 class="page-title">Datasets & Reports</h1>
        <p class="text-muted-workhub mb-0">Create reusable reports from WorkHub projects, tasks, requests, employees, Odoo imports, and audit data.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportCreateModal">
        <i class="bi bi-plus-lg me-2"></i>Create report
      </button>
    </div>

    <div class="row g-3 mb-4">
      <div v-for="(value, key) in stats" :key="key" class="col-6 col-lg-2">
        <div class="card dashboard-stat h-100">
          <div class="card-body">
            <h6 class="text-muted-workhub text-capitalize">{{ String(key).replace('_', ' ') }}</h6>
            <h3 class="mb-0">{{ value }}</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-8">
        <div class="card">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <div>
              <h4 class="mb-0">Saved reports</h4>
              <small class="text-muted-workhub">Reports define what data to show and which columns matter.</small>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Report</th>
                  <th>Dataset</th>
                  <th>Columns</th>
                  <th>Visibility</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="report in reports" :key="report.id">
                  <td>
                    <strong>{{ report.name }}</strong>
                    <div class="text-muted-workhub small">{{ report.description || 'No description' }}</div>
                  </td>
                  <td><span class="badge badge-soft-primary">{{ sourceLabel(report.source) }}</span></td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <span v-for="column in report.columns || []" :key="column" class="badge badge-soft-secondary">{{ column }}</span>
                    </div>
                  </td>
                  <td class="text-capitalize">{{ report.visibility }}</td>
                  <td>{{ formatDateTime(report.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="!reports.length" class="card-body">
            <div class="empty-state">
              No reports yet. Create your first report for projects, tasks, requests, employees, Odoo imports, or audit history.
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="card h-100">
          <div class="card-header bg-transparent">
            <h4 class="mb-0">Available datasets</h4>
            <small class="text-muted-workhub">Start with these WorkHub data sources.</small>
          </div>
          <div class="card-body dataset-source-list">
            <button
              v-for="[key, source] in sourceEntries"
              :key="key"
              class="dataset-source-card text-start"
              type="button"
              data-bs-toggle="modal"
              data-bs-target="#reportCreateModal"
              @click="form.source = key"
            >
              <span class="stat-icon bg-primary text-white"><i class="bi bi-database"></i></span>
              <span class="min-w-0">
                <strong>{{ source.label }}</strong>
                <small>{{ source.description }}</small>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div id="reportCreateModal" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" @submit.prevent="submit">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Create report</h5>
              <small class="text-muted-workhub">Choose a dataset and the columns users should see.</small>
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-7">
                <label class="form-label">Report name</label>
                <input v-model="form.name" class="form-control" :class="{ 'is-invalid': form.errors.name }" placeholder="Example: Active projects by Space">
                <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
              </div>
              <div class="col-md-5">
                <label class="form-label">Dataset</label>
                <select v-model="form.source" class="form-select">
                  <option v-for="[key, source] in sourceEntries" :key="key" :value="key">{{ source.label }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea v-model="form.description" class="form-control" rows="2" placeholder="What question does this report answer?"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Columns</label>
                <div class="report-column-grid">
                  <label v-for="column in selectedSource?.columns || []" :key="column" class="report-column-option">
                    <input v-model="form.columns" class="form-check-input" type="checkbox" :value="column">
                    <span>{{ column.replace('_', ' ') }}</span>
                  </label>
                </div>
                <div v-if="form.errors.columns" class="text-danger small mt-1">{{ form.errors.columns }}</div>
              </div>
              <div class="col-md-7">
                <label class="form-label">Filter notes</label>
                <input v-model="form.filter_notes" class="form-control" placeholder="Example: active only, due this month">
              </div>
              <div class="col-md-5">
                <label class="form-label">Visibility</label>
                <select v-model="form.visibility" class="form-select">
                  <option value="admins">Admins only</option>
                  <option value="managers">Managers</option>
                  <option value="everyone">Everyone</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Save report
            </button>
          </div>
        </form>
      </div>
    </div>
  </WorkHubLayout>
</template>
