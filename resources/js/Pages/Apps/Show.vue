<script setup lang="ts">
import WorkHubLayout from '../../Layouts/WorkHubLayout.vue';

defineProps<{ module: { title: string; phase: string; status: string; icon: string }; slug: string }>();
</script>

<template>
  <WorkHubLayout>
    <div class="row g-4 align-items-stretch">
      <div class="col-xl-8">
        <div class="card h-100">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
              <div class="stat-icon bg-primary-subtle text-primary">
                <i :class="['bi', module.icon]"></i>
              </div>
              <div>
                <h1 class="page-title">{{ module.title }}</h1>
                <p class="text-muted-workhub mb-0">{{ module.phase }} · {{ module.status }}</p>
              </div>
            </div>

            <div class="alert alert-primary border-0">
              This app tile is now wired and clickable. The production workflow for this Rework module will be filled from the parity catalog before it is marked live.
            </div>

            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light">
                  <strong>Screens</strong>
                  <div class="text-muted-workhub small">Discovery pending</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light">
                  <strong>Permissions</strong>
                  <div class="text-muted-workhub small">Policy mapping pending</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light">
                  <strong>Data contract</strong>
                  <div class="text-muted-workhub small">API draft pending</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="card h-100">
          <div class="card-header bg-transparent"><strong>Acceptance checklist</strong></div>
          <div class="card-body">
            <ul class="list-unstyled mb-0">
              <li class="mb-3"><i class="bi bi-check-circle text-success me-2"></i>Navigation endpoint exists</li>
              <li class="mb-3"><i class="bi bi-circle text-muted-workhub me-2"></i>Rework parity fields mapped</li>
              <li class="mb-3"><i class="bi bi-circle text-muted-workhub me-2"></i>State transitions implemented</li>
              <li><i class="bi bi-circle text-muted-workhub me-2"></i>Role/action tests added</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </WorkHubLayout>
</template>
