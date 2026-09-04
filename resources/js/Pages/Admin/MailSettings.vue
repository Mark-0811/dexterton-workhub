<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';

const props = defineProps<{ mailSettings: any; boardColors: Record<string, string> }>();

const form = useForm({
  enabled: !!props.mailSettings.enabled,
  smtp_host: props.mailSettings.smtp_host || '',
  smtp_port: props.mailSettings.smtp_port || 587,
  smtp_username: props.mailSettings.smtp_username || '',
  smtp_from_address: props.mailSettings.smtp_from_address || 'workhub@dexterton.com',
  smtp_from_name: props.mailSettings.smtp_from_name || 'Dexterton WorkHub',
  alert_days_before: props.mailSettings.alert_days_before || 7,
  send_time: props.mailSettings.send_time || '08:00',
  recipients: {
    owner: props.mailSettings.recipients?.owner ?? true,
    assignee: props.mailSettings.recipients?.assignee ?? true,
    manager: props.mailSettings.recipients?.manager ?? false,
  },
  task_colors: {
    todo: props.boardColors.todo || '#eaf1ff',
    doing: props.boardColors.doing || '#fff3cd',
    review: props.boardColors.review || '#ede7ff',
    done: props.boardColors.done || '#d1e7dd',
    cancelled: props.boardColors.cancelled || '#f8d7da',
  },
});

function save() {
  form.patch('/admin/mail-settings', { preserveScroll: true });
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading">
      <h3>Mail setup & deadline alerts</h3>
      <p class="text-muted-workhub mb-0">Configure SMTP details, deadline alert timing, recipients, and task board colors.</p>
    </div>

    <form class="row g-4" @submit.prevent="save">
      <div class="col-xl-7">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Mail server</h4>
            <p class="text-muted mb-0 small">Used by scheduled project/task deadline alerts.</p>
          </div>
          <div class="card-body row g-3">
            <div class="col-12">
              <div class="form-check form-switch">
                <input id="enabled" v-model="form.enabled" class="form-check-input" type="checkbox">
                <label class="form-check-label" for="enabled">Enable deadline email alerts</label>
              </div>
            </div>
            <div class="col-md-8">
              <label class="form-label">SMTP host</label>
              <input v-model="form.smtp_host" class="form-control" placeholder="smtp.dexterton.com">
            </div>
            <div class="col-md-4">
              <label class="form-label">Port</label>
              <input v-model="form.smtp_port" type="number" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Username</label>
              <input v-model="form.smtp_username" class="form-control" placeholder="workhub@dexterton.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">From email</label>
              <input v-model="form.smtp_from_address" type="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">From name</label>
              <input v-model="form.smtp_from_name" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Alert days before</label>
              <input v-model="form.alert_days_before" type="number" min="1" max="60" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Send time</label>
              <input v-model="form.send_time" type="time" class="form-control">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h4 class="card-title">Who gets alerts?</h4></div>
          <div class="card-body d-flex flex-wrap gap-4">
            <div class="form-check">
              <input id="recipientOwner" v-model="form.recipients.owner" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="recipientOwner">Project owner</label>
            </div>
            <div class="form-check">
              <input id="recipientAssignee" v-model="form.recipients.assignee" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="recipientAssignee">Task assignee</label>
            </div>
            <div class="form-check">
              <input id="recipientManager" v-model="form.recipients.manager" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="recipientManager">Manager when available</label>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-5">
        <div class="card">
          <div class="card-header">
            <h4 class="card-title">Task board colors</h4>
            <p class="text-muted mb-0 small">These colors apply to project lanes from To do through Done.</p>
          </div>
          <div class="card-body">
            <div v-for="(label, key) in { todo: 'To do', doing: 'Doing', review: 'Review', done: 'Done', cancelled: 'Cancelled' }" :key="key" class="d-flex align-items-center gap-3 mb-3">
              <input v-model="form.task_colors[key]" type="color" class="form-control form-control-color">
              <div class="flex-grow-1">
                <label class="form-label mb-0">{{ label }}</label>
                <input v-model="form.task_colors[key]" class="form-control form-control-sm">
              </div>
              <span class="badge rounded-pill" :style="{ backgroundColor: form.task_colors[key], color: '#25396f' }">Preview</span>
            </div>
          </div>
          <div class="card-footer text-end">
            <button class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              Save setup
            </button>
          </div>
        </div>
      </div>
    </form>
  </WorkHubLayout>
</template>
