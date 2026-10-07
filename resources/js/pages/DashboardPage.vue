<script setup>
import { ref, onMounted, computed } from 'vue';
import { api, errorMessage } from '../api';
import { useAuth } from '../stores/auth';
import PageHeader from '../components/PageHeader.vue';
import Icon from '../components/Icon.vue';
import AsyncState from '../components/AsyncState.vue';
import DeliveryTable from '../components/DeliveryTable.vue';
import { dateLabel } from '../utils';
const data = ref(null),
  loading = ref(true),
  error = ref(''),
  auth = useAuth();
async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = (await api.get('/api/dashboard')).data.data;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
const cards = [
  ['total', "Today's orders", 'Package', 'text-brand-600 bg-brand-50'],
  ['pending', 'Pending', 'Clock3', 'text-slate-500 bg-slate-100'],
  ['assigned', 'Assigned', 'UserRoundCheck', 'text-blue-600 bg-blue-50'],
  ['in_progress', 'In progress', 'Truck', 'text-amber-600 bg-amber-50'],
  ['delivered', 'Delivered', 'CircleCheck', 'text-emerald-600 bg-emerald-50'],
  ['failed', 'Failed', 'CircleAlert', 'text-red-600 bg-red-50'],
  ['active_drivers', 'Active drivers', 'Users', 'text-violet-600 bg-violet-50'],
];
const progress = computed(() =>
  data.value?.total
    ? Math.round((data.value.delivered / data.value.total) * 100)
    : 0,
);
</script>
<template>
  <PageHeader
    :title="$t('Operations overview')"
    :description="
      $t(
        `Welcome back, ${auth.user?.name.split(' ')[0]}. Here’s how your day is moving.`,
      )
    "
    :eyebrow="$t('Daily dispatch')"
  >
    <button class="btn btn-secondary" :disabled="loading" @click="load">
      <Icon name="RefreshCw" :size="15" />
      {{ $t('Refresh') }}
    </button>
    <RouterLink class="btn btn-primary" to="/orders/create">
      <Icon name="Plus" :size="17" />
      {{ $t('Create order') }}
    </RouterLink>
  </PageHeader>
  <AsyncState :loading="loading" :error="error" @retry="load">
    <div class="mb-6 flex items-center gap-2 text-xs text-slate-500">
      <Icon name="CalendarDays" :size="15" />
      <span>{{ dateLabel(data.date) }}</span>
      <span class="mx-1 text-slate-300">·</span>
      <span>{{ $t('Today at a glance') }}</span>
    </div>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
      <div
        v-for="[key, label, icon, color] in cards"
        :key="key"
        class="card p-4"
      >
        <span
          :class="color"
          class="mb-4 inline-flex h-8 w-8 items-center justify-center rounded-lg"
        >
          <Icon :name="icon" :size="17" />
        </span>
        <div class="text-2xl font-semibold tracking-tight text-slate-900">
          {{ data[key] }}
        </div>
        <div class="mt-1 text-[11px] text-slate-500">{{ $t(label) }}</div>
      </div>
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
      <section class="card min-w-0">
        <div class="flex items-center justify-between gap-3 p-5">
          <div>
            <h2 class="font-semibold">{{ $t('Today’s orders') }}</h2>
            <p class="mt-1 text-xs text-slate-400">
              {{ $t('Your daily dispatch board, at a glance.') }}
            </p>
          </div>
          <RouterLink to="/orders" class="link text-xs">
            {{ $t('View all orders →') }}
          </RouterLink>
        </div>
        <AsyncState
          :empty="!data.deliveries.length"
          :message="$t('No orders scheduled for today.')"
        >
          <DeliveryTable :tasks="data.deliveries" compact />
        </AsyncState>
        <div
          class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-400"
        >
          {{
            $t('Showing up to 12 of {count} scheduled orders', {
              count: data.total,
            })
          }}
        </div>
      </section>
      <div class="space-y-5">
        <section class="card p-5">
          <div class="flex items-center gap-2">
            <Icon
              name="ChartNoAxesColumnIncreasing"
              :size="17"
              class="text-brand-600"
            />
            <h2 class="font-semibold">{{ $t('Order progress') }}</h2>
          </div>
          <p class="mt-1 text-xs text-slate-400">
            {{ $t('Today’s completion rate') }}
          </p>
          <div
            class="relative mx-auto my-7 flex h-36 w-36 items-center justify-center rounded-full"
            :style="{
              background: `conic-gradient(#087f63 ${progress}%, #edf1f5 0)`,
            }"
          >
            <div
              class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"
            >
              <span class="text-3xl font-semibold text-slate-900">
                {{ progress }}
                <span class="text-lg">%</span>
              </span>
              <span class="mt-1 text-[10px] text-slate-400">
                {{ $t('completed') }}
              </span>
            </div>
          </div>
          <div
            v-for="[label, count, color] in [
              ['Delivered', data.delivered, 'bg-brand-600'],
              ['In progress', data.in_progress, 'bg-amber-400'],
              ['Awaiting order', data.pending + data.assigned, 'bg-blue-400'],
              [
                'Failed / cancelled',
                data.failed + data.cancelled,
                'bg-slate-300',
              ],
            ]"
            :key="label"
            class="flex items-center justify-between border-t border-slate-100 py-3 text-xs"
          >
            <span class="flex items-center gap-2 text-slate-500">
              <span :class="color" class="h-2 w-2 rounded-full"></span>
              {{ $t(label) }}
            </span>
            <span class="font-semibold text-slate-700">{{ count }}</span>
          </div>
        </section>
        <section class="rounded-xl border border-brand-100 bg-brand-50/70 p-5">
          <Icon name="Route" class="mb-3 text-brand-600" :size="23" />
          <h3 class="text-sm font-semibold">
            {{ $t('Give every stop a place.') }}
          </h3>
          <p class="mt-2 text-xs leading-5 text-slate-500">
            {{
              $t('Group orders into rides and put your drivers’ day in order.')
            }}
          </p>
          <RouterLink to="/rides" class="link mt-4 inline-block text-xs">
            {{ $t('Plan a ride →') }}
          </RouterLink>
        </section>
      </div>
    </div>
  </AsyncState>
</template>
