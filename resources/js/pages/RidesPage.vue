<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import { api, allPages, errorMessage } from '../api';
import { useList } from '../composables/useList';
import { today, dateLabel } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import BaseModal from '../components/BaseModal.vue';
import FormField from '../components/FormField.vue';
import Pagination from '../components/Pagination.vue';
import StatusBadge from '../components/StatusBadge.vue';
import DriverBadge from '../components/DriverBadge.vue';
import Icon from '../components/Icon.vue';
const auth = useAuth(),
  toasts = useToasts(),
  router = useRouter(),
  filters = reactive({ date: '', driver_id: '', status: '' }),
  drivers = ref([]),
  storages = ref([]),
  modal = ref(false),
  form = reactive({
    driver_id: null,
    date: today(),
    notes: '',
    storage_location_id: null,
  }),
  busy = ref(false),
  saveError = ref(''),
  errors = ref({});
const filtersOpen = ref(false);
const { rows, meta, loading, error, load } = useList('/api/rides', filters);
onMounted(async () => {
  try {
    storages.value = (await api.get('/api/ride-storage-locations')).data.data;
    if (storages.value.length === 1)
      form.storage_location_id = storages.value[0].id;
  } catch (e) {
    toasts.show(errorMessage(e), 'error');
  }
  if (auth.driver) form.driver_id = auth.user.id;
  if (!auth.driver)
    try {
      drivers.value = await allPages('/api/drivers', { active: 1 });
    } catch (e) {
      toasts.show(errorMessage(e), 'error');
    }
});
async function save() {
  busy.value = true;
  saveError.value = '';
  errors.value = {};
  try {
    const ride = (await api.post('/api/rides', form)).data.data;
    toasts.show('Ride created');
    await router.push(
      auth.driver ? `/driver/rides/${ride.id}` : `/rides/${ride.id}`,
    );
  } catch (e) {
    errors.value = e.response?.data.errors || {};
    saveError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <PageHeader
    :title="$t(auth.driver ? 'My rides' : 'Rides & route planning')"
    :description="
      $t('An organized route. A clear order. A smoother day on the road.')
    "
    :eyebrow="$t('Route planning')"
  >
    <button class="btn btn-secondary" :disabled="loading" @click="load()">
      <Icon name="RefreshCw" :size="16" />
      {{ $t('Refresh') }}
    </button>
    <button
      class="btn btn-primary"
      @click="
        modal = true;
        saveError = '';
        errors = {};
      "
    >
      <Icon name="Plus" :size="16" />
      {{ $t('Create ride') }}
    </button>
  </PageHeader>
  <section class="card">
    <div class="border-b border-slate-100 p-4 md:hidden">
      <button
        class="btn btn-secondary w-full"
        :aria-expanded="filtersOpen"
        aria-controls="list-filters"
        @click="filtersOpen = !filtersOpen"
      >
        {{ $t('Filters') }}
        <Icon :name="filtersOpen ? 'ChevronUp' : 'ChevronDown'" :size="16" />
      </button>
    </div>
    <div id="list-filters" :class="filtersOpen ? '' : 'hidden md:block'">
      <form
        class="filter-form flex flex-wrap gap-3 p-5"
        @submit.prevent="
          load();
          filtersOpen = false;
        "
      >
        <input
          v-model="filters.date"
          class="field !w-auto"
          type="date"
          :aria-label="$t('Ride date')"
        />
        <select
          v-if="!auth.driver"
          v-model="filters.driver_id"
          class="field !w-auto"
          :aria-label="$t('Driver')"
        >
          <option value="">{{ $t('All drivers') }}</option>
          <option v-for="d in drivers" :key="d.id" :value="d.id">
            {{ d.name }}
          </option>
        </select>
        <select
          v-model="filters.status"
          class="field !w-auto"
          :aria-label="$t('Ride status')"
        >
          <option value="">{{ $t('All statuses') }}</option>
          <option
            v-for="s in ['planned', 'started', 'completed']"
            :key="s"
            :value="s"
          >
            {{ $t(s) }}
          </option>
        </select>
        <button class="btn btn-secondary">{{ $t('Apply filters') }}</button>
      </form>
    </div>
    <AsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :message="$t('No rides found. Your next route starts here.')"
      @retry="load"
    >
      <div class="divide-y divide-slate-100 md:hidden">
        <article v-for="ride in rows" :key="ride.id" class="space-y-4 p-4">
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold">
              {{ $t('Ride #{id}', { id: ride.id }) }}
            </h2>
            <StatusBadge :status="ride.status" />
          </div>
          <DriverBadge :driver="ride.driver" />
          <div class="flex flex-wrap gap-4 text-sm text-slate-500">
            <span>{{ dateLabel(ride.date) }}</span>
            <span>{{ ride.tasks_count }} {{ $t('stops') }}</span>
          </div>
          <RouterLink
            :to="auth.driver ? `/driver/rides/${ride.id}` : `/rides/${ride.id}`"
            class="btn btn-secondary w-full"
          >
            {{ $t('View route →') }}
          </RouterLink>
        </article>
      </div>
      <div class="table-wrap hidden md:block">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('Ride') }}</th>
              <th>{{ $t('Driver') }}</th>
              <th>{{ $t('Date') }}</th>
              <th>{{ $t('Stops') }}</th>
              <th>{{ $t('Status') }}</th>
              <th>{{ $t('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ride in rows" :key="ride.id">
              <td class="font-medium text-slate-800">
                {{ $t('Ride #{id}', { id: ride.id }) }}
              </td>
              <td><DriverBadge :driver="ride.driver" /></td>
              <td class="text-xs">{{ dateLabel(ride.date) }}</td>
              <td class="text-xs">{{ ride.tasks_count }} {{ $t('stops') }}</td>
              <td><StatusBadge :status="ride.status" /></td>
              <td>
                <RouterLink
                  :to="
                    auth.driver
                      ? `/driver/rides/${ride.id}`
                      : `/rides/${ride.id}`
                  "
                  class="link text-xs"
                >
                  {{ $t('View route →') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </AsyncState>
    <Pagination :meta="meta" @page="load" />
  </section>
  <BaseModal
    v-if="modal"
    :title="$t('Create ride')"
    @close="!busy && (modal = false)"
  >
    <form class="space-y-5" @submit.prevent="save">
      <FormField
        v-if="!auth.driver"
        :label="$t('Driver')"
        required
        :error="errors.driver_id"
      >
        <select v-model="form.driver_id" class="field" required>
          <option :value="null" disabled>{{ $t('Select a driver') }}</option>
          <option v-for="d in drivers" :key="d.id" :value="d.id">
            {{ d.name }}
          </option>
        </select>
      </FormField>
      <p v-if="auth.driver" class="text-xs text-slate-500">
        {{ $t('This ride will be assigned to you.') }}
      </p>
      <FormField
        :label="$t('Starting storage location')"
        :error="errors.storage_location_id"
      >
        <select v-model="form.storage_location_id" class="field">
          <option :value="null">{{ $t('Select storage') }}</option>
          <option
            v-for="storage in storages"
            :key="storage.id"
            :value="storage.id"
          >
            {{ storage.name }}
          </option>
        </select>
      </FormField>
      <FormField :label="$t('Date')" required :error="errors.date">
        <input v-model="form.date" class="field" type="date" required />
      </FormField>
      <FormField :label="$t('Notes')" :error="errors.notes">
        <textarea v-model="form.notes" class="field" rows="3"></textarea>
      </FormField>
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
          {{ $t(busy ? 'Creating…' : 'Create ride') }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>
