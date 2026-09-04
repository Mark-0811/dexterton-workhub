<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';

const page = usePage<any>();
const theme = ref(localStorage.getItem('theme') || localStorage.getItem('workhub-theme') || 'light');
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash || {});
const sidebarSpaces = computed(() => page.props.sidebarSpaces || []);
const notifications = computed(() => page.props.notifications || []);
const notificationsSeen = ref(false);
const unreadNotificationCount = computed(() => notificationsSeen.value ? 0 : notifications.value.length);
const mazerLogoUrl = '/vendor/mazer/assets/static/images/logo/logo.svg';
const avatarUrl = computed(() => `/vendor/mazer/assets/static/images/faces/${(((user.value?.id || '').toString().length || 1) % 8) + 1}.jpg`);

const nav = [
  { label: 'Dashboard', href: '/dashboard', icon: 'bi-grid-fill', app: 'dashboard', exact: true },
  { label: 'Employees', href: '/employees', icon: 'bi-people-fill', app: 'employees', permissions: ['users.manage'], exact: true },
  { label: 'Requests & Approvals', href: '/requests', icon: 'bi-stack', app: 'requests', permissions: ['requests.create'] },
  { label: 'Workflows', href: '/workflows', icon: 'bi-diagram-3-fill', app: 'workflows', permissions: ['workflows.manage'] },
  { label: 'Projects', href: '/projects', icon: 'bi-kanban-fill', app: 'projects', permissions: ['projects.view'], exact: true },
  { label: 'Todos', href: '/todos', icon: 'bi-check2-square', app: 'todos', permissions: ['todos.manage'], exact: true },
];

const apps = [
  { label: 'Service Flows', href: '/apps/service-flows', icon: 'bi-bezier2', status: 'Catalog', app: 'service-flows' },
  { label: 'Employees', href: '/employees', icon: 'bi-people-fill', status: 'Live', app: 'employees', permissions: ['users.manage'] },
  { label: 'Requests', href: '/requests', icon: 'bi-inboxes-fill', status: 'Live', app: 'requests', permissions: ['requests.create'] },
  { label: 'Workflows', href: '/workflows', icon: 'bi-diagram-3-fill', status: 'Live', app: 'workflows', permissions: ['workflows.manage'] },
  { label: 'Expenses', href: '/apps/expenses', icon: 'bi-receipt-cutoff', status: 'Planned', app: 'expenses' },
  { label: 'Projects', href: '/projects', icon: 'bi-kanban-fill', status: 'Live', app: 'projects', permissions: ['projects.view'] },
  { label: 'Bookings', href: '/apps/bookings', icon: 'bi-calendar2-check-fill', status: 'Planned', app: 'bookings' },
  { label: 'Documents', href: '/apps/documents', icon: 'bi-file-earmark-richtext-fill', status: 'Planned', app: 'documents' },
  { label: 'E-Sign', href: '/apps/e-signature', icon: 'bi-pen-fill', status: 'Planned', app: 'e-signature' },
  { label: 'Automations', href: '/apps/automations', icon: 'bi-robot', status: 'Planned', app: 'automations' },
  { label: 'Webforms', href: '/apps/webforms', icon: 'bi-ui-checks-grid', status: 'Planned', app: 'webforms' },
  { label: 'Mails', href: '/admin/mail-settings', icon: 'bi-envelope-paper-fill', status: 'Live', app: 'mails', permissions: ['admin.manage'], exact: true },
  { label: 'Datasets & Reports', href: '/apps/datasets', icon: 'bi-database-fill', status: 'Live', app: 'datasets', permissions: ['admin.manage'], exact: true },
  { label: 'Odoo Import', href: '/integrations/odoo', icon: 'bi-cloud-arrow-down-fill', status: 'Live', app: 'odoo', permissions: ['api.use'] },
];

