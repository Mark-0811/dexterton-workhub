<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';

const props = defineProps<{ userRecord?: any; sourceEmployee?: any; roles: any[]; groups: any[]; offices: any[]; managers: any[]; spaces: any[]; appCatalog: string[] }>();
const editing = !!props.userRecord;
const employee = props.sourceEmployee;

function usernameFromEmployee() {
  const emailName = employee?.email?.split('@')?.[0];
  return emailName || employee?.name?.toLowerCase()?.replace(/[^a-z0-9]+/g, '.')?.replace(/(^\.|\.$)/g, '') || '';
}

const form = useForm({
  employee_id: employee?.id || '',
  name: props.userRecord?.name || employee?.name || '',
  email: props.userRecord?.email || employee?.email || '',
  username: props.userRecord?.username || usernameFromEmployee(),
  title: props.userRecord?.title || employee?.job_title || '',
  account_type: props.userRecord?.account_type || 'user',
  status: props.userRecord?.status || (employee ? 'active' : 'active'),
  office_id: props.userRecord?.office_id || employee?.office_id || '',
  manager_id: props.userRecord?.manager_id || employee?.manager_id || '',
  password: '',
  invite_only: false,
  role_ids: props.userRecord?.roles?.map((r: any) => r.id) || [],
  group_ids: props.userRecord?.groups?.map((g: any) => g.id) || [],
  app_access: props.userRecord?.app_access || ['dashboard', 'employees', 'projects', 'todos'],
  visible_space_ids: props.userRecord?.visible_spaces?.map((space: any) => space.id) || props.spaces.map((space: any) => space.id),
});

function submit() {
  editing ? form.patch(`/admin/users/${props.userRecord.id}`) : form.post('/admin/users');
}
</script>

<template>
  <WorkHubLayout>
    <div class="page-heading mb-4">
      <h1 class="page-title">{{ editing ? 'Edit user' : 'Add new user' }}</h1>
      <p class="text-muted-workhub mb-0">Create local access, assign role/group/office, and optionally send an invite-style setup.</p>
    </div>
    <div v-if="employee && !editing" class="alert alert-light-primary d-flex align-items-start gap-3">
      <div class="stat-icon bg-primary text-white flex-shrink-0"><i class="bi bi-person-plus"></i></div>
      <div>
        <strong>Creating login access from employee record</strong>
        <div class="small">
          WorkHub copied {{ employee.name }}{{ employee.email ? ` (${employee.email})` : '' }} from the employee directory.
          Review the access, password/invite mode, and Space visibility before saving.
        </div>
      </div>
    </div>
    <form class="card" @submit.prevent="submit">
      <div class="card-body">
        <div class="form-section-title mb-3">Identity</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name</label><input v-model="form.name" class="form-control"><div class="text-danger small">{{ form.errors.name }}</div></div>
          <div class="col-md-6"><label class="form-label">Email</label><input v-model="form.email" type="email" class="form-control"><div class="text-danger small">{{ form.errors.email }}</div></div>
          <div class="col-md-4"><label class="form-label">Username</label><input v-model="form.username" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Title</label><input v-model="form.title" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Account type</label><select v-model="form.account_type" class="form-select"><option>user</option><option>manager</option><option>system_owner</option><option>guest</option></select></div>
        </div>
        <hr>
        <div class="form-section-title mb-3">Access and organization</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Status</label><select v-model="form.status" class="form-select"><option>active</option><option>invited</option><option>inactive</option><option>locked</option></select></div>
          <div class="col-md-4"><label class="form-label">Office</label><select v-model="form.office_id" class="form-select"><option value="">No office</option><option v-for="office in offices" :key="office.id" :value="office.id">{{ office.name }}</option></select></div>
          <div class="col-md-4"><label class="form-label">Manager</label><select v-model="form.manager_id" class="form-select"><option value="">No manager</option><option v-for="manager in managers" :key="manager.id" :value="manager.id">{{ manager.name }}</option></select></div>
          <div class="col-md-6"><label class="form-label">Roles</label><select v-model="form.role_ids" class="form-select" multiple><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div>
          <div class="col-md-6"><label class="form-label">Groups</label><select v-model="form.group_ids" class="form-select" multiple><option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option></select></div>
        </div>
        <hr>
        <div class="form-section-title mb-3">Login method</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Temporary password</label><input v-model="form.password" type="password" class="form-control" :placeholder="editing ? 'Leave blank to keep password' : 'Required unless invite only'"><div class="text-danger small">{{ form.errors.password }}</div></div>
          <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input id="invite" v-model="form.invite_only" class="form-check-input" type="checkbox"><label class="form-check-label" for="invite">Invite/setup mode</label></div></div>
        </div>
        <hr>
        <div class="form-section-title mb-3">Application access</div>
        <div class="row g-2">
          <div v-for="app in appCatalog" :key="app" class="col-md-3"><label class="form-check"><input v-model="form.app_access" class="form-check-input" type="checkbox" :value="app"><span class="form-check-label text-capitalize">{{ app }}</span></label></div>
        </div>
        <hr>
        <div class="form-section-title mb-2">Space visibility</div>
        <p class="text-muted-workhub small mb-3">Choose the department Spaces this user can see in the sidebar, dashboard, and project list. Their own projects and assigned tasks stay visible.</p>
        <div class="row g-3">
          <div v-for="space in spaces" :key="space.id" class="col-md-6 col-xl-4">
            <label class="space-permission-card">
              <input v-model="form.visible_space_ids" class="form-check-input" type="checkbox" :value="space.id">
              <span class="space-dot" :style="{ backgroundColor: space.color }"></span>
              <span class="min-w-0">
                <strong class="d-block text-truncate">{{ space.department }} · {{ space.name }}</strong>
                <small class="text-muted-workhub">{{ space.description || 'Project workspace' }}</small>
              </span>
            </label>
          </div>
          <div v-if="!spaces.length" class="col-12">
            <div class="empty-state py-4">No Spaces are available yet.</div>
          </div>
        </div>
      </div>
      <div class="card-footer text-end"><button class="btn btn-primary" :disabled="form.processing"><i class="bi bi-save me-2"></i>Save user</button></div>
    </form>
  </WorkHubLayout>
</template>
