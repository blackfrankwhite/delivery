<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '../api';
import { useToasts } from '../stores/toasts';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import BaseModal from '../components/BaseModal.vue';
import FormField from '../components/FormField.vue';
import Icon from '../components/Icon.vue';
import LocationMap from '../components/LocationMap.vue';
import AddressAutocomplete from '../components/AddressAutocomplete.vue';
import DeliveryTable from '../components/DeliveryTable.vue';
const route = useRoute(),
  toasts = useToasts(),
  customer = ref(null),
  loading = ref(true),
  error = ref(''),
  modal = ref(false),
  editing = ref(null),
  busy = ref(false),
  saveError = ref(''),
  errors = ref({}),
  form = reactive({});
async function load() {
  loading.value = true;
  error.value = '';
  try {
    customer.value = (
      await api.get(`/api/customers/${route.params.id}`)
    ).data.data;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
function open(address = null) {
  editing.value = address;
  Object.assign(
    form,
    {
      label: '',
      address: '',
      city: '',
      latitude: null,
      longitude: null,
      entrance: '',
      floor: '',
      apartment: '',
      instructions: '',
      is_default: true,
    },
    address || {},
  );
  errors.value = {};
  saveError.value = '';
  modal.value = true;
}
function selectSuggestion(suggestion) {
  form.latitude = suggestion.latitude;
  form.longitude = suggestion.longitude;
  form.city = suggestion.city || '';
}
async function save() {
  busy.value = true;
  errors.value = {};
  saveError.value = '';
  try {
    const path = `/api/customers/${customer.value.id}/addresses`;
    const payload = {
      ...form,
      latitude: form.latitude === '' ? null : form.latitude,
      longitude: form.longitude === '' ? null : form.longitude,
    };
    if (editing.value) await api.put(`${path}/${editing.value.id}`, payload);
    else await api.post(path, payload);
    modal.value = false;
    toasts.show('Address saved');
    await load();
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
    :title="$t(customer?.name || 'Customer details')"
    :description="
      $t('Contact details, saved destinations, and recent order history.')
    "
    :eyebrow="$t('Customer')"
  >
    <RouterLink to="/customers" class="btn btn-secondary">
      {{ $t('All customers') }}
    </RouterLink>
    <RouterLink
      v-if="customer?.active"
      :to="`/orders/create?customer_id=${customer.id}`"
      class="btn btn-primary"
    >
      <Icon name="Plus" :size="16" />
      {{ $t('Create order') }}
    </RouterLink>
  </PageHeader>
  <AsyncState :loading="loading" :error="error" @retry="load">
    <section class="card mb-6 flex flex-wrap gap-8 p-6">
      <div>
        <p class="label">{{ $t('Phone') }}</p>
        <a :href="`tel:${customer.phone}`" class="link">{{ customer.phone }}</a>
      </div>
      <div>
        <p class="label">{{ $t('Email') }}</p>
        <a
          v-if="customer.email"
          :href="`mailto:${customer.email}`"
          class="link"
        >
          {{ customer.email }}
        </a>
        <span v-else>—</span>
      </div>
      <div>
        <p class="label">{{ $t('Status') }}</p>
        <span :class="customer.active ? 'text-brand-600' : 'text-slate-400'">
          {{ $t(customer.active ? 'Active' : 'Inactive') }}
        </span>
      </div>
      <p
        v-if="customer.notes"
        class="w-full whitespace-pre-wrap text-sm text-slate-500"
      >
        {{ customer.notes }}
      </p>
    </section>
    <section class="mb-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-base font-semibold">{{ $t('Address') }}</h2>
        <button
          v-if="!customer.addresses.length"
          class="btn btn-secondary"
          @click="open()"
        >
          <Icon name="Plus" :size="16" />
          {{ $t('Add address') }}
        </button>
      </div>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="a in customer.addresses" :key="a.id" class="card p-5">
          <div class="mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2 font-semibold">
              <Icon name="MapPin" class="text-brand-600" />
              {{ a.label }}
            </span>
          </div>
          <p class="font-medium text-slate-700">{{ a.address }}</p>
          <p class="mt-1 text-xs text-slate-400">{{ a.city }}</p>
          <p class="mt-3 text-xs text-slate-500">
            {{
              [
                a.entrance && $t('Entrance') + ' ' + a.entrance,
                a.floor && $t('Floor') + ' ' + a.floor,
                a.apartment && $t('Apartment') + ' ' + a.apartment,
              ]
                .filter(Boolean)
                .join(' · ')
            }}
          </p>
          <p
            v-if="a.instructions"
            class="mt-3 whitespace-pre-wrap text-xs text-slate-500"
          >
            {{ a.instructions }}
          </p>
          <div class="mt-5 flex gap-4 border-t border-slate-100 pt-3">
            <button class="link text-xs" @click="open(a)">
              {{ $t('Edit address') }}
            </button>
          </div>
        </div>
      </div>
      <div
        v-if="!customer.addresses.length"
        class="card p-8 text-center text-slate-400"
      >
        {{ $t('No saved addresses yet.') }}
      </div>
    </section>
    <section class="card">
      <div class="flex items-center justify-between p-5">
        <h2 class="font-semibold">{{ $t('Recent orders') }}</h2>
        <RouterLink
          :to="`/orders?customer_id=${customer.id}`"
          class="link text-xs"
        >
          {{ $t('Browse orders →') }}
        </RouterLink>
      </div>
      <AsyncState
        :empty="!customer.deliveries.length"
        :message="$t('No previous orders for this customer.')"
      >
        <DeliveryTable :tasks="customer.deliveries" compact />
      </AsyncState>
    </section>
  </AsyncState>
  <BaseModal
    v-if="modal"
    :title="$t(editing ? 'Edit saved address' : 'Add saved address')"
    @close="!busy && (modal = false)"
  >
    <form class="grid grid-cols-2 gap-4" @submit.prevent="save">
      <FormField :label="$t('Label')" class="col-span-2" :error="errors.label">
        <input
          v-model="form.label"
          class="field"
          required
          :placeholder="$t('Home, office, warehouse…')"
        />
      </FormField>
      <AddressAutocomplete
        v-model="form.address"
        :city="form.city"
        :label="$t('Address')"
        class="col-span-2"
        required
        :error="errors.address"
        @select="selectSuggestion"
      />
      <FormField
        v-for="[key, label] in [
          ['city', 'City'],
          ['entrance', 'Entrance'],
          ['floor', 'Floor'],
          ['apartment', 'Apartment'],
        ]"
        :key="key"
        :label="$t(label)"
        :error="errors[key]"
      >
        <input v-model="form[key]" class="field" />
      </FormField>
      <div class="col-span-2">
        <h3 class="mb-3 text-sm font-semibold">
          {{ $t('Saved address on the map') }}
        </h3>
        <LocationMap
          v-model:latitude="form.latitude"
          v-model:longitude="form.longitude"
          editable
          :label="$t('Saved address location picker')"
        />
        <p
          v-if="errors.latitude || errors.longitude"
          class="mt-2 text-xs text-red-600"
          role="alert"
        >
          {{ $t((errors.latitude || errors.longitude)[0]) }}
        </p>
        <details class="mt-4">
          <summary class="cursor-pointer text-xs text-slate-500">
            {{ $t('Enter coordinates manually (optional)') }}
          </summary>
          <div class="mt-3 grid grid-cols-2 gap-4">
            <FormField
              v-for="[key, label] in [
                ['latitude', 'Latitude'],
                ['longitude', 'Longitude'],
              ]"
              :key="key"
              :label="$t(label)"
              :error="errors[key]"
            >
              <input
                v-model.number="form[key]"
                class="field"
                type="number"
                step="any"
                :min="key === 'latitude' ? -90 : -180"
                :max="key === 'latitude' ? 90 : 180"
              />
            </FormField>
          </div>
        </details>
      </div>
      <FormField
        :label="$t('Instructions')"
        class="col-span-2"
        :error="errors.instructions"
      >
        <textarea v-model="form.instructions" class="field" rows="2"></textarea>
      </FormField>

      <p v-if="saveError" class="col-span-2 text-sm text-red-600" role="alert">
        {{ $t(saveError) }}
      </p>
      <div class="col-span-2 flex justify-end gap-3">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="modal = false"
        >
          {{ $t('Cancel') }}
        </button>
        <button class="btn btn-primary" :disabled="busy">
          {{ $t(busy ? 'Saving…' : 'Save address') }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>
