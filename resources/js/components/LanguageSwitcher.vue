<script setup>
import { locale, setLocale, t } from '../i18n';
</script>
<template>
  <label class="inline-flex shrink-0 items-center gap-2 text-xs text-slate-500">
    <span class="sr-only">{{ t('Language') }}</span>
    <select
      :value="locale"
      :aria-label="t('Language')"
      class="min-h-11 max-w-28 rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs text-slate-700 focus:border-brand-600 focus:outline-none"
      @change="setLocale($event.target.value)"
    >
      <option value="ka">ქართული</option>
      <option value="en">English</option>
    </select>
  </label>
</template>
