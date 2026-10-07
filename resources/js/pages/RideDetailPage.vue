<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, allPages, errorMessage } from '../api';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import Sortable from 'sortablejs';
import { coordinates } from '../utils';
import {
  distanceLabel,
  durationLabel,
  directionInstruction,
} from '../routeUtils';
import RideRouteMap from '../components/RideRouteMap.vue';
import RideOrderPanel from '../components/RideOrderPanel.vue';
import { dateLabel, timeLabel } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import StatusBadge from '../components/StatusBadge.vue';
import DriverBadge from '../components/DriverBadge.vue';
import BaseModal from '../components/BaseModal.vue';
import Icon from '../components/Icon.vue';
const route = useRoute(),
  router = useRouter(),
  deleteOpen = ref(false),
  auth = useAuth(),
  toasts = useToasts(),
  ride = ref(null),
  loading = ref(true),
  error = ref(''),
  actionError = ref(''),
  busy = ref(false),
  modal = ref(false),
  eligible = ref([]),
  selected = ref([]),
  optionsLoading = ref(false),
  confirm = ref(null),
  storageOptions = ref([]),
  storageId = ref(null),
  wholeDirectionsOpen = ref(false),
  clientModal = ref(false),
  clients = ref([]),
  clientId = ref(null),
  selectedOrder = ref(null),
  stopsElement = ref(null);
