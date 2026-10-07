<script setup>
defineProps({ meta: Object });
defineEmits(['page']);
</script>
<template>
  <div
    v-if="meta"
    class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center border-t border-slate-100 px-5 py-4 text-xs text-slate-500"
  >
    <span>
      {{
        $t('{from}–{to} of {total} records', {
          from: meta.from || 0,
          to: meta.to || 0,
          total: meta.total,
        })
      }}
    </span>
    <div class="flex items-center justify-between gap-2 sm:gap-3">
      <button
        class="btn btn-secondary !min-h-8 !px-3 !py-1 !text-xs"
        :disabled="meta.current_page <= 1"
        @click="$emit('page', meta.current_page - 1)"
      >
        {{ $t('Previous') }}
      </button>
      <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button
        class="btn btn-secondary !min-h-8 !px-3 !py-1 !text-xs"
        :disabled="meta.current_page >= meta.last_page"
        @click="$emit('page', meta.current_page + 1)"
      >
        {{ $t('Next') }}
      </button>
    </div>
  </div>
</template>
