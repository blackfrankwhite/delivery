<script setup>
import Icon from './Icon.vue';
defineProps({
  loading: Boolean,
  error: String,
  empty: Boolean,
  message: { type: String, default: 'No records found.' },
});
defineEmits(['retry']);
</script>
<template>
  <div v-if="loading" class="p-12 text-center text-slate-400" role="status">
    <Icon name="LoaderCircle" class="mx-auto mb-3 animate-spin" />
    {{ $t('Loading…') }}
  </div>
  <div v-else-if="error" class="p-8 text-center" role="alert">
    <p class="mb-3 text-red-600">{{ $t(error) }}</p>
    <button class="btn btn-secondary" @click="$emit('retry')">
      {{ $t('Try again') }}
    </button>
  </div>
  <div v-else-if="empty" class="p-12 text-center">
    <Icon name="Inbox" class="mx-auto mb-3 text-slate-300" :size="30" />
    <p class="text-slate-500">{{ $t(message) }}</p>
  </div>
  <slot v-else />
</template>
