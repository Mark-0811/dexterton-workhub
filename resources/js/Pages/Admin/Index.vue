<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime } from '../../lib/dates';
const props = defineProps<{ users: any[]; offices: any[]; groups: any[]; roles: any[]; spaces: any[]; auditEvents: any[] }>();

const adminStats = [
  { label: 'Users', value: props.users.length, icon: 'iconly-boldProfile', color: 'purple' },
  { label: 'Offices', value: props.offices.length, icon: 'iconly-boldHome', color: 'blue' },
  { label: 'Groups', value: props.groups.length, icon: 'iconly-boldUser1', color: 'green' },
  { label: 'Spaces', value: props.spaces.length, icon: 'iconly-boldCategory', color: 'red' },
];
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading d-flex justify-content-between align-items-center">
      <div>
        <h3>Administration</h3>
        <p class="text-muted-workhub mb-0">Manage Dexterton users, offices, groups, roles, and audit visibility.</p>
      </div>
      <div class="d-flex gap-2">
        <Link href="/spaces" class="btn btn-outline-primary"><i class="bi bi-grid-1x2 me-2"></i>Spaces</Link>
        <Link href="/admin/mail-settings" class="btn btn-outline-primary"><i class="bi bi-envelope-gear me-2"></i>Mail setup</Link>
        <Link href="/admin/audit" class="btn btn-outline-primary"><i class="bi bi-clipboard2-pulse me-2"></i>Audit trail</Link>
        <Link href="/admin/users/create" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Add user</Link>
      </div>
    </div>

    <div class="row">
      <div v-for="stat in adminStats" :key="stat.label" class="col-6 col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body px-4 py-4-5">
            <div class="row">
              <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                <div :class="['stats-icon mb-2', stat.color]">
                  <i :class="stat.icon"></i>
                </div>
              </div>
              <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                <h6 class="text-muted font-semibold">{{ stat.label }}</h6>
                <h6 class="font-extrabold mb-0">{{ stat.value }}</h6>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-8">
        <div id="users" class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Users and access</h4>
            <Link href="/admin/users/create" class="btn btn-sm btn-primary">Add user</Link>
          </div>
          <div class="card-body px-0 py-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-4">User</th><th>Office</th><th>Roles</th><th>Status</th><th class="pe-4"></th></tr></thead>
                <tbody>
                  <tr v-for="user in users" :key="user.id">
                    <td class="ps-4"><strong>{{ user.name }}</strong><br><span class="text-muted-workhub">{{ user.email }}</span></td>
                    <td>{{ user.office?.name || 'No office' }}</td>
                    <td><span v-for="role in user.roles" :key="role.id" class="badge badge-soft-primary me-1">{{ role.name }}</span></td>
                    <td><span class="badge badge-soft-success text-capitalize">{{ user.status }}</span></td>
                    <td class="text-end pe-4"><Link :href="`/admin/users/${user.id}/edit`" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></Link></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div id="audit" class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Readable audit trail</h4>
            <Link href="/admin/audit">View all</Link>
          </div>
          <div class="card-content pb-3">
            <div v-for="event in auditEvents" :key="event.id" class="recent-message d-flex px-4 py-3">
              <div class="avatar avatar-lg">
                <div class="avatar-content bg-light-primary text-primary"><i class="bi bi-activity"></i></div>
              </div>
              <div class="name ms-4 flex-grow-1">
                <h6 class="mb-1 audit-message">{{ event.message }}</h6>
                <h6 class="text-muted mb-0">{{ formatDateTime(event.created_at) }}</h6>
              </div>
            </div>
            <div v-if="!auditEvents.length" class="px-4 text-muted-workhub small">No audit events yet.</div>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4 class="mb-0">Spaces</h4>
              <small class="text-muted-workhub">Department project areas shown in the sidenav</small>
            </div>
            <Link href="/spaces" class="btn btn-sm btn-primary">Manage</Link>
          </div>
          <div class="card-body">
            <Link
              v-for="space in spaces"
              :key="space.id"
              :href="`/projects?space=${space.id}`"
              class="space-card d-flex gap-3 align-items-center mb-2"
            >
              <span class="space-dot" :style="{ backgroundColor: space.color }"></span>
              <span class="flex-grow-1 min-w-0">
                <strong class="d-block text-truncate">{{ space.department }} · {{ space.name }}</strong>
                <small class="text-muted-workhub">{{ space.projects_count }} projects · owner {{ space.owner?.name || 'Unassigned' }}</small>
              </span>
              <i class="bi bi-chevron-right text-muted-workhub"></i>
            </Link>
            <div v-if="!spaces.length" class="empty-state py-4">No Spaces yet.</div>
          </div>
        </div>
        <div class="card mb-4"><div class="card-header"><h4>Groups</h4></div>
          <ul class="list-group list-group-flush"><li v-for="group in groups" :key="group.id" class="list-group-item d-flex justify-content-between"><span>{{ group.name }}</span><span>{{ group.users_count }}</span></li></ul>
        </div>
        <div class="card"><div class="card-header"><h4>Roles</h4></div>
          <ul class="list-group list-group-flush"><li v-for="role in roles" :key="role.id" class="list-group-item">{{ role.name }} <small class="text-muted-workhub">({{ role.permissions.length }} permissions)</small></li></ul>
        </div>
      </div>
    </div>
  </WorkHubLayout>
</template>
