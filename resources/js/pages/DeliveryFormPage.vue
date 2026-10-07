<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, allPages, errorMessage } from '../api';
import { useToasts } from '../stores/toasts';
import { today } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import FormField from '../components/FormField.vue';
import AsyncState from '../components/AsyncState.vue';
import Icon from '../components/Icon.vue';
import LocationMap from '../components/LocationMap.vue';
import CreateClientModal from '../components/CreateClientModal.vue';
const route = useRoute(),
  router = useRouter(),
  toasts = useToasts(),
  edit = !!route.params.id;
const clientModal = ref(false);
const loading = ref(true),
  loadError = ref(''),
  saving = ref(false),
  error = ref(''),
  errors = ref({}),
  customers = ref([]),
  items = ref([]),
  drivers = ref([]),
  addresses = ref([]);
const form = reactive({
  customer_id: null,
  customer_address_id: null,
  driver_id: null,
  recipient_name: '',
  recipient_phone: '',
  address: '',
  city: '',
  entrance: '',
  floor: '',
  apartment: '',
  latitude: null,
  longitude: null,
  date_from: today(),
  date_to: today(),
  time_from: '09:00',
  time_to: '18:00',
  priority: 'normal',
  notes: '',
  items: [],
});
async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    [customers.value, items.value, drivers.value] = await Promise.all([
      allPages('/api/customers'),
      allPages('/api/items'),
      allPages('/api/drivers'),
    ]);
    if (edit) {
      const task = (await api.get(`/api/orders/${route.params.id}`)).data.data;
      Object.keys(form).forEach((k) => (form[k] = task[k] ?? form[k]));
      form.time_from = task.time_from?.slice(0, 5) || '09:00';
      form.time_to = task.time_to?.slice(0, 5) || '18:00';
      form.items = task.items.map((i) => ({
        item_id: i.item_id,
        quantity: i.quantity,
        notes: i.notes || '',
      }));
      addresses.value =
        customers.value.find((c) => c.id === task.customer_id)?.addresses || [];
      form.customer_address_id = addresses.value[0]?.id || null;
      const savedNotes = form.notes;
      selectAddress();
      form.notes = savedNotes;
      const client = customers.value.find((c) => c.id === task.customer_id);
      if (client) {
        form.recipient_name = client.name;
        form.recipient_phone = client.phone;
      }
    } else if (route.query.customer_id) {
      form.customer_id = Number(route.query.customer_id);
      selectCustomer();
    }
  } catch (e) {
    loadError.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
function selectCustomer() {
  const c = customers.value.find((c) => c.id === form.customer_id);
  addresses.value = c?.addresses || [];
  form.customer_address_id = null;
  for (const field of [
    'address',
    'city',
    'entrance',
    'floor',
    'apartment',
    'notes',
  ])
    form[field] = '';
  form.latitude = null;
  form.longitude = null;
  if (c) {
    form.recipient_name = c.name;
    form.recipient_phone = c.phone;
    const def = addresses.value.find((a) => a.is_default) || addresses.value[0];
    if (def) {
      form.customer_address_id = def.id;
      selectAddress();
    }
  }
}
function selectAddress() {
  const a = addresses.value.find((a) => a.id === form.customer_address_id);
  if (a) {
    for (const f of [
      'address',
      'city',
      'latitude',
      'longitude',
      'entrance',
      'floor',
      'apartment',
    ])
      form[f] = a[f] ?? '';
    form.notes = a.instructions || '';
  } else {
    form.latitude = null;
    form.longitude = null;
  }
}
function clientCreated(client) {
  customers.value.push(client);
  form.customer_id = client.id;
  selectCustomer();
  clientModal.value = false;
  toasts.show('Customer saved');
}
async function save() {
  saving.value = true;
  errors.value = {};
  error.value = '';
  try {
    const payload = {
      ...Object.fromEntries(
        Object.entries(form).filter(
          ([key]) =>
            ![
              'recipient_name',
              'recipient_phone',
              'address',
              'city',
              'latitude',
              'longitude',
              'entrance',
              'floor',
              'apartment',
            ].includes(key),
        ),
      ),
      time_from: form.time_from || null,
      time_to: form.time_to || null,
    };
    const { data } = edit
      ? await api.put(`/api/orders/${route.params.id}`, payload)
      : await api.post('/api/orders', payload);
    toasts.show(edit ? 'Order updated' : 'Order created');
    await router.push(`/orders/${data.data.id}`);
  } catch (e) {
    errors.value = e.response?.data.errors || {};
    error.value = errorMessage(e);
  } finally {
    saving.value = false;
  }
}
</script>
<template>
  <PageHeader
    :title="$t(edit ? 'Edit order' : 'Create order')"
    :description="
      $t('Choose a client and use their saved address for this order.')
    "
    :eyebrow="$t('Order management')"
  >
    <RouterLink
      class="btn btn-secondary"
      :to="edit ? `/orders/${route.params.id}` : '/orders'"
    >
      {{ $t('Cancel') }}
    </RouterLink>
  </PageHeader>
  <AsyncState :loading="loading" :error="loadError" @retry="load">
    <form class="mx-auto max-w-5xl space-y-6" @submit.prevent="save">
      <section class="card p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-semibold">{{ $t('Recipient & destination') }}</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{
                $t(
                  'Select an existing client or create a new one. The address comes from the client’s saved addresses.',
                )
              }}
            </p>
          </div>
          <button
            type="button"
            class="btn btn-secondary"
            @click="clientModal = true"
          >
            <Icon name="Plus" :size="16" />
            {{ $t('Create customer') }}
          </button>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
          <FormField
            :label="$t('Customer')"
            required
            :error="errors.customer_id"
          >
            <select
              v-model="form.customer_id"
              class="field"
              required
              @change="selectCustomer"
            >
              <option :value="null" disabled>{{ $t('Select client') }}</option>
              <option
                v-for="c in customers.filter(
                  (c) => c.active || c.id === form.customer_id,
                )"
                :key="c.id"
                :value="c.id"
              >
                {{ c.name }}
              </option>
            </select>
          </FormField>
          <p
            v-if="errors.customer_address_id"
            class="text-sm text-red-600"
            role="alert"
          >
            {{ $t(errors.customer_address_id[0]) }}
          </p>
        </div>
        <div
          v-if="form.customer_address_id"
          class="mt-5 rounded-xl bg-slate-50 p-4"
        >
          <p class="font-semibold">{{ form.recipient_name }}</p>
          <p class="mt-1 text-sm">{{ form.recipient_phone }}</p>
          <p class="mt-3 text-sm">
            {{ form.address }}
            <span v-if="form.city">· {{ form.city }}</span>
          </p>
          <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
            <div
              v-for="[key, label] in [
                ['entrance', 'Entrance'],
                ['floor', 'Floor'],
                ['apartment', 'Apartment'],
              ]"
              :key="key"
            >
              <dt class="text-xs text-slate-500">{{ $t(label) }}</dt>
              <dd>{{ form[key] || '—' }}</dd>
            </div>
          </dl>
          <LocationMap
            class="mt-4"
            :latitude="form.latitude"
            :longitude="form.longitude"
            :label="$t('Destination on the map')"
          />
        </div>
        <p
          v-if="form.customer_id && !addresses.length"
          class="mt-4 text-sm text-amber-700"
        >
          {{
            $t('This client needs a saved address before creating an order.')
          }}
        </p>
        <RouterLink
          v-if="form.customer_id"
          :to="`/customers/${form.customer_id}`"
          class="link mt-4 inline-block text-xs"
        >
          {{ $t('Edit client address') }}
        </RouterLink>
      </section>
      <section class="card p-6">
        <h2 class="mb-2 font-semibold">{{ $t('Schedule & assignment') }}</h2>
        <p class="mb-6 text-xs text-slate-500">
          {{
            $t(
              'This order is available for a ride on any date in this range. Hours default to 09:00–18:00 and can be changed.',
            )
          }}
        </p>
        <div class="grid gap-5 sm:grid-cols-2">
          <FormField
            :label="$t('Date from')"
            required
            :error="errors.date_from"
          >
            <input
              v-model="form.date_from"
              class="field"
              type="date"
              required
            />
          </FormField>
          <FormField :label="$t('Date to')" required :error="errors.date_to">
            <input
              v-model="form.date_to"
              class="field"
              type="date"
              :min="form.date_from"
              required
            />
          </FormField>
          <FormField :label="$t('Time from')" :error="errors.time_from">
            <input v-model="form.time_from" class="field" type="time" />
          </FormField>
          <FormField :label="$t('Time to')" :error="errors.time_to">
            <input v-model="form.time_to" class="field" type="time" />
          </FormField>
          <FormField :label="$t('Driver (optional)')" :error="errors.driver_id">
            <select v-model="form.driver_id" class="field">
              <option :value="null">{{ $t('Leave unassigned') }}</option>
              <option
                v-for="d in drivers.filter(
                  (d) => d.active || d.id === form.driver_id,
                )"
                :key="d.id"
                :value="d.id"
              >
                {{ d.name }}
              </option>
            </select>
          </FormField>
          <FormField :label="$t('Priority')" :error="errors.priority">
            <select v-model="form.priority" class="field">
              <option
                v-for="p in ['low', 'normal', 'high', 'urgent']"
                :key="p"
                :value="p"
              >
                {{ $t(p) }}
              </option>
            </select>
          </FormField>
          <FormField
            :label="$t('Order instructions')"
            :error="errors.notes"
            class="sm:col-span-2"
          >
            <textarea
              v-model="form.notes"
              class="field"
              rows="3"
              :placeholder="
                $t(
                  'Access information, handling instructions, or anything the driver should know.',
                )
              "
            ></textarea>
          </FormField>
        </div>
      </section>
      <section class="card p-6">
        <div class="mb-5 flex items-center justify-between">
          <div>
            <h2 class="font-semibold">{{ $t('Order items') }}</h2>
            <p class="mt-1 text-xs text-slate-400">
              {{ $t('Add products and quantities for this stop.') }}
            </p>
          </div>
          <button
            type="button"
            class="btn btn-secondary"
            @click="
              form.items.push({
                item_id: null,
                quantity: 1,
                notes: '',
              })
            "
          >
            <Icon name="Plus" :size="16" />
            {{ $t('Add item') }}
          </button>
        </div>
        <p
          v-if="!form.items.length"
          class="rounded-lg bg-slate-50 p-5 text-center text-xs text-slate-400"
        >
          {{ $t('No items added yet.') }}
        </p>
        <div
          v-for="(row, i) in form.items"
          :key="i"
          class="mb-4 grid items-start gap-3 sm:grid-cols-[2fr_1fr_2fr_auto]"
        >
          <FormField :label="$t('Item')" :error="errors[`items.${i}.item_id`]">
            <select v-model="row.item_id" class="field" required>
              <option :value="null" disabled>{{ $t('Select item') }}</option>
              <option
                v-for="item in items.filter(
                  (item) => item.active || item.id === row.item_id,
                )"
                :key="item.id"
                :value="item.id"
              >
                {{ item.name }}
              </option>
            </select>
          </FormField>
          <FormField
            :label="$t('Quantity')"
            :error="errors[`items.${i}.quantity`]"
          >
            <input
              v-model.number="row.quantity"
              class="field"
              type="number"
              min="1"
              max="100000"
              required
            />
          </FormField>
          <FormField :label="$t('Notes')" :error="errors[`items.${i}.notes`]">
            <input v-model="row.notes" class="field" />
          </FormField>
          <button
            type="button"
            class="btn btn-secondary sm:mt-5"
            :aria-label="$t('Remove item')"
            @click="form.items.splice(i, 1)"
          >
            <Icon name="Trash2" :size="16" />
          </button>
        </div>
        <p v-if="errors.items" class="text-xs text-red-600">
          {{ $t(errors.items[0]) }}
        </p>
      </section>
      <p v-if="error" class="text-sm text-red-600" role="alert">
        {{ $t(error) }}
      </p>
      <div
        class="flex flex-wrap justify-end gap-3 [&>.btn]:flex-1 sm:[&>.btn]:flex-none"
      >
        <RouterLink class="btn btn-secondary" to="/orders">
          {{ $t('Cancel') }}
        </RouterLink>
        <button
          class="btn btn-primary"
          :disabled="saving || !form.customer_id || !form.customer_address_id"
        >
          {{ $t(saving ? 'Saving…' : edit ? 'Save changes' : 'Create order') }}
          <Icon name="ArrowRight" :size="16" />
        </button>
      </div>
    </form>
  </AsyncState>
  <CreateClientModal
    v-if="clientModal"
    @close="clientModal = false"
    @created="clientCreated"
  />
</template>
