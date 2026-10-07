<script setup>
import { initials } from '../utils';
defineProps({ driver: Object });
</script>
<template>
  <div v-if="driver" class="flex items-center gap-2">
    <span
      class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-500"
    >
      {{ initials(driver.name) }}
    </span>
    <span class="text-xs">{{ driver.name }}</span>
  </div>
  <span v-else class="text-xs text-slate-400">{{ $t('Unassigned') }}</span>
</template>
