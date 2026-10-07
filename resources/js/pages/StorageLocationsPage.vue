<script setup>
import { reactive, ref } from 'vue';
import { api, errorMessage } from '../api';
import { useList } from '../composables/useList';
import { useToasts } from '../stores/toasts';
import { mapLink } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import BaseModal from '../components/BaseModal.vue';
import FormField from '../components/FormField.vue';
import Pagination from '../components/Pagination.vue';
import AddressAutocomplete from '../components/AddressAutocomplete.vue';
import LocationMap from '../components/LocationMap.vue';
import Icon from '../components/Icon.vue';

const filters = reactive({ search: '' });
const { rows, meta, loading, error, load } = useList(
  '/api/storage-locations',
  filters,
);
const toasts = useToasts();
const modal = ref(false),
  editing = ref(null),
  deleting = ref(null),
  busy = ref(false),
  saveError = ref(''),
  errors = ref({});
const emptyForm = () => ({
  name: '',
  address: '',
  city: '',
  latitude: null,
  longitude: null,
  instructions: '',
  active: true,
});
const form = reactive(emptyForm());
function open(location = null) {
  editing.value = location;
  const values = { ...emptyForm(), ...location };
  for (const key of Object.keys(form)) form[key] = values[key];
  saveError.value = '';
  errors.value = {};
  modal.value = true;
}
function selectSuggestion(suggestion) {
  form.latitude = suggestion.latitude;
  form.longitude = suggestion.longitude;
  form.city = suggestion.city || '';
}
async function save() {
  busy.value = true;
  saveError.value = '';
  errors.value = {};
  try {
    const payload = { ...form };
    if (editing.value)
      await api.put(`/api/storage-locations/${editing.value.id}`, payload);
    else await api.post('/api/storage-locations', payload);
    modal.value = false;
    toasts.show('Storage location saved');
    await load(meta.value?.current_page || 1);
  } catch (e) {
    saveError.value = errorMessage(e);
    errors.value = e.response?.data.errors || {};
  } finally {
    busy.value = false;
  }
}
async function remove() {
  busy.value = true;
  saveError.value = '';
  try {
    await api.delete(`/api/storage-locations/${deleting.value.id}`);
    deleting.value = null;
    toasts.show('Storage location deleted');
    await load();
  } catch (e) {
    saveError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <PageHeader
    :title="$t('Storage locations')"
    :description="
      $t('Save warehouses and storage addresses with precise map locations.')
    "
    :eyebrow="$t('Workspace directory')"
  >
    <button class="btn btn-primary" @click="open()">
      <Icon name="Plus" :size="17" />
      {{ $t('Add storage location') }}
    </button>
  </PageHeader>
  <section class="card">
    <form class="filter-form flex gap-3 p-5" @submit.prevent="load()">
      <input
        v-model="filters.search"
        class="field"
        :placeholder="$t('Search storage locations…')"
        :aria-label="$t('Search storage locations')"
      />
      <button class="btn btn-secondary">{{ $t('Search') }}</button>
    </form>
    <AsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :message="
        $t(
          'No storage locations yet. Add your first warehouse or storage address.',
        )
      "
      @retry="load"
    >
      <div class="divide-y divide-slate-100">
        <article
          v-for="location in rows"
          :key="location.id"
          class="flex flex-wrap items-start justify-between gap-4 p-5"
        >
          <div class="min-w-0 flex-1">
            <h2 class="font-semibold">{{ location.name }}</h2>
            <p class="mt-1 break-words text-sm text-slate-500">
              {{ location.address }}
            </p>
            <p v-if="location.city" class="mt-1 text-xs text-slate-400">
              {{ location.city }}
            </p>
            <p
              v-if="location.instructions"
              class="mt-2 whitespace-pre-wrap text-xs text-slate-500"
            >
              {{ location.instructions }}
            </p>
            <span
              class="mt-2 inline-block rounded-full bg-slate-50 px-2 py-1 text-xs"
            >
              {{ $t(location.active ? 'Active' : 'Inactive') }}
            </span>
          </div>
          <div class="flex w-full flex-wrap gap-3 text-xs sm:w-auto">
            <a
              :href="mapLink(location)"
              target="_blank"
              rel="noopener noreferrer"
              class="link"
            >
              {{ $t('Open navigation') }}
            </a>
            <button class="link" @click="open(location)">
              {{ $t('Edit') }}
            </button>
            <button
              class="text-red-600"
              @click="
                deleting = location;
                saveError = '';
              "
            >
              {{ $t('Delete') }}
            </button>
          </div>
        </article>
      </div>
    </AsyncState>
    <Pagination :meta="meta" @page="load" />
  </section>
  <BaseModal
    v-if="modal"
    :title="$t(editing ? 'Edit storage location' : 'Add storage location')"
    @close="!busy && (modal = false)"
  >
    <form class="space-y-4" @submit.prevent="save">
      <FormField :label="$t('Storage name')" required :error="errors.name">
        <input
          v-model="form.name"
          class="field"
          required
          maxlength="255"
          :placeholder="$t('Main warehouse, branch…')"
        />
      </FormField>
      <AddressAutocomplete
        v-model="form.address"
        :city="form.city"
        :label="$t('Address')"
        required
        :error="errors.address"
        @select="selectSuggestion"
      />
      <FormField :label="$t('City')" :error="errors.city">
        <input v-model="form.city" class="field" maxlength="255" />
      </FormField>
      <LocationMap
        v-model:latitude="form.latitude"
        v-model:longitude="form.longitude"
        editable
        :label="$t('Storage location picker')"
      />
      <p
        v-if="errors.latitude || errors.longitude"
        class="text-xs text-red-600"
        role="alert"
      >
        {{ $t((errors.latitude || errors.longitude)[0]) }}
      </p>
      <details>
        <summary class="cursor-pointer text-xs text-slate-500">
          {{ $t('Enter coordinates manually (optional)') }}
        </summary>
        <div class="mt-3 grid grid-cols-2 gap-4">
          <FormField :label="$t('Latitude')" :error="errors.latitude">
            <input
              v-model.number="form.latitude"
              class="field"
              type="number"
              min="-90"
              max="90"
              step="any"
            />
          </FormField>
          <FormField :label="$t('Longitude')" :error="errors.longitude">
            <input
              v-model.number="form.longitude"
              class="field"
              type="number"
              min="-180"
              max="180"
              step="any"
            />
          </FormField>
        </div>
      </details>
      <FormField :label="$t('Instructions')" :error="errors.instructions">
        <textarea v-model="form.instructions" class="field" rows="3"></textarea>
      </FormField>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.active" type="checkbox" class="accent-brand-600" />
        {{ $t('Active') }}
      </label>
      <p v-if="saveError" class="text-sm text-red-600" role="alert">
        {{ $t(saveError) }}
      </p>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="modal = false"
        >
          {{ $t('Cancel') }}
        </button>
        <button class="btn btn-primary" :disabled="busy">
          {{ $t(busy ? 'Saving…' : 'Save storage location') }}
        </button>
      </div>
    </form>
  </BaseModal>
  <BaseModal
    v-if="deleting"
    :title="$t('Delete storage location?')"
    @close="!busy && (deleting = null)"
  >
    <p class="text-sm leading-6 text-slate-600">
      {{
        $t(
          'Remove {name} from the storage directory? Its record will remain stored.',
          { name: deleting.name },
        )
      }}
    </p>
    <p v-if="saveError" class="mt-4 text-sm text-red-600" role="alert">
      {{ $t(saveError) }}
    </p>
    <div class="mt-6 flex justify-end gap-3">
      <button
        class="btn btn-secondary"
        :disabled="busy"
        @click="deleting = null"
      >
        {{ $t('Cancel') }}
      </button>
      <button class="btn btn-danger" :disabled="busy" @click="remove">
        {{ $t(busy ? 'Deleting…' : 'Delete storage location') }}
      </button>
    </div>
  </BaseModal>
</template>
