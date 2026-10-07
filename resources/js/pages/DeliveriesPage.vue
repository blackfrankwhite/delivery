<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuth } from '../stores/auth';
import { allPages, errorMessage } from '../api';
import { useList } from '../composables/useList';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import Icon from '../components/Icon.vue';
import DeliveryTable from '../components/DeliveryTable.vue';
import Pagination from '../components/Pagination.vue';
import { statuses } from '../utils';
const route = useRoute(),
  auth = useAuth(),
  filters = reactive({
    search: '',
    date: '',
    status: '',
    driver_id: route.query.driver_id || '',
    customer_id: route.query.customer_id || '',
    priority: '',
    date_from: '',
    date_to: '',
  }),
  drivers = ref([]),
  customers = ref([]),
  lookupError = ref('');
const filtersOpen = ref(false);
const { rows, meta, loading, error, load } = useList('/api/orders', filters);
onMounted(async () => {
  if (!auth.driver)
    try {
      [drivers.value, customers.value] = await Promise.all([
        allPages('/api/drivers'),
        allPages('/api/customers'),
      ]);
    } catch (e) {
      lookupError.value = errorMessage(e);
    }
});
function clear() {
  Object.keys(filters).forEach((k) => (filters[k] = ''));
  load();
}
</script>
<template>
  <PageHeader
    :title="$t(auth.driver ? 'My orders' : 'Orders')"
    :description="
      $t(
        auth.driver
          ? 'All of your assigned orders, in one place.'
          : 'Create, assign, and follow every order from start to finish.',
      )
    "
    :eyebrow="$t('Order management')"
  >
    <button
      class="btn btn-secondary"
      :disabled="loading"
      @click="load(meta?.current_page || 1)"
    >
      <Icon name="RefreshCw" :size="15" />
      {{ $t('Refresh') }}
    </button>
    <RouterLink v-if="!auth.driver" class="btn btn-primary" to="/orders/create">
      <Icon name="Plus" :size="17" />
      {{ $t('Create order') }}
    </RouterLink>
  </PageHeader>
  <section class="card">
    <div class="border-b border-slate-100 p-4 md:hidden">
      <form class="mb-3 flex gap-2" @submit.prevent="load()">
        <input
          v-model="filters.search"
          class="field min-w-0"
          :placeholder="$t('Reference, recipient, phone, address…')"
          :aria-label="$t('Search orders')"
        />
        <button
          class="btn btn-secondary"
          :disabled="loading"
          :aria-label="$t('Search')"
        >
          <Icon name="Search" :size="18" />
        </button>
      </form>
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
        class="filter-form flex flex-wrap items-end gap-3 p-5"
        @submit.prevent="
          load();
          filtersOpen = false;
        "
      >
        <label class="min-w-[220px] flex-1">
          <span class="label">{{ $t('Search orders') }}</span>
          <div class="relative">
            <Icon
              name="Search"
              class="absolute top-3 left-3 text-slate-400"
              :size="16"
            />
            <input
              v-model="filters.search"
              class="field !pl-9"
              :placeholder="$t('Reference, recipient, phone, address…')"
            />
          </div>
        </label>
        <label>
          <span class="label">{{ $t('Order date') }}</span>
          <input v-model="filters.date" class="field" type="date" />
        </label>
        <label>
          <span class="label">{{ $t('Status') }}</span>
          <select v-model="filters.status" class="field">
            <option value="">{{ $t('All statuses') }}</option>
            <option v-for="status in statuses" :key="status" :value="status">
              {{ $t(status) }}
            </option>
          </select>
        </label>
        <label v-if="!auth.driver">
          <span class="label">{{ $t('Driver') }}</span>
          <select v-model="filters.driver_id" class="field max-w-44">
            <option value="">{{ $t('All drivers') }}</option>
            <option v-for="d in drivers" :key="d.id" :value="d.id">
              {{ d.name }}
            </option>
          </select>
        </label>
        <label v-if="!auth.driver">
          <span class="label">{{ $t('Customer') }}</span>
          <select v-model="filters.customer_id" class="field max-w-44">
            <option value="">{{ $t('All customers') }}</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </label>
        <button class="btn btn-primary" :disabled="loading">
          {{ $t('Apply') }}
        </button>
        <button type="button" class="btn btn-secondary" @click="clear">
          {{ $t('Reset') }}
        </button>
        <details class="w-full">
          <summary class="cursor-pointer text-xs text-slate-500">
            {{ $t('More filters') }}
          </summary>
          <div class="mt-3 flex flex-wrap gap-3">
            <label>
              <span class="label">{{ $t('From') }}</span>
              <input v-model="filters.date_from" class="field" type="date" />
            </label>
            <label>
              <span class="label">{{ $t('To') }}</span>
              <input v-model="filters.date_to" class="field" type="date" />
            </label>
            <label>
              <span class="label">{{ $t('Priority') }}</span>
              <select v-model="filters.priority" class="field">
                <option value="">{{ $t('All priorities') }}</option>
                <option
                  v-for="p in ['low', 'normal', 'high', 'urgent']"
                  :key="p"
                  :value="p"
                >
                  {{ $t(p) }}
                </option>
              </select>
            </label>
          </div>
        </details>
        <p v-if="lookupError" class="w-full text-xs text-red-600">
          {{ $t(lookupError) }}
        </p>
      </form>
    </div>
    <AsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :message="$t('No orders match these filters.')"
      @retry="load"
    >
      <DeliveryTable :tasks="rows" :driver="auth.driver" />
    </AsyncState>
    <Pagination :meta="meta" @page="load" />
  </section>
</template>
