<script setup>
import { nextTick, onUnmounted, ref, useId, watch } from 'vue';
import axios from 'axios';
import { api, errorMessage } from '../api';
import Icon from './Icon.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  city: { type: String, default: '' },
  label: { type: String, default: 'Address' },
  error: { type: [Array, String], default: null },
  required: Boolean,
});
const emit = defineEmits(['update:modelValue', 'select']);
const id = useId();
const root = ref(null);
const list = ref(null);
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const searchError = ref('');
const searched = ref(false);
const focused = ref(false);
const active = ref(-1);
let timer;
let controller;
let version = 0;
let inputValue = props.modelValue;

function cancel() {
  clearTimeout(timer);
  controller?.abort();
  version++;
  loading.value = false;
}
function reset() {
  cancel();
  results.value = [];
  open.value = false;
  searched.value = false;
  searchError.value = '';
  active.value = -1;
}
function input(event) {
  reset();
  inputValue = event.target.value;
  emit('update:modelValue', inputValue);
  const query = inputValue.trim();
  if (query.length < 3) return;
  open.value = true;
  if (query.length > 500) {
    searchError.value =
      'Use a shorter address to search, or place the pin manually.';
    return;
  }
  loading.value = true;
  const request = version;
  timer = setTimeout(() => search(query, request), 550);
}
async function search(query, request) {
  const current = new AbortController();
  controller = current;
  try {
    const { data } = await api.get('/api/addresses/search', {
      params: { q: query, city: props.city || undefined },
      signal: current.signal,
    });
    if (request !== version) return;
    results.value = data.data;
    active.value = results.value.length ? 0 : -1;
    searched.value = true;
    open.value = focused.value;
  } catch (error) {
    if (request === version && !axios.isCancel(error))
      searchError.value = errorMessage(error);
  } finally {
    if (request === version) loading.value = false;
  }
}
function choose(result) {
  reset();
  inputValue = result.address;
  emit('update:modelValue', result.address);
  emit('select', result);
}
function focus() {
  focused.value = true;
  open.value = searched.value || loading.value || !!searchError.value;
}
function blur(event) {
  if (root.value.contains(event.relatedTarget)) return;
  focused.value = false;
  open.value = false;
}
async function keydown(event) {
  if (event.key === 'Escape') {
    reset();
    return;
  }
  if (!open.value || !results.value.length) return;
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    active.value =
      (active.value +
        (event.key === 'ArrowDown' ? 1 : -1) +
        results.value.length) %
      results.value.length;
    await nextTick();
    list.value?.children[active.value]?.scrollIntoView({ block: 'nearest' });
  } else if (event.key === 'Enter' && active.value >= 0) {
    event.preventDefault();
    choose(results.value[active.value]);
  }
}
// Saved-address selection and edits loaded from the API do not start searches.
watch(
  () => props.modelValue,
  (value) => {
    if (value !== inputValue) {
      inputValue = value;
      reset();
    }
  },
);
watch(() => props.city, reset);
onUnmounted(cancel);
</script>

<template>
  <div ref="root" @focusout="blur">
    <label :for="id" class="label">
      {{ $t(label) }}
      <span v-if="required" class="ml-1 text-brand-600" aria-hidden="true">
        *
      </span>
    </label>
    <div class="relative">
      <input
        :id="id"
        :value="modelValue"
        class="field !pr-10"
        type="text"
        autocomplete="off"
        :required="required"
        maxlength="5000"
        :placeholder="$t('Start typing a street, building, or address…')"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        :aria-controls="`${id}-suggestions`"
        :aria-activedescendant="
          open && active >= 0 ? `${id}-option-${active}` : undefined
        "
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="`${id}-help${error ? ` ${id}-error` : ''}`"
        @input="input"
        @focus="focus"
        @keydown="keydown"
      />
      <Icon
        :name="loading ? 'LoaderCircle' : 'Search'"
        class="pointer-events-none absolute top-3 right-3 text-slate-400"
        :class="loading ? 'animate-spin' : ''"
        :size="16"
      />
      <div
        v-if="open"
        class="absolute top-full right-0 left-0 z-20 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
      >
        <p v-if="loading" class="p-4 text-xs text-slate-500" role="status">
          {{ $t('Searching addresses…') }}
        </p>
        <p
          v-else-if="searchError"
          class="p-4 text-xs leading-5 text-amber-700"
          role="status"
        >
          {{ $t(searchError) }}
        </p>
        <p
          v-else-if="searched && !results.length"
          class="p-4 text-xs text-slate-500"
          role="status"
        >
          {{
            $t(
              'No matching addresses. You can still enter the address and place the pin manually.',
            )
          }}
        </p>
        <ul
          v-else
          :id="`${id}-suggestions`"
          ref="list"
          role="listbox"
          :aria-label="$t('{label} suggestions', { label: $t(label) })"
          class="max-h-[min(16rem,35dvh)] touch-pan-y overflow-y-auto overscroll-contain"
        >
          <li
            v-for="(result, index) in results"
            :id="`${id}-option-${index}`"
            :key="result.id"
            role="option"
            :aria-selected="index === active"
            class="flex cursor-pointer items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0"
            :class="index === active ? 'bg-brand-50' : 'hover:bg-slate-50'"
            @pointerdown.prevent
            @click="choose(result)"
            @pointerenter="active = index"
          >
            <Icon
              name="MapPin"
              class="mt-0.5 shrink-0 text-brand-600"
              :size="17"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800">
                {{ result.address }}
              </p>
              <p class="mt-1 text-xs text-slate-500">{{ result.label }}</p>
            </div>
          </li>
        </ul>
        <p
          class="border-t border-slate-100 px-4 py-2 text-[10px] text-slate-400"
        >
          {{ $t('Address data © OpenStreetMap contributors · Photon') }}
        </p>
      </div>
    </div>
    <p :id="`${id}-help`" class="mt-1.5 text-[11px] leading-5 text-slate-400">
      {{
        $t(
          'Choose a suggestion to move the map pin. You can drag the pin to fine-tune it.',
        )
      }}
    </p>
    <span
      v-if="error"
      :id="`${id}-error`"
      class="mt-1.5 block text-xs text-red-600"
      role="alert"
    >
      {{ $t(Array.isArray(error) ? error[0] : error) }}
    </span>
  </div>
</template>
