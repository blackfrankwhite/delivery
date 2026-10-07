<script setup>
defineProps({ title: String, description: String, eyebrow: String });
</script>
<template>
  <div
    class="mb-5 flex flex-col gap-4 sm:mb-7 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between"
  >
    <div class="min-w-0">
      <div v-if="eyebrow" class="eyebrow mb-2">{{ $t(eyebrow) }}</div>
      <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
        {{ $t(title) }}
      </h1>
      <p v-if="description" class="mt-1.5 text-sm text-slate-500">
        {{ $t(description) }}
      </p>
    </div>
    <div
      class="flex w-full flex-wrap gap-2 [&>.btn]:flex-1 sm:w-auto sm:[&>.btn]:flex-none"
    >
      <slot />
    </div>
  </div>
</template>
