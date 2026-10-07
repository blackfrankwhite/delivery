<script setup>
import { reactive, ref, computed } from 'vue';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import { api, errorMessage } from '../api';
import { useList } from '../composables/useList';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import BaseModal from '../components/BaseModal.vue';
import FormField from '../components/FormField.vue';
import Pagination from '../components/Pagination.vue';
import Icon from '../components/Icon.vue';
import AddressAutocomplete from '../components/AddressAutocomplete.vue';
import LocationMap from '../components/LocationMap.vue';
import { initials } from '../utils';
const props = defineProps({ kind: { type: String, required: true } }),
  auth = useAuth(),
  toasts = useToasts();
const account = computed(() =>
  ['drivers', 'operators', 'users'].includes(props.kind),
);
const singular = computed(
  () =>
    ({
      customers: 'customer',
      items: 'item',
      drivers: 'driver',
      operators: 'operator',
      users: 'user',
    })[props.kind],
);
const title = computed(() => props.kind[0].toUpperCase() + props.kind.slice(1));
const editable = computed(() => !account.value || auth.admin);
const descriptions = {
  customers:
    'Keep recurring recipients and their saved addresses close at hand.',
  items: 'Reusable products and packages for your order tasks.',
  drivers: 'Your team on the road, and their daily workload.',
  operators: 'The people keeping your orders moving.',
  users: 'Manage accounts and access to your workspace.',
};
const filters = reactive({ search: '', active: '' });
const { rows, meta, loading, error, load } = useList(
  () => `/api/${props.kind}`,
  filters,
);
const modal = ref(false),
  editing = ref(null),
  form = reactive({}),
  busy = ref(false),
  saveError = ref(''),
  errors = ref({});
