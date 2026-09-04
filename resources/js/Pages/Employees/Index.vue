<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
defineProps<{ employees: any[]; usersWithoutEmployee: any[] }>();
</script>
<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-center mb-4"><div><h1 class="page-title">Employees</h1><p class="text-muted-workhub mb-0">Local users plus employees imported from Odoo.</p></div><Link href="/integrations/odoo" class="btn btn-primary"><i class="bi bi-cloud-arrow-down me-2"></i>Import from Odoo</Link></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Employee</th><th>Job</th><th>Department</th><th>Manager</th><th>Office</th><th>Status</th><th>Source</th><th class="text-end">User access</th></tr></thead>
      <tbody>
        <tr v-for="employee in employees" :key="employee.id">
          <td>
            <strong>{{ employee.name }}</strong><br>
            <span class="text-muted-workhub">{{ employee.email || 'No email' }}</span>
          </td>
          <td>{{ employee.job_title || '—' }}</td>
          <td>{{ employee.department || '—' }}</td>
          <td>{{ employee.manager?.name || '—' }}</td>
          <td>{{ employee.office?.name || '—' }}</td>
          <td><span class="badge badge-soft-primary text-capitalize">{{ employee.status }}</span></td>
          <td>{{ employee.external_source || 'workhub' }}</td>
          <td class="text-end">
            <Link
              v-if="employee.user"
              :href="`/admin/users/${employee.user.id}/edit`"
              class="btn btn-sm btn-light-primary"
            >
              <i class="bi bi-pencil-square me-1"></i>Edit user
            </Link>
            <Link
              v-else
              :href="`/admin/users/create?employee=${employee.id}`"
              class="btn btn-sm btn-primary"
            >
              <i class="bi bi-person-plus me-1"></i>Create user
            </Link>
          </td>
        </tr>
      </tbody>
    </table></div><div v-if="!employees.length" class="card-body"><div class="empty-state">No employees yet. Import from Odoo or add users in Admin.</div></div></div>
  </WorkHubLayout>
</template>
