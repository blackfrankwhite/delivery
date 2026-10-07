<script setup>
import { computed } from 'vue';
const props = defineProps({ status: String });
const styles = {
  pending: 'bg-slate-100 text-slate-600',
  assigned: 'bg-blue-50 text-blue-700',
  started: 'bg-amber-50 text-amber-700',
  arrived: 'bg-violet-50 text-violet-700',
  delivered: 'bg-emerald-50 text-emerald-700',
  failed: 'bg-red-50 text-red-700',
  cancelled: 'bg-slate-100 text-slate-500',
  planned: 'bg-blue-50 text-blue-700',
  completed: 'bg-emerald-50 text-emerald-700',
};
const label = computed(() =>
  props.status === 'started'
    ? 'In progress'
    : props.status?.replaceAll('_', ' '),
);
</script>
<template>
  <span
    :class="styles[status] || styles.pending"
    class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize"
  >
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $t(label) }}
  </span>
</template>
