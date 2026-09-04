<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';
import { formatDateTime } from '../../lib/dates';
defineProps<{ todos: any[] }>();
const form = useForm({ title: '', priority: 'normal', due_at: '' });
</script>

<template>
  <WorkHubLayout>
    <div class="row g-4">
      <div class="col-lg-5">
        <h1 class="page-title mb-4">Todos</h1>
        <form class="card" @submit.prevent="form.post('/todos', { onSuccess: () => form.reset() })">
          <div class="card-body">
            <label class="form-label">Task</label><input v-model="form.title" class="form-control mb-3">
            <label class="form-label">Priority</label><select v-model="form.priority" class="form-select mb-3"><option>low</option><option>normal</option><option>high</option><option>urgent</option></select>
            <label class="form-label">Due</label><input v-model="form.due_at" class="form-control mb-3" type="datetime-local">
            <button class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Add todo</button>
          </div>
        </form>
      </div>
      <div class="col-lg-7">
        <div class="card"><ul class="list-group list-group-flush">
          <li v-for="todo in todos" :key="todo.id" class="list-group-item d-flex align-items-center gap-3">
            <input class="form-check-input" type="checkbox" :checked="!!todo.completed_at" @change="router.post(`/todos/${todo.id}/toggle`)">
            <div class="flex-grow-1" :class="{ 'text-decoration-line-through text-muted-workhub': todo.completed_at }">
              <strong>{{ todo.title }}</strong><br><small>{{ formatDateTime(todo.due_at) }}</small>
            </div>
            <span class="badge badge-soft-warning">{{ todo.priority }}</span>
          </li>
        </ul></div>
      </div>
    </div>
  </WorkHubLayout>
</template>
