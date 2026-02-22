<script setup lang="ts">
interface Props {
  stats: {
    total_students: number;
    total_teachers: number;
    total_classes: number;
  };
}

defineProps<Props>();
</script>

<template>
  <div class="col-span-12 lg:col-span-6 xl:col-span-3">
    <div class="card mb-0">
      <div class="flex justify-between mb-4">
        <div>
          <span class="block text-muted-color font-medium mb-4">Students</span>
          <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
            {{ stats.total_students }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
          <i class="pi pi-users text-blue-500 text-xl!"></i>
        </div>
      </div>
      <span class="text-muted-color">Total active students</span>
    </div>
  </div>
  
  <div class="col-span-12 lg:col-span-6 xl:col-span-3">
    <div class="card mb-0">
      <div class="flex justify-between mb-4">
        <div>
          <span class="block text-muted-color font-medium mb-4">Teachers</span>
          <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
            {{ stats.total_teachers }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
          <i class="pi pi-briefcase text-orange-500 text-xl!"></i>
        </div>
      </div>
      <span class="text-muted-color">Faculty members</span>
    </div>
  </div>
  
  <div class="col-span-12 lg:col-span-6 xl:col-span-3">
    <div class="card mb-0">
      <div class="flex justify-between mb-4">
        <div>
          <span class="block text-muted-color font-medium mb-4">Classes</span>
          <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
            {{ stats.total_classes }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
          <i class="pi pi-book text-cyan-500 text-xl!"></i>
        </div>
      </div>
      <span class="text-muted-color">Active classes</span>
    </div>
  </div>
  
  <div class="col-span-12 lg:col-span-6 xl:col-span-3">
    <div class="card mb-0">
      <div class="flex justify-between mb-4">
        <div>
          <span class="block text-muted-color font-medium mb-4">Avg Students/Class</span>
          <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
            {{ stats.total_classes > 0 ? Math.round(stats.total_students / stats.total_classes) : 0 }}
          </div>
        </div>
        <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
          <i class="pi pi-chart-bar text-purple-500 text-xl!"></i>
        </div>
      </div>
      <span class="text-muted-color">Class capacity usage</span>
    </div>
  </div>
</template>
