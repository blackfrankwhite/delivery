<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, errorMessage } from '../api';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import { timeLabel } from '../utils';
import RideRouteMap from './RideRouteMap.vue';
import {
  distanceLabel,
  durationLabel,
  directionInstruction,
} from '../routeUtils';
import BaseModal from './BaseModal.vue';
import AsyncState from './AsyncState.vue';
import StatusBadge from './StatusBadge.vue';
import StatusActions from './StatusActions.vue';
import FormField from './FormField.vue';
const props = defineProps({
  id: Number,
  storage: Object,
  tasks: { type: Array, default: () => [] },
  plan: Object,
  routeBusy: Boolean,
  routeError: String,
});
const directionsOpen = ref(false);
const stopIndex = computed(() =>
  props.tasks.findIndex((stop) => stop.id === props.id),
);
const leg = computed(() => props.plan?.legs?.[stopIndex.value]);
const origin = computed(() =>
  stopIndex.value === 0
    ? props.storage?.address
    : props.tasks[stopIndex.value - 1]?.address,
);
const emit = defineEmits(['close', 'updated', 'directions']);
function showDirections() {
  directionsOpen.value = !directionsOpen.value;
  if (directionsOpen.value && !leg.value) emit('directions');
}
const auth = useAuth(),
  toasts = useToasts(),
  task = ref(null),
  loading = ref(true),
  error = ref(''),
  notes = ref(''),
  busy = ref(false),
  saveError = ref('');
async function load() {
  loading.value = true;
  error.value = '';
  try {
    task.value = (await api.get(`/api/orders/${props.id}`)).data.data;
    notes.value = task.value.driver_notes || '';
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
function updated(value) {
  task.value = value;
  notes.value = value.driver_notes || '';
  emit('updated');
}
async function saveNotes() {
  busy.value = true;
  saveError.value = '';
  try {
    updated(
      (
        await api.patch(`/api/orders/${props.id}/notes`, {
          driver_notes: notes.value,
        })
      ).data.data,
    );
    toasts.show('Driver notes saved');
  } catch (e) {
    saveError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
onMounted(load);
</script>
<template>
  <BaseModal
    :title="task?.reference_number || $t('Order details')"
    @close="!busy && $emit('close')"
  >
    <AsyncState :loading="loading" :error="error" @retry="load">
      <div class="space-y-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-semibold">{{ task.recipient_name }}</h2>
            <a
              :href="`tel:${task.recipient_phone}`"
              class="link mt-2 inline-block text-sm"
            >
              {{ task.recipient_phone }}
            </a>
          </div>
          <StatusBadge :status="task.status" />
        </div>
        <div class="rounded-xl bg-slate-50 p-4">
          <p>{{ task.address }}</p>
          <p class="mt-1 text-xs text-slate-500">
            {{ task.city }} · {{ timeLabel(task) }}
          </p>
          <p v-if="task.notes" class="mt-3 whitespace-pre-wrap text-sm">
            {{ task.notes }}
          </p>
          <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <template
              v-for="[key, label] in [
                ['entrance', 'Entrance'],
                ['floor', 'Floor'],
                ['apartment', 'Apartment'],
              ]"
              :key="key"
            >
              <div v-if="task[key]">
                <dt class="text-xs text-slate-500">{{ $t(label) }}</dt>
                <dd class="mt-1 font-medium">{{ task[key] }}</dd>
              </div>
            </template>
          </dl>
          <button
            class="btn btn-secondary mt-4"
            :aria-expanded="directionsOpen"
            :disabled="routeBusy"
            @click="showDirections"
          >
            {{
              $t(directionsOpen ? 'Hide directions' : 'Show directions in app')
            }}
          </button>
        </div>
        <section v-if="directionsOpen" class="space-y-4">
          <h3 class="text-sm font-semibold">{{ $t('Driving directions') }}</h3>
          <template v-if="leg">
            <p class="text-xs text-slate-500">
              {{ $t('From {address}', { address: origin || '—' }) }}
            </p>
            <p class="text-sm font-semibold text-brand-700">
              {{ distanceLabel(leg.distance) }} ·
              {{ durationLabel(leg.duration) }}
            </p>
            <RideRouteMap :storage="storage" :tasks="tasks" :plan="plan" />
            <p class="text-xs text-slate-500">
              {{
                $t(
                  'Directions follow the planned route from storage or the previous stop.',
                )
              }}
            </p>
            <ol class="space-y-3 text-sm">
              <li
                v-for="(step, index) in leg.steps"
                :key="index"
                class="rounded-lg bg-slate-50 p-3"
              >
                {{ directionInstruction(step) }}
                <span v-if="step.distance" class="text-xs text-slate-500">
                  · {{ distanceLabel(step.distance) }}
                </span>
              </li>
            </ol>
          </template>
          <div v-else class="space-y-3">
            <p v-if="routeBusy" class="text-sm text-slate-500" role="status">
              {{ $t('Calculating route…') }}
            </p>
            <p
              v-else-if="routeError"
              class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
              role="alert"
            >
              {{ $t(routeError) }}
            </p>
            <p v-else class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
              {{
                $t(
                  'Plan or recalculate the ride to show driving directions for this order.',
                )
              }}
            </p>
            <button
              v-if="!routeBusy"
              class="btn btn-secondary"
              @click="$emit('directions')"
            >
              {{ $t('Retry directions') }}
            </button>
          </div>
        </section>
        <div v-if="task.items.length">
          <h3 class="mb-2 text-sm font-semibold">{{ $t('Order items') }}</h3>
          <p
            v-for="item in task.items"
            :key="item.item_id"
            class="py-1 text-sm"
          >
            {{ item.quantity }} × {{ item.name }}
          </p>
        </div>
        <StatusActions :task="task" :driver="auth.driver" @updated="updated" />
        <FormField :label="$t('Driver notes')">
          <textarea v-model="notes" class="field" rows="3"></textarea>
        </FormField>
        <p v-if="saveError" class="text-sm text-red-600" role="alert">
          {{ $t(saveError) }}
        </p>
        <div class="flex flex-wrap gap-3">
          <button class="btn btn-secondary" :disabled="busy" @click="saveNotes">
            {{ $t('Save notes') }}
          </button>
          <RouterLink
            :to="
              auth.driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`
            "
            class="btn btn-secondary"
          >
            {{ $t('Order details') }}
          </RouterLink>
          <RouterLink
            v-if="
              !auth.driver &&
              !['delivered', 'failed', 'cancelled'].includes(task.status)
            "
            :to="`/orders/${task.id}/edit`"
            class="btn btn-primary"
          >
            {{ $t('Edit order') }}
          </RouterLink>
        </div>
      </div>
    </AsyncState>
  </BaseModal>
</template>
