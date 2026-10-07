<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '../api';

import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import DeliveryTable from '../components/DeliveryTable.vue';
import StatusBadge from '../components/StatusBadge.vue';
const route = useRoute(),
  driver = ref(null),
  loading = ref(true),
  error = ref('');
async function load() {
  loading.value = true;
  error.value = '';
  try {
    driver.value = (await api.get(`/api/drivers/${route.params.id}`)).data.data;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
const todays = computed(() => driver.value?.today_tasks || []);
</script>
<template>
  <PageHeader
    :title="$t(driver?.name || 'Driver details')"
    :description="$t('Today’s assignments and recent order activity.')"
    :eyebrow="$t('Driver')"
  >
    <RouterLink class="btn btn-secondary" to="/drivers">
      {{ $t('All drivers') }}
    </RouterLink>
  </PageHeader>
  <AsyncState :loading="loading" :error="error" @retry="load">
    <div class="card mb-6 flex flex-wrap gap-8 p-6">
      <div>
        <p class="label">{{ $t('Phone') }}</p>
        <a :href="`tel:${driver.phone}`" class="link">
          {{ driver.phone || '—' }}
        </a>
      </div>
      <div>
        <p class="label">{{ $t('Email') }}</p>
        <span>{{ driver.email }}</span>
      </div>
      <div>
        <p class="label">{{ $t('Status') }}</p>
        <span>{{ $t(driver.active ? 'Active' : 'Inactive') }}</span>
      </div>
    </div>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="ride in driver.rides"
        :key="ride.id"
        :to="`/rides/${ride.id}`"
        class="card flex items-center justify-between p-5"
      >
        <div>
          <p class="font-semibold">{{ $t('Ride #{id}', { id: ride.id }) }}</p>
          <p class="mt-1 text-xs text-slate-400">
            {{ $t('{count} stops today', { count: ride.tasks_count }) }}
          </p>
        </div>
        <StatusBadge :status="ride.status" />
      </RouterLink>
    </div>
    <section class="card mb-6">
      <h2 class="p-5 font-semibold">{{ $t('Today’s assignments') }}</h2>
      <AsyncState
        :empty="!todays.length"
        :message="$t('No orders assigned for today.')"
      >
        <DeliveryTable :tasks="todays" compact />
      </AsyncState>
    </section>
    <section class="card">
      <div class="flex items-center justify-between p-5">
        <h2 class="font-semibold">{{ $t('Recent orders') }}</h2>
        <RouterLink :to="`/orders?driver_id=${driver.id}`" class="link text-xs">
          {{ $t('View all →') }}
        </RouterLink>
      </div>
      <AsyncState
        :empty="!driver.deliveries.length"
        :message="$t('No recent orders.')"
      >
        <DeliveryTable :tasks="driver.deliveries" compact />
      </AsyncState>
    </section>
  </AsyncState>
</template>