const adminNav = [
  { label: 'Administration', href: '/admin', icon: 'bi-shield-lock-fill', permissions: ['admin.manage'], exact: true },
  { label: 'Users & Groups', href: '/admin#users', icon: 'bi-people-fill', permissions: ['users.manage'], exact: true },
  { label: 'Mail Setup', href: '/admin/mail-settings', icon: 'bi-envelope-gear-fill', permissions: ['admin.manage'], exact: true },
  { label: 'Audit Trail', href: '/admin/audit', icon: 'bi-clipboard2-pulse-fill', permissions: ['audit.view'], exact: true },
];

function hasPermission(permission: string) {
  return (user.value?.permissions || []).includes(permission);
}

function hasAnyPermission(permissions?: string[]) {
  return !permissions?.length || permissions.some((permission) => hasPermission(permission));
}

function canAccessApp(app?: string) {
  if (!app) return true;
  const access = user.value?.app_access;
  return !Array.isArray(access) || access.length === 0 || access.includes(app);
}

function visible(item: any) {
  return canAccessApp(item.app) && hasAnyPermission(item.permissions);
}

const visibleNav = computed(() => nav.filter(visible));
const visibleApps = computed(() => apps.filter(visible));
const visibleAdminNav = computed(() => adminNav.filter(visible));
const canSeeAdminSettings = computed(() => hasAnyPermission(['admin.manage', 'users.manage']));
const canManageSpaces = computed(() => hasPermission('projects.manage') && canAccessApp('spaces'));

const toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2800,
  timerProgressBar: true,
});

watch(() => flash.value.success, (message) => {
  if (message) toast.fire({ icon: 'success', title: message });
}, { immediate: true });

watch(() => flash.value.error, (message) => {
  if (message) toast.fire({ icon: 'error', title: message });
}, { immediate: true });

function active(href: string, exact = false) {
  const path = window.location.pathname;
  const query = window.location.search;
  const hash = window.location.hash;
  const [cleanHref, hrefHash = ''] = href.split('#');
  if (href.includes('?')) return `${path}${query}` === href;
  if (hrefHash) return path === cleanHref && hash === `#${hrefHash}`;
  if (exact) return path === cleanHref && !query;
  return path === cleanHref || path.startsWith(`${cleanHref}/`);
}

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('theme', theme.value);
  localStorage.setItem('workhub-theme', theme.value);
  document.documentElement.setAttribute('data-bs-theme', theme.value);
  document.body.classList.add(theme.value);
}

function logout() {
  router.post('/logout');
}

function markNotificationsShown() {
  if (!notifications.value.length || notificationsSeen.value) return;

  notificationsSeen.value = true;
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
  fetch('/notifications/read', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': token,
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  }).catch(() => {
    notificationsSeen.value = false;
  });
}

function closeMobileSidebar() {
  if (window.innerWidth < 1200) {
    document.getElementById('sidebar')?.classList.remove('active');
    document.getElementById('sidebar')?.classList.add('inactive');
    document.querySelector('.sidebar-backdrop')?.remove();
    document.body.style.overflowY = 'auto';
  }
}
</script>