let sortable;
const editable = computed(() => ride.value?.status === 'planned');
async function load() {
  loading.value = true;
  error.value = '';
  try {
    ride.value = (await api.get(`/api/rides/${route.params.id}`)).data.data;
    storageId.value = ride.value.storage_location_id;
    storageOptions.value = (
      await api.get('/api/ride-storage-locations')
    ).data.data;
    if (!storageId.value && storageOptions.value.length === 1)
      storageId.value = storageOptions.value[0].id;
    if (
      ride.value.storage &&
      ride.value.tasks.length &&
      !ride.value.route_plan &&
      ride.value.status !== 'completed'
    )
      await calculate(
        ride.value.status === 'planned' && !ride.value.route_plan_stale,
      );
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
const startStorage = computed(() =>
  storageId.value === ride.value?.storage_location_id
    ? ride.value?.storage
    : storageOptions.value.find((storage) => storage.id === storageId.value),
);
const displayedPlan = computed(() =>
  storageId.value === ride.value?.storage_location_id
    ? ride.value?.route_plan
    : null,
);
const missingPins = computed(
  () => ride.value?.tasks.filter((task) => !coordinates(task)) || [],
);
async function calculate(optimize = true) {
  if (!storageId.value) {
    actionError.value = 'Select a storage location with a map pin.';
    return false;
  }
  return act(
    () =>
      api.post(`/api/rides/${ride.value.id}/plan`, {
        optimize,
        storage_location_id:
          storageId.value === ride.value.storage_location_id
            ? null
            : storageId.value,
      }),
    optimize ? 'Ride route planned' : 'Route recalculated',
  );
}
async function ensureDirections() {
  if (busy.value || displayedPlan.value) return;
  if (ride.value.status === 'completed') {
    actionError.value =
      'No saved driving directions are available for this completed ride.';
    return;
  }
  await calculate(false);
}
async function showWholeDirections() {
  wholeDirectionsOpen.value = true;
  await ensureDirections();
}
async function reorderTo(ids) {
  const previous = ride.value.tasks,
    previousPlan = ride.value.route_plan;
  ride.value.tasks = ids.map((id, index) => ({
    ...previous.find((task) => task.id === id),
    sequence: index + 1,
  }));
  ride.value.route_plan = null;
  if (
    await act(
      () => api.put(`/api/rides/${ride.value.id}/reorder`, { task_ids: ids }),
      'Stop order saved',
    )
  ) {
    if (storageId.value) await calculate(false);
  } else {
    ride.value.tasks = previous;
    ride.value.route_plan = previousPlan;
  }
}
async function move(index, direction) {
  const ids = ride.value.tasks.map((task) => task.id);
  [ids[index], ids[index + direction]] = [ids[index + direction], ids[index]];
  await reorderTo(ids);
}
async function removeStop(task) {
  if (
    await act(
      () => api.delete(`/api/rides/${ride.value.id}/tasks/${task.id}`),
      'Stop removed',
    )
  ) {
    if (storageId.value && ride.value.tasks.length) await calculate(false);
  }
}
async function refreshOrders() {
  try {
    ride.value = (await api.get(`/api/rides/${route.params.id}`)).data.data;
    if (
      ride.value.storage &&
      ride.value.tasks.length &&
      !ride.value.route_plan &&
      ride.value.status !== 'completed'
    )
      await calculate(false);
  } catch (e) {
    actionError.value = errorMessage(e);
  }
}
watch(
  stopsElement,
  (element) => {
    sortable?.destroy();
    sortable = null;
    if (!element) return;
    sortable = Sortable.create(element, {
      handle: '.drag-handle',
      draggable: '.ride-stop',
      animation: 150,
      disabled: !editable.value || busy.value,
      ghostClass: 'opacity-40',
      delay: 120,
      delayOnTouchOnly: true,
      touchStartThreshold: 5,
      onEnd(event) {
        const { oldIndex, newIndex, item, from } = event;
        if (oldIndex === newIndex) return;
        // Restore the DOM before Vue applies the optimistic order to its keyed list.
        const others = [...from.children].filter((child) => child !== item);
        from.insertBefore(item, others[oldIndex] || null);
        const ids = ride.value.tasks.map((task) => task.id);
        ids.splice(newIndex, 0, ids.splice(oldIndex, 1)[0]);
        reorderTo(ids);
      },
    });
  },
  { flush: 'post' },
);
watch([editable, busy], () =>
  sortable?.option('disabled', !editable.value || busy.value),
);
onUnmounted(() => sortable?.destroy());
async function act(action, message) {
  busy.value = true;
  actionError.value = '';
  try {
    ride.value = (await action()).data.data;
    toasts.show(message);
    return true;
  } catch (e) {
    actionError.value = errorMessage(e);
    return false;
  } finally {
    busy.value = false;
  }
}
async function open() {
  modal.value = true;
  optionsLoading.value = true;
  actionError.value = '';
  selected.value = [];
  try {
    const tasks = await allPages('/api/orders', {
      date: ride.value.date,
    });
    eligible.value = tasks.filter(
      (t) =>
        !t.ride_id &&
        ['pending', 'assigned'].includes(t.status) &&
        (!t.driver_id || t.driver_id === ride.value.driver_id),
    );
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    optionsLoading.value = false;
  }
}
async function openClients() {
  clientModal.value = true;
  clientId.value = null;
  actionError.value = '';
  optionsLoading.value = true;
  try {
    clients.value = (await allPages('/api/customers', { active: 1 })).filter(
      (client) =>
        !ride.value.tasks.some((task) => task.customer_id === client.id),
    );
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    optionsLoading.value = false;
  }
}
const clientAddress = computed(() => {
  const client = clients.value.find((client) => client.id === clientId.value);
  return (
    client?.addresses?.find((address) => address.is_default) ||
    client?.addresses?.[0]
  );
});
async function addClient() {
  if (
    await act(
      () =>
        api.post(`/api/rides/${ride.value.id}/clients`, {
          customer_id: clientId.value,
        }),
      'Client added to ride',
    )
  ) {
    clientModal.value = false;
    if (storageId.value) await calculate(!ride.value.route_plan_stale);
  }
}
async function add() {
  if (
    await act(
      () =>
        api.post(`/api/rides/${ride.value.id}/tasks`, {
          task_ids: selected.value,
        }),
      'Stops added to ride',
    )
  ) {
    modal.value = false;
    if (storageId.value) await calculate(!ride.value.route_plan_stale);
  }
}
async function transition() {
  if (
    await act(
      () =>
        api.post(`/api/rides/${ride.value.id}/status`, {
          status: confirm.value,
        }),
      'Ride status updated',
    )
  )
    confirm.value = null;
}
async function deleteRide() {
  busy.value = true;
  actionError.value = '';
  try {
    await api.delete(`/api/rides/${ride.value.id}`);
    deleteOpen.value = false;
    toasts.show('Ride deleted');
    await router.push('/rides');
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <PageHeader
    :title="ride ? $t('Ride #{id}', { id: ride.id }) : $t('Ride details')"
    :description="
      $t(
        'Plan from storage, drag stops to change their order, and open any map point to manage its order.',
      )
    "
    :eyebrow="$t('Route planning')"
  >
    <button class="btn btn-secondary" :disabled="loading || busy" @click="load">
      <Icon name="RefreshCw" :size="15" />
      {{ $t('Refresh') }}
    </button>
    <button v-if="editable" class="btn btn-primary" @click="open">
      <Icon name="Plus" :size="16" />
      {{ $t('Add stops') }}
    </button>
    <button
      v-if="editable && !auth.driver"
      class="btn btn-secondary"
      :disabled="busy"
      @click="openClients"
    >
      <Icon name="Plus" :size="16" />
      {{ $t('Add client') }}
    </button>
    <button
      v-if="auth.admin && ride"
      class="btn btn-secondary text-red-600"
      :disabled="busy"
      @click="
        deleteOpen = true;
        actionError = '';
      "
    >
      <Icon name="Trash2" :size="15" />
      {{ $t('Delete ride') }}
    </button>
  </PageHeader>
  <AsyncState :loading="loading" :error="error" @retry="load">
    <section
      class="card mb-6 flex flex-wrap items-center justify-between gap-5 p-6"
    >
      <div class="flex flex-wrap items-center gap-8">
        <div>
          <p class="label">{{ $t('Driver') }}</p>
          <DriverBadge :driver="ride.driver" />
        </div>
        <div>
          <p class="label">{{ $t('Date') }}</p>
          <p>{{ dateLabel(ride.date) }}</p>
        </div>
        <div>
          <p class="label">{{ $t('Stops') }}</p>
          <p class="font-semibold">
            {{ ride.tasks.length }} {{ $t('orders') }}
          </p>
        </div>
        <StatusBadge :status="ride.status" />
      </div>
      <button
        v-if="ride.status !== 'completed'"
        class="btn btn-primary"
        :disabled="busy || (ride.status === 'planned' && !displayedPlan)"
        @click="
          confirm = ride.status === 'planned' ? 'started' : 'completed';
          actionError = '';
        "
      >
        {{ $t(ride.status === 'planned' ? 'Start ride' : 'Complete ride') }}
        <Icon name="ArrowRight" :size="16" />
      </button>
      <p
        v-if="ride.notes"
        class="w-full whitespace-pre-wrap text-sm text-slate-500"
      >
        {{ ride.notes }}
      </p>
    </section>
    <p
      v-if="actionError && !modal && !confirm"
      class="mb-5 text-sm text-red-600"
      role="alert"
    >
      {{ $t(actionError) }}
    </p>
    <section class="card mb-6 p-5 sm:p-6">
      <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div class="w-full min-w-0 sm:w-auto sm:flex-1 sm:max-w-md">
          <label for="ride-storage" class="label">
            {{ $t('Starting storage location') }}
          </label>
          <select
            id="ride-storage"
            v-model="storageId"
            class="field"
            :disabled="busy || !editable"
            @change="
              ride.tasks.length &&
              calculate(!ride.route_plan && !ride.route_plan_stale)
            "
          >
            <option :value="null">{{ $t('Select storage') }}</option>
            <option
              v-if="
                ride.storage &&
                !storageOptions.some(
                  (storage) => storage.id === ride.storage_location_id,
                )
              "
              :value="ride.storage_location_id"
            >
              {{ ride.storage.name }} · {{ $t('Saved storage') }}
            </option>
            <option
              v-for="storage in storageOptions"
              :key="storage.id"
              :value="storage.id"
            >
              {{ storage.name }} · {{ storage.address }}
            </option>
          </select>
        </div>
        <div
          class="flex w-full flex-wrap gap-2 [&>.btn]:flex-1 sm:w-auto sm:[&>.btn]:flex-none"
        >
          <button
            class="btn btn-secondary"
            :disabled="busy || !ride.tasks.length"
            @click="showWholeDirections"
          >
            <Icon name="Route" :size="16" />
            {{ $t('View full route directions') }}
          </button>
          <button
            v-if="editable"
            class="btn btn-primary"
            :disabled="
              busy || !storageId || !ride.tasks.length || missingPins.length > 0
            "
            @click="calculate(true)"
          >
            <Icon name="Route" :size="16" />
            {{ $t(busy ? 'Calculating route…' : 'Plan ride automatically') }}
          </button>
          <button
            v-if="ride.status !== 'completed'"
            class="btn btn-secondary"
            :disabled="
              busy || !storageId || !ride.tasks.length || missingPins.length > 0
            "
            @click="calculate(false)"
          >
            {{ $t('Recalculate route') }}
          </button>
        </div>
      </div>
      <p
        v-if="!storageOptions.length && !ride.storage"
        class="mb-4 text-xs text-amber-700"
      >
        {{
          $t(
            'An administrator needs to add an active storage location with a map pin first.',
          )
        }}
        <RouterLink v-if="auth.admin" to="/storage-locations" class="link">
          {{ $t('Add storage location') }}
        </RouterLink>
      </p>
      <div
        v-if="missingPins.length"
        class="mb-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800"
        role="alert"
      >
        <p>
          {{
            $t(
              'These orders need map pins before a driving route can be planned:',
            )
          }}
        </p>
        <button
          v-for="task in missingPins"
          :key="task.id"
          class="mt-2 mr-3 underline"
          @click="selectedOrder = task.id"
        >
          {{ task.reference_number }} · {{ task.recipient_name }}
        </button>
      </div>
      <div v-if="displayedPlan" class="mb-4 flex flex-wrap gap-5 text-sm">
        <span class="font-semibold text-brand-700">
          {{ distanceLabel(displayedPlan.distance) }}
        </span>
        <span>
          {{ durationLabel(displayedPlan.duration) }} ·
          {{ $t('Estimated driving time') }}
        </span>
      </div>
      <p v-else-if="ride.tasks.length" class="mb-4 text-xs text-slate-500">
        {{
          $t(
            'Plan the route to show road directions. Changing the storage, stops, or pins requires recalculation.',
          )
        }}
      </p>
      <RideRouteMap
        :storage="startStorage"
        :tasks="ride.tasks"
        :plan="displayedPlan"
        @select="selectedOrder = $event"
      />
      <p v-if="editable" class="mt-4 text-xs text-slate-500">
        {{
          $t(
            'Automatic planning improves driving order. It does not schedule delivery time windows or live traffic.',
          )
        }}
      </p>
      <details v-if="displayedPlan" class="mt-5 border-t border-slate-100 pt-4">
        <summary class="cursor-pointer text-sm font-semibold">
          {{ $t('Driving directions') }}
        </summary>
        <div class="mt-3 space-y-3">
          <details
            v-for="(leg, index) in displayedPlan.legs"
            :key="ride.tasks[index]?.id"
            class="rounded-lg bg-slate-50 p-3"
          >
            <summary class="cursor-pointer text-xs font-semibold">
              {{ index + 1 }}. {{ ride.tasks[index]?.recipient_name }} ·
              {{ distanceLabel(leg.distance) }} ·
              {{ durationLabel(leg.duration) }}
            </summary>
            <ol class="mt-3 space-y-2 text-xs text-slate-600">
              <li v-for="(step, stepIndex) in leg.steps" :key="stepIndex">
                {{ directionInstruction(step) }}
                <span v-if="step.distance" class="text-slate-400">
                  · {{ distanceLabel(step.distance) }}
                </span>
              </li>
            </ol>
          </details>
        </div>
      </details>
    </section>
    <AsyncState
      :empty="!ride.tasks.length"
      :message="$t('No stops yet. Add orders to plan this route.')"
    >
      <div ref="stopsElement" class="space-y-4">
        <article
          v-for="(task, index) in ride.tasks"
          :key="task.id"
          class="ride-stop card flex flex-wrap items-center gap-3 p-4 sm:gap-4 sm:p-5"
        >
          <button
            v-if="editable"
            class="drag-handle flex min-h-11 min-w-11 items-center justify-center cursor-grab touch-none rounded-lg p-2 text-slate-400 active:cursor-grabbing"
            :disabled="busy"
            :aria-label="$t('Drag stop')"
          >
            <Icon name="GripVertical" :size="20" />
          </button>
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-base font-semibold text-brand-700"
          >
            {{ task.sequence }}
          </span>
          <div
            class="min-w-0 basis-[calc(100%-7rem)] flex-1 sm:min-w-44 sm:basis-auto"
          >
            <RouterLink
              :to="
                auth.driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`
              "
              class="font-semibold text-slate-800 hover:text-brand-600"
            >
              {{ task.recipient_name }}
            </RouterLink>
            <p class="mt-1 text-xs text-slate-500">
              {{ task.address }}
            </p>
            <p class="mt-2 text-[11px] text-slate-400">
              {{
                $t(
                  task.items
                    .map((i) => `${i.quantity} × ${i.name}`)
                    .join(' · ') || $t('No items'),
                )
              }}
              · {{ timeLabel(task) }}
            </p>
          </div>
          <StatusBadge :status="task.status" />
          <div v-if="editable" class="ml-auto flex gap-2">
            <button
              class="btn btn-secondary !px-2"
              :disabled="index === 0 || busy"
              :aria-label="$t('Move stop up')"
              @click="move(index, -1)"
            >
              <Icon name="ChevronUp" :size="17" />
            </button>
            <button
              class="btn btn-secondary !px-2"
              :disabled="index === ride.tasks.length - 1 || busy"
              :aria-label="$t('Move stop down')"
              @click="move(index, 1)"
            >
              <Icon name="ChevronDown" :size="17" />
            </button>
            <button
              class="btn btn-secondary !px-2 text-red-500"
              :disabled="busy"
              :aria-label="$t('Remove stop')"
              @click="removeStop(task)"
            >
              <Icon name="X" :size="17" />
            </button>
          </div>
          <RouterLink
            :to="
              auth.driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`
            "
            class="link text-xs"
          >
            {{ $t('View →') }}
          </RouterLink>
        </article>
      </div>
    </AsyncState>
  </AsyncState>
  <BaseModal
    v-if="clientModal"
    :title="$t('Add client to ride')"
    @close="!busy && (clientModal = false)"
  >
    <p class="mb-4 text-sm text-slate-500">
      {{
        $t(
          'Creates an order using the client’s default address, the ride date, and 09:00–18:00. You can then edit its details.',
        )
      }}
    </p>
    <AsyncState
      :loading="optionsLoading"
      :empty="!clients.length"
      :message="$t('No eligible clients found.')"
    >
      <select
        v-model="clientId"
        class="field"
        :disabled="busy"
        :aria-label="$t('Customer')"
      >
        <option :value="null">{{ $t('Select client') }}</option>
        <option v-for="client in clients" :key="client.id" :value="client.id">
          {{ client.name }}
        </option>
      </select>
      <div v-if="clientAddress" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm">
        <p>{{ clientAddress.address }}</p>
        <p>{{ clientAddress.city }}</p>
        <p v-for="key in ['entrance', 'floor', 'apartment']" :key="key">
          {{
            clientAddress[key]
              ? $t(key[0].toUpperCase() + key.slice(1)) +
                ': ' +
                clientAddress[key]
              : ''
          }}
        </p>
        <p class="mt-2 whitespace-pre-wrap">{{ clientAddress.instructions }}</p>
      </div>
      <p v-else-if="clientId" class="mt-3 text-sm text-amber-700">
        {{
          $t('This client needs a saved address before being added to a ride.')
        }}
      </p>
    </AsyncState>
    <p v-if="actionError" class="mt-4 text-sm text-red-600" role="alert">
      {{ $t(actionError) }}
    </p>
    <div class="mt-5 flex justify-end gap-3">
      <button
        class="btn btn-secondary"
        :disabled="busy"
        @click="clientModal = false"
      >
        {{ $t('Cancel') }}
      </button>
      <button
        class="btn btn-primary"
        :disabled="busy || !clientAddress"
        @click="addClient"
      >
        {{ $t(busy ? 'Adding…' : 'Add client') }}
      </button>
    </div>
  </BaseModal>
  <BaseModal
    v-if="wholeDirectionsOpen"
    :title="$t('Full ride directions')"
    @close="wholeDirectionsOpen = false"
  >
    <p v-if="busy" class="mb-4 text-sm text-slate-500" role="status">
      {{ $t('Calculating route…') }}
    </p>
    <template v-if="displayedPlan">
      <p class="mb-4 text-sm font-semibold text-brand-700">
        {{ distanceLabel(displayedPlan.distance) }} ·
        {{ durationLabel(displayedPlan.duration) }}
      </p>
      <RideRouteMap
        :storage="startStorage"
        :tasks="ride.tasks"
        :plan="displayedPlan"
        @select="
          wholeDirectionsOpen = false;
          selectedOrder = $event;
        "
      />
      <p class="mt-4 text-xs text-slate-500">
        {{
          $t(
            'Follow the complete route from storage through every order to the last stop.',
          )
        }}
      </p>
      <section
        v-for="(leg, index) in displayedPlan.legs"
        :key="ride.tasks[index]?.id"
        class="mt-5 border-t border-slate-100 pt-4"
      >
        <h3 class="text-sm font-semibold">
          {{ index + 1 }}. {{ ride.tasks[index]?.recipient_name }}
        </h3>
        <p class="mt-1 text-xs text-slate-500">
          {{ ride.tasks[index]?.address }}
        </p>
        <p class="mt-2 text-xs text-brand-700">
          {{ distanceLabel(leg.distance) }} · {{ durationLabel(leg.duration) }}
        </p>
        <ol class="mt-3 space-y-2 text-sm">
          <li
            v-for="(step, stepIndex) in leg.steps"
            :key="stepIndex"
            class="rounded-lg bg-slate-50 p-3"
          >
            {{ directionInstruction(step) }}
            <span v-if="step.distance" class="text-xs text-slate-500">
              · {{ distanceLabel(step.distance) }}
            </span>
          </li>
        </ol>
      </section>
    </template>
    <div v-else-if="!busy" class="space-y-4">
      <p class="text-sm text-red-700" role="alert">
        {{
          $t(
            actionError ||
              'Plan or recalculate the ride to show driving directions for this order.',
          )
        }}
      </p>
      <button class="btn btn-secondary" @click="ensureDirections">
        {{ $t('Retry directions') }}
      </button>
    </div>
  </BaseModal>
  <RideOrderPanel
    v-if="selectedOrder"
    :id="selectedOrder"
    :key="selectedOrder"
    :storage="startStorage"
    :tasks="ride.tasks"
    :plan="displayedPlan"
    :route-busy="busy"
    :route-error="actionError"
    @directions="ensureDirections"
    @close="selectedOrder = null"
    @updated="refreshOrders"
  />
  <BaseModal
    v-if="modal"
    :title="$t('Add stops to ride')"
    @close="!busy && (modal = false)"
  >
    <p class="mb-5 text-sm text-slate-500">
      {{
        $t(
          'Orders whose date range includes {date}, for {driver}. Adding a pending order also assigns it to this driver.',
          { date: dateLabel(ride.date), driver: ride.driver.name },
        )
      }}
    </p>
    <AsyncState
      :loading="optionsLoading"
      :empty="!eligible.length"
      :error="actionError"
      :message="
        $t(
          'No eligible orders. Create a pending order covering this date or assign one to this driver.',
        )
      "
      @retry="open"
    >
      <div
        class="max-h-[45dvh] space-y-2 overflow-y-auto overscroll-contain sm:max-h-80"
      >
        <label
          v-for="task in eligible"
          :key="task.id"
          class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50"
        >
          <input
            v-model="selected"
            class="mt-1 accent-brand-600"
            type="checkbox"
            :value="task.id"
          />
          <div>
            <p class="text-sm font-medium">
              {{ task.recipient_name }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ task.address }}
            </p>
            <p class="mt-1 text-[10px] text-brand-600">
              {{ task.reference_number }} · {{ timeLabel(task) }}
            </p>
          </div>
        </label>
      </div>
    </AsyncState>
    <div class="mt-6 flex justify-end gap-3">
      <button class="btn btn-secondary" :disabled="busy" @click="modal = false">
        {{ $t('Cancel') }}
      </button>
      <button
        class="btn btn-primary"
        :disabled="!selected.length || busy"
        @click="add"
      >
        {{
          busy
            ? $t('Adding…')
            : $t('Add {count} stops', { count: selected.length })
        }}
      </button>
    </div>
  </BaseModal>
  <BaseModal
    v-if="confirm"
    :title="
      $t(confirm === 'started' ? 'Start this ride?' : 'Complete this ride?')
    "
    @close="!busy && (confirm = null)"
  >
    <p class="text-sm text-slate-500">
      {{
        $t(
          confirm === 'started'
            ? 'Once started, the stops and their order are fixed.'
            : 'All stops must be delivered, failed, or cancelled before completing this ride.',
        )
      }}
    </p>
    <p v-if="actionError" class="mt-4 text-sm text-red-600">
      {{ $t(actionError) }}
    </p>
    <div class="mt-6 flex justify-end gap-3">
      <button
        class="btn btn-secondary"
        :disabled="busy"
        @click="confirm = null"
      >
        {{ $t('Go back') }}
      </button>
      <button class="btn btn-primary" :disabled="busy" @click="transition">
        {{ $t('Confirm') }}
      </button>
    </div>
  </BaseModal>
  <BaseModal
    v-if="deleteOpen"
    :title="$t('Delete this ride?')"
    @close="!busy && (deleteOpen = false)"
  >
    <p class="text-sm leading-6 text-slate-600">
      {{
        $t(
          'Ride #{id} will be removed from active route lists. Its orders will be kept and released from this ride, with their driver assignments and statuses unchanged. Pending or assigned orders can be added to another eligible ride. The ride record and removal history will remain stored.',
          { id: ride.id },
        )
      }}
    </p>
    <p v-if="actionError" class="mt-4 text-sm text-red-600" role="alert">
      {{ $t(actionError) }}
    </p>
    <div class="mt-6 flex justify-end gap-3">
      <button
        class="btn btn-secondary"
        :disabled="busy"
        @click="deleteOpen = false"
      >
        {{ $t('Keep ride') }}
      </button>
      <button
        class="btn btn-primary !bg-red-600"
        :disabled="busy"
        @click="deleteRide"
      >
        {{ $t(busy ? 'Deleting…' : 'Delete ride') }}
      </button>
    </div>
  </BaseModal>
</template>
