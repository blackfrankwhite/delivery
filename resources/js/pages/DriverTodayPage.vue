<script setup>
import { ref, onMounted, computed } from 'vue';
import { allPages, errorMessage } from '../api';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import { useList } from '../composables/useList';
import { today, dateLabel } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import DeliveryCard from '../components/DeliveryCard.vue';
import Pagination from '../components/Pagination.vue';
import Icon from '../components/Icon.vue';
import StatusBadge from '../components/StatusBadge.vue';
const auth = useAuth(),
  toasts = useToasts(),
  rides = ref([]);
const { rows, meta, loading, error, load } = useList('/api/driver/today', {});
const name = computed(() => auth.user?.name.split(' ')[0]);
async function loadRides() {
  try {
    rides.value = await allPages('/api/driver/rides', { date: today() });
  } catch (e) {
    toasts.show(errorMessage(e), 'error');
  }
}
onMounted(loadRides);
async function refresh() {
  await Promise.all([load(meta.value?.current_page || 1), loadRides()]);
}
function updated(task) {
  const index = rows.value.findIndex((t) => t.id === task.id);
  if (index !== -1) rows.value[index] = task;
}
</script>
<template>
  <div class="mx-auto max-w-4xl">
    <PageHeader
      :title="
        $t(`${new Date().getHours() < 12 ? 'Good morning' : 'Hello'}, ${name}`)
      "
      :description="$t('Your stops for today. One order at a time.')"
      :eyebrow="$t(dateLabel(today()))"
    >
      <button class="btn btn-secondary" :disabled="loading" @click="refresh">
        <Icon name="RefreshCw" :size="16" />
        {{ $t('Refresh') }}
      </button>
    </PageHeader>
    <section class="mb-6 rounded-2xl bg-brand-700 p-6 text-white">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-emerald-200">{{ $t('Today’s work') }}</p>
          <h2 class="mt-2 text-2xl font-semibold !text-white">
            {{ meta?.total || 0 }} {{ $t('orders') }}
          </h2>
          <p class="mt-2 text-xs text-emerald-100/70">
            {{ $t('Call ahead. Follow instructions. Keep everyone updated.') }}
          </p>
        </div>
        <Icon name="Route" :size="42" class="text-emerald-200/60" />
      </div>
    </section>
    <div v-if="rides.length" class="mb-6 space-y-3">
      <RouterLink
        v-for="ride in rides"
        :key="ride.id"
        :to="`/driver/rides/${ride.id}`"
        class="card flex items-center justify-between p-4"
      >
        <div>
          <p class="text-sm font-semibold">
            {{ $t('Ride #{id}', { id: ride.id }) }}
          </p>
          <p class="mt-1 text-xs text-slate-400">
            {{
              $t('{count} stops · View route →', { count: ride.tasks_count })
            }}
          </p>
        </div>
        <StatusBadge :status="ride.status" />
      </RouterLink>
    </div>
    <AsyncState
      :loading="loading"
      :error="error"
      :empty="!rows.length"
      :message="
        $t(
          'No tasks have been assigned to you today. Check back with your dispatcher.',
        )
      "
      @retry="refresh"
    >
      <div class="grid gap-5 md:grid-cols-2">
        <DeliveryCard
          v-for="(task, index) in rows"
          :key="task.id"
          :task="task"
          :index="index + (meta.current_page - 1) * meta.per_page"
          @updated="updated"
        />
      </div>
    </AsyncState>
    <div class="mt-5"><Pagination :meta="meta" @page="load" /></div>
  </div>
</template>