<template>
  <div class="workhub">
    <div id="sidebar">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative">
          <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
              <Link href="/dashboard" @click="closeMobileSidebar">
                <img :src="mazerLogoUrl" alt="Mazer">
              </Link>
            </div>
            <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
              <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true"
                role="img" class="iconify iconify--system-uicons" width="20" height="20"
                preserveAspectRatio="xMidYMid meet" viewBox="0 0 21 21">
                <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round"
                  stroke-linejoin="round">
                  <path
                    d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4zM4.136 4.136L5.55 5.55m9.9 9.9l1.414 1.414M1.5 10.5h2m14 0h2M4.135 16.863L5.55 15.45m9.899-9.9l1.414-1.415M10.5 19.5v-2m0-14v-2"
                    opacity=".3"></path>
                  <g transform="translate(-210 -1)">
                    <path d="M220.5 2.5v2m6.5.5l-1.5 1.5"></path>
                    <circle cx="220.5" cy="11.5" r="4"></circle>
                    <path d="m214 5l1.5 1.5m5 14v-2m6.5-.5l-1.5-1.5M214 18l1.5-1.5m-4-5h2m14 0h2"></path>
                  </g>
                </g>
              </svg>
              <div class="form-check form-switch fs-6">
                <input id="toggle-dark" class="form-check-input me-0" type="checkbox" :checked="theme === 'dark'" style="cursor: pointer" @change="toggleTheme">
                <label class="form-check-label"></label>
              </div>
              <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true"
                role="img" class="iconify iconify--mdi" width="20" height="20" preserveAspectRatio="xMidYMid meet"
                viewBox="0 0 24 24">
                <path fill="currentColor"
                  d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z">
                </path>
              </svg>
            </div>
            <div class="sidebar-toggler x">
              <button type="button" class="sidebar-hide d-xl-none d-block btn btn-link p-0"><i class="bi bi-x bi-middle"></i></button>
            </div>
          </div>
        </div>
        <div class="sidebar-menu">
          <ul class="menu mb-0 p-0">
            <li class="sidebar-title">Menu</li>
            <li v-for="item in visibleNav" :key="item.href" class="sidebar-item" :class="{ active: active(item.href, item.exact) }">
              <Link :href="item.href" class="sidebar-link" @click="closeMobileSidebar">
                <i :class="['bi', item.icon]"></i>
                <span>{{ item.label }}</span>
              </Link>
            </li>

            <li v-if="sidebarSpaces.length || canManageSpaces" class="sidebar-title mt-4 d-flex align-items-center justify-content-between">
              <span>Spaces</span>
              <Link v-if="canManageSpaces" href="/spaces" class="sidebar-title-action" title="Manage spaces" @click="closeMobileSidebar">
                <i class="bi bi-plus-circle"></i>
              </Link>
            </li>
            <li v-for="space in sidebarSpaces" :key="space.id" class="sidebar-item sidebar-space" :class="{ active: active(`/spaces/${space.id}`, true) }">
              <Link :href="`/spaces/${space.id}`" class="sidebar-link" @click="closeMobileSidebar">
                <span class="space-dot" :style="{ backgroundColor: space.color }"></span>
                <span class="space-label">{{ space.department }} · {{ space.name }}</span>
                <small class="ms-auto text-muted-workhub">{{ space.projects_count }}</small>
              </Link>
            </li>
            <li v-if="canManageSpaces" class="sidebar-item" :class="{ active: active('/spaces', true) }">
              <Link href="/spaces" class="sidebar-link" @click="closeMobileSidebar">
                <i class="bi bi-sliders"></i>
                <span>Manage Spaces</span>
              </Link>
            </li>

            <li v-if="visibleApps.length" class="sidebar-title mt-4">Rework Apps</li>
            <li v-for="app in visibleApps" :key="app.href" class="sidebar-item sidebar-app" :class="{ active: active(app.href, app.exact) }">
              <Link :href="app.href" class="sidebar-link" @click="closeMobileSidebar">
                <i :class="['bi', app.icon]"></i>
                <span>{{ app.label }}</span>
                <small :class="['ms-auto', app.status === 'Live' ? 'text-success' : 'text-muted-workhub']">{{ app.status }}</small>
              </Link>
            </li>

            <li v-if="visibleAdminNav.length" class="sidebar-title mt-4">Access</li>
            <li v-for="item in visibleAdminNav" :key="item.href" class="sidebar-item" :class="{ active: active(item.href, item.exact) }">
              <Link :href="item.href" class="sidebar-link" @click="closeMobileSidebar">
                <i :class="['bi', item.icon]"></i>
                <span>{{ item.label }}</span>
              </Link>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <main id="main" class="layout-navbar navbar-fixed">
      <header>
        <nav class="navbar navbar-expand navbar-light navbar-top">
          <div class="container-fluid">
              <button type="button" class="burger-btn d-block d-xl-none btn btn-link p-0" aria-label="Toggle menu">
                <i class="bi bi-justify fs-3"></i>
              </button>

              <div class="collapse navbar-collapse show" id="navbarSupportedContent">
                <div class="position-relative global-search me-auto">
                  <i class="bi bi-search search-icon"></i>
                  <input class="form-control" placeholder="Search users, requests, projects, workflows" />
                </div>

                <ul class="navbar-nav ms-auto mb-lg-0">
                  <li class="nav-item dropdown me-1">
                    <button class="nav-link active dropdown-toggle text-gray-600 btn btn-link" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="bi bi-envelope bi-sub fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-lg-end">
                      <li><h6 class="dropdown-header">Mail</h6></li>
                      <li><span class="dropdown-item">No new mail</span></li>
                    </ul>
                  </li>
                  <li class="nav-item dropdown me-3">
                    <button class="nav-link active dropdown-toggle text-gray-600 btn btn-link position-relative" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" @click="markNotificationsShown">
                      <i class="bi bi-bell bi-sub fs-4"></i>
                      <span v-if="unreadNotificationCount" class="badge badge-notification bg-danger">{{ unreadNotificationCount }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-center dropdown-menu-sm-end notification-dropdown">
                      <li class="dropdown-header"><h6>Notifications</h6></li>
                      <li v-for="notification in notifications" :key="notification.id" class="dropdown-item notification-item">
                        <Link class="d-flex align-items-center" :href="notification.href || '/dashboard'">
                          <div :class="['notification-icon', notification.category === 'project' ? 'bg-success' : notification.category === 'account' ? 'bg-info' : 'bg-primary']">
                            <i :class="['bi', notification.category === 'project' ? 'bi-kanban' : notification.category === 'account' ? 'bi-person-gear' : 'bi-bell']"></i>
                          </div>
                          <div class="notification-text ms-4">
                            <p class="notification-title font-bold">{{ notification.title }}</p>
                            <p class="notification-subtitle font-thin text-sm">{{ notification.body }}</p>
                          </div>
                        </Link>
                      </li>
                      <li v-if="!notifications.length"><span class="dropdown-item text-muted-workhub">No new notifications</span></li>
                      <li><p class="text-center py-2 mb-0 small text-muted-workhub">Opening this menu marks notifications as read.</p></li>
                    </ul>
                  </li>
                </ul>

              <div class="dropdown">
                <button type="button" class="btn btn-link p-0 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="user-menu d-flex">
                    <div class="user-name text-end me-3">
                      <h6 class="mb-0 text-gray-600">{{ user?.name }}</h6>
                      <p class="mb-0 text-sm text-gray-600">{{ user?.title || user?.account_type }}</p>
                    </div>
                    <div class="user-img d-flex align-items-center">
                      <div class="avatar avatar-md">
                        <img :src="avatarUrl" alt="User avatar">
                      </div>
                    </div>
                  </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width:11rem">
                  <li><h6 class="dropdown-header">Hello, {{ user?.name?.split(' ')[0] || 'there' }}!</h6></li>
                  <li><span class="dropdown-item-text small">{{ user?.email }}</span></li>
                  <li><Link class="dropdown-item" href="/profile"><i class="icon-mid bi bi-person-gear me-2"></i>Profile settings</Link></li>
                  <li v-if="canSeeAdminSettings"><Link class="dropdown-item" href="/admin"><i class="icon-mid bi bi-gear me-2"></i>Admin settings</Link></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button class="dropdown-item" @click="logout"><i class="icon-mid bi bi-box-arrow-left me-2"></i>Logout</button></li>
                </ul>
              </div>
              </div>
            </div>
        </nav>
      </header>

      <section id="main-content">
        <div class="page-heading mb-3">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><Link href="/dashboard">Home</Link></li>
              <li class="breadcrumb-item active">Workspace</li>
            </ol>
          </nav>
        </div>
        <slot />
      </section>
    </main>
  </div>
</template>