const fields = computed(() =>
  account.value
    ? [
        ['name', 'Full name', 'text'],
        ['email', 'Email', 'email'],
        ['phone', 'Phone', 'tel'],
        [
          'password',
          editing.value
            ? 'New password (leave blank to keep current)'
            : 'Password',
          'password',
        ],
      ]
    : props.kind === 'customers'
      ? [
          ['name', 'Customer name', 'text'],
          ['phone', 'Phone', 'tel'],
          ['email', 'Email (optional)', 'email'],
          ['notes', 'Notes', 'textarea'],
        ]
      : [
          ['name', 'Item name', 'text'],
          ['sku', 'SKU (optional)', 'text'],
          ['description', 'Description', 'textarea'],
        ],
);
function open(record = null) {
  editing.value = record;
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(
    form,
    account.value
      ? {
          name: '',
          email: '',
          phone: '',
          password: '',
          role: props.kind === 'operators' ? 'operator' : 'driver',
          active: true,
        }
      : props.kind === 'customers'
        ? {
            name: '',
            phone: '',
            email: '',
            notes: '',
            active: true,
            default_address: {
              label: 'Default',
              address: '',
              city: '',
              entrance: '',
              floor: '',
              apartment: '',
              instructions: '',
              latitude: null,
              longitude: null,
              is_default: true,
            },
          }
        : { name: '', sku: '', description: '', active: true },
  );
  if (record)
    Object.keys(form).forEach((k) => {
      if (k !== 'password') form[k] = record[k] ?? form[k];
    });
  if (props.kind === 'customers' && record) {
    const address =
      record.addresses?.find((a) => a.is_default) || record.addresses?.[0];
    if (address)
      Object.assign(form.default_address, address, { is_default: true });
  }
  errors.value = {};
  saveError.value = '';
  modal.value = true;
}
async function save() {
  busy.value = true;
  saveError.value = '';
  errors.value = {};
  try {
    const path = account.value ? '/api/users' : `/api/${props.kind}`;
    if (editing.value) await api.put(`${path}/${editing.value.id}`, form);
    else await api.post(path, form);
    modal.value = false;
    toasts.show(
      `${singular.value[0].toUpperCase() + singular.value.slice(1)} saved`,
    );
    await load(meta.value?.current_page || 1);
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
    :title="$t(title)"
    :description="$t(descriptions[kind])"
    :eyebrow="$t('Workspace directory')"
  >
    <button v-if="editable" class="btn btn-primary" @click="open()">
      <Icon name="Plus" :size="17" />
      {{ $t('Create ' + singular) }}
    </button>
  </PageHeader>
  <section class="card">
    <form class="filter-form flex flex-wrap gap-3 p-5" @submit.prevent="load()">
      <div class="relative min-w-52 flex-1">
        <Icon
          name="Search"
          class="absolute top-3 left-3 text-slate-400"
          :size="16"
        />
        <input
          v-model="filters.search"
          class="field !pl-9"
          :placeholder="$t(`Search ${kind}…`)"
          :aria-label="$t(`Search ${kind}`)"
        />
      </div>
      <select
        v-model="filters.active"
        class="field !w-auto"
        :aria-label="$t('Active status')"
      >
        <option value="">{{ $t('All records') }}</option>
        <option value="1">{{ $t('Active') }}</option>
        <option value="0">{{ $t('Inactive') }}</option>
      </select>
      <button class="btn btn-secondary">{{ $t('Search') }}</button>
    </form>
    <AsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :message="$t(`No ${kind} found.`)"
      @retry="load"
    >
      <div class="divide-y divide-slate-100 md:hidden">
        <article v-for="row in rows" :key="row.id" class="space-y-3 p-4">
          <div class="flex items-start justify-between gap-3">
            <RouterLink
              v-if="kind === 'customers' || kind === 'drivers'"
              :to="`/${kind}/${row.id}`"
              class="min-w-0 break-words py-2 font-semibold text-slate-800"
            >
              {{ row.name }}
            </RouterLink>
            <h2 v-else class="min-w-0 break-words font-semibold">
              {{ row.name }}
            </h2>
            <span
              class="shrink-0 rounded-full bg-slate-50 px-2 py-1 text-xs"
              :class="row.active ? 'text-brand-700' : 'text-slate-400'"
            >
              {{ $t(row.active ? 'Active' : 'Inactive') }}
            </span>
          </div>
          <template v-if="kind === 'items'">
            <p class="text-xs text-slate-400">{{ row.sku }}</p>
            <p class="break-words text-sm text-slate-500">
              {{ row.description }}
            </p>
          </template>
          <template v-else>
            <a v-if="row.phone" :href="`tel:${row.phone}`" class="link text-sm">
              {{ row.phone }}
            </a>
            <p v-if="row.email" class="break-all text-xs text-slate-500">
              {{ row.email }}
            </p>
          </template>
          <p
            v-if="kind === 'customers'"
            class="break-words text-sm text-slate-600"
          >
            {{ row.addresses?.[0]?.address || '—' }}
          </p>
          <p v-if="account" class="text-xs text-slate-500">
            {{
              $t(row.role === 'superadministrator' ? 'Administrator' : row.role)
            }}
          </p>
          <p v-if="kind === 'drivers'" class="text-xs">
            {{ $t('Today’s orders') }}: {{ row.today_deliveries || 0 }}
          </p>
          <div class="flex gap-2">
            <button
              v-if="editable"
              class="btn btn-secondary flex-1"
              @click="open(row)"
            >
              {{ $t('Edit') }}
            </button>
            <RouterLink
              v-if="kind === 'customers' || kind === 'drivers'"
              :to="`/${kind}/${row.id}`"
              class="btn btn-secondary flex-1"
            >
              {{ $t('View →') }}
            </RouterLink>
          </div>
        </article>
      </div>
      <div class="table-wrap hidden md:block">
        <table class="data-table">
          <thead>
            <tr>
              <th>
                {{
                  $t(
                    account
                      ? 'Team member'
                      : kind === 'items'
                        ? 'Item'
                        : 'Customer',
                  )
                }}
              </th>
              <th v-if="kind === 'items'">{{ $t('SKU') }}</th>
              <th v-else>{{ $t('Contact') }}</th>
              <th v-if="kind === 'customers'">{{ $t('Address') }}</th>
              <th v-if="account">{{ $t('Role') }}</th>
              <th v-if="kind === 'drivers'">{{ $t('Today’s orders') }}</th>
              <th>{{ $t('Status') }}</th>
              <th>{{ $t('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="flex items-center gap-3">
                  <span
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700"
                  >
                    {{ initials(row.name) }}
                  </span>
                  <div>
                    <RouterLink
                      v-if="kind === 'customers' || kind === 'drivers'"
                      :to="`/${kind}/${row.id}`"
                      class="font-medium text-slate-800 hover:text-brand-600"
                    >
                      {{ row.name }}
                    </RouterLink>
                    <span v-else class="font-medium text-slate-800">
                      {{ row.name }}
                    </span>
                    <p
                      v-if="kind === 'items'"
                      class="mt-1 max-w-60 truncate text-xs text-slate-400"
                    >
                      {{ row.description }}
                    </p>
                  </div>
                </div>
              </td>
              <td v-if="kind === 'items'" class="text-xs text-slate-500">
                {{ row.sku || '—' }}
              </td>
              <td v-else>
                <p class="text-xs">{{ row.phone || '—' }}</p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ row.email }}
                </p>
              </td>
              <td v-if="kind === 'customers'" class="text-xs">
                {{ row.addresses?.[0]?.address || '—' }}
              </td>
              <td v-if="account" class="text-xs capitalize">
                {{
                  $t(
                    row.role === 'superadministrator'
                      ? 'Administrator'
                      : row.role,
                  )
                }}
              </td>
              <td v-if="kind === 'drivers'">
                <span
                  class="rounded-lg bg-slate-50 px-3 py-1 text-xs font-semibold"
                >
                  {{ row.today_deliveries || 0 }} {{ $t('orders') }}
                </span>
              </td>
              <td>
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
                  :class="
                    row.active
                      ? 'bg-brand-50 text-brand-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                  {{ $t(row.active ? 'Active' : 'Inactive') }}
                </span>
              </td>
              <td>
                <button v-if="editable" class="link text-xs" @click="open(row)">
                  {{ $t('Edit') }}
                </button>
                <RouterLink
                  v-else-if="kind === 'drivers'"
                  :to="`/drivers/${row.id}`"
                  class="link text-xs"
                >
                  {{ $t('View →') }}
                </RouterLink>
                <span v-else class="text-slate-300">—</span>
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
    :title="$t(`${editing ? 'Edit' : 'Create'} ${singular}`)"
    @close="!busy && (modal = false)"
  >
    <form class="space-y-4" @submit.prevent="save">
      <FormField
        v-for="[key, label, type] in fields"
        :key="key"
        :label="$t(label)"
        :error="errors[key]"
      >
        <textarea
          v-if="type === 'textarea'"
          v-model="form[key]"
          class="field"
          rows="3"
        ></textarea>
        <input
          v-else
          v-model="form[key]"
          class="field"
          :type="type"
          :required="
            key === 'name' ||
            (key === 'phone' && kind === 'customers') ||
            (key === 'email' && account) ||
            (key === 'password' && account && !editing)
          "
          :minlength="key === 'password' ? 8 : undefined"
          :autocomplete="key === 'password' ? 'new-password' : undefined"
        />
      </FormField>
      <section
        v-if="kind === 'customers'"
        class="space-y-4 border-t border-slate-100 pt-4"
      >
        <h3 class="font-semibold">{{ $t('Default address') }}</h3>
        <AddressAutocomplete
          v-model="form.default_address.address"
          :city="form.default_address.city"
          :label="$t('Address')"
          required
          :error="errors['default_address.address']"
          @select="
            (suggestion) =>
              Object.assign(form.default_address, {
                latitude: suggestion.latitude,
                longitude: suggestion.longitude,
                city: suggestion.city || '',
              })
          "
        />
        <div class="grid grid-cols-2 gap-4">
          <FormField
            v-for="[key, label] in [
              ['label', 'Label'],
              ['city', 'City'],
              ['entrance', 'Entrance'],
              ['floor', 'Floor'],
              ['apartment', 'Apartment'],
            ]"
            :key="key"
            :label="$t(label)"
            :error="errors['default_address.' + key]"
          >
            <input
              v-model="form.default_address[key]"
              class="field"
              :required="key === 'label'"
            />
          </FormField>
        </div>
        <FormField
          :label="$t('Instructions')"
          :error="errors['default_address.instructions']"
        >
          <textarea
            v-model="form.default_address.instructions"
            class="field"
            rows="2"
          ></textarea>
        </FormField>
        <LocationMap
          v-model:latitude="form.default_address.latitude"
          v-model:longitude="form.default_address.longitude"
          editable
          :label="$t('Saved address location picker')"
        />
        <p
          v-if="
            errors['default_address.latitude'] ||
            errors['default_address.longitude']
          "
          class="text-xs text-red-600"
        >
          {{
            (errors['default_address.latitude'] ||
              errors['default_address.longitude'])[0]
          }}
        </p>
      </section>
      <FormField v-if="account" :label="$t('Role')" :error="errors.role">
        <select v-model="form.role" class="field">
          <option value="driver">{{ $t('Driver') }}</option>
          <option value="operator">{{ $t('Operator') }}</option>
          <option value="superadministrator">
            {{ $t('Superadministrator') }}
          </option>
        </select>
      </FormField>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.active" type="checkbox" class="accent-brand-600" />
        {{ $t('Active account / record') }}
      </label>
      <p class="text-xs text-slate-400">
        {{ $t('Deactivate records to preserve their order history.') }}
      </p>
      <p v-if="errors.active" class="text-xs text-red-600">
        {{ $t(errors.active[0]) }}
      </p>
      <p v-if="saveError" class="text-sm text-red-600" role="alert">
        {{ $t(saveError) }}
      </p>
      <div class="flex justify-end gap-3 pt-3">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="modal = false"
        >
          {{ $t('Cancel') }}
        </button>
        <button class="btn btn-primary" :disabled="busy">
          {{ $t(busy ? 'Saving…' : 'Save ' + singular) }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>
