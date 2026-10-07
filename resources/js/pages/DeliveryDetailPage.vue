<script setup>
import { intlLocale } from '../i18n';
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, allPages, errorMessage } from '../api';
import { useAuth } from '../stores/auth';
import { useToasts } from '../stores/toasts';
import { orderDateLabel, timeLabel, mapLink, coordinates } from '../utils';
import PageHeader from '../components/PageHeader.vue';
import AsyncState from '../components/AsyncState.vue';
import StatusBadge from '../components/StatusBadge.vue';
import DriverBadge from '../components/DriverBadge.vue';
import StatusActions from '../components/StatusActions.vue';
import FormField from '../components/FormField.vue';
import Icon from '../components/Icon.vue';
import BaseModal from '../components/BaseModal.vue';
import LocationMap from '../components/LocationMap.vue';
const route = useRoute(),
  router = useRouter(),
  deleteOpen = ref(false),
  auth = useAuth(),
  toasts = useToasts(),
  task = ref(null),
  loading = ref(true),
  error = ref(''),
  drivers = ref([]),
  driverId = ref(null),
  notes = ref(''),
  busy = ref(false),
  actionError = ref('');
async function load() {
  loading.value = true;
  error.value = '';
  try {
    task.value = (await api.get(`/api/orders/${route.params.id}`)).data.data;
    notes.value = task.value.driver_notes || '';
    driverId.value = task.value.driver_id;
    if (!auth.driver)
      drivers.value = await allPages('/api/drivers', { active: 1 });
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
function updated(value) {
  task.value = value;
  notes.value = value.driver_notes || '';
}
async function assign() {
  busy.value = true;
  actionError.value = '';
  try {
    updated(
      (
        await api.post(`/api/orders/${task.value.id}/assign-driver`, {
          driver_id: driverId.value,
        })
      ).data.data,
    );
    toasts.show('Driver assignment updated');
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
async function saveNotes() {
  busy.value = true;
  actionError.value = '';
  try {
    updated(
      (
        await api.patch(`/api/orders/${task.value.id}/notes`, {
          driver_notes: notes.value,
        })
      ).data.data,
    );
    toasts.show('Driver notes saved');
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
async function deleteOrder() {
  busy.value = true;
  actionError.value = '';
  try {
    await api.delete(`/api/orders/${task.value.id}`);
    deleteOpen.value = false;
    toasts.show('Order deleted');
    await router.push('/orders');
  } catch (e) {
    actionError.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <PageHeader
    :title="$t(task?.reference_number || 'Order details')"
    :description="
      $t('Destination, instructions, and a clear record of every update.')
    "
    :eyebrow="$t('Order details')"
  >
    <button class="btn btn-secondary" :disabled="loading" @click="load">
      <Icon name="RefreshCw" :size="15" />
      {{ $t('Refresh') }}
    </button>
    <RouterLink
      v-if="
        task &&
        !auth.driver &&
        !['delivered', 'failed', 'cancelled'].includes(task.status)
      "
      :to="`/orders/${task.id}/edit`"
      class="btn btn-primary"
    >
      <Icon name="Pencil" :size="15" />
      {{ $t('Edit order') }}
    </RouterLink>
    <button
      v-if="auth.admin && task"
      class="btn btn-secondary text-red-600"
      :disabled="busy"
      @click="
        deleteOpen = true;
        actionError = '';
      "
    >
      <Icon name="Trash2" :size="15" />
      {{ $t('Delete order') }}
    </button>
  </PageHeader>
  <AsyncState :loading="loading" :error="error" @retry="load">
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <div class="space-y-6">
        <section class="card p-6">
          <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="text-xl font-semibold">
                {{ task.recipient_name }}
              </h2>
              <a
                :href="`tel:${task.recipient_phone}`"
                class="link mt-2 inline-flex items-center gap-2"
              >
                <Icon name="Phone" :size="15" />
                {{ task.recipient_phone }}
              </a>
            </div>
            <StatusBadge :status="task.status" />
          </div>
          <div class="rounded-xl bg-slate-50 p-5">
            <div class="flex gap-3">
              <Icon
                name="MapPin"
                class="mt-0.5 shrink-0 text-brand-600"
                :size="22"
              />
              <div>
                <p class="text-base font-medium text-slate-800">
                  {{ task.address }}
                </p>
                <p class="mt-1 text-slate-500">
                  {{ task.city }}
                </p>
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-500">
                  <span v-if="task.entrance">
                    {{ $t('Entrance') }} {{ task.entrance }}
                  </span>
                  <span v-if="task.floor">
                    {{ $t('Floor') }} {{ task.floor }}
                  </span>
                  <span v-if="task.apartment">
                    {{ $t('Apartment') }} {{ task.apartment }}
                  </span>
                </div>
                <a
                  :href="mapLink(task)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="link mt-4 inline-flex items-center gap-2"
                >
                  <Icon name="Navigation" :size="16" />
                  {{ $t('Open navigation') }}
                </a>
              </div>
            </div>
          </div>
          <div v-if="coordinates(task)" class="mt-5">
            <h3 class="mb-3 text-sm font-semibold">
              {{ $t('Pinned destination') }}
            </h3>
            <LocationMap
              :latitude="task.latitude"
              :longitude="task.longitude"
              :label="$t('Order destination map')"
            />
          </div>
          <p v-else class="mt-4 text-xs text-slate-400">
            {{
              $t(
                'No precise map pin saved. Navigation uses the written address.',
              )
            }}
          </p>
          <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
              <p class="label">{{ $t('Scheduled') }}</p>
              <p>{{ orderDateLabel(task) }}</p>
              <p class="mt-1 text-xs text-slate-400">
                {{ timeLabel(task) }}
              </p>
            </div>
            <div>
              <p class="label">{{ $t('Priority') }}</p>
              <p class="capitalize">{{ $t(task.priority) }}</p>
            </div>
          </div>
          <div v-if="task.notes" class="mt-6 border-t border-slate-100 pt-5">
            <p class="label">{{ $t('Order instructions') }}</p>
            <p class="whitespace-pre-wrap leading-6 text-slate-600">
              {{ task.notes }}
            </p>
          </div>
          <div class="mt-6 border-t border-slate-100 pt-5">
            <StatusActions
              :task="task"
              :driver="auth.driver"
              @updated="updated"
            />
          </div>
        </section>
        <section class="card p-6">
          <h2 class="mb-5 font-semibold">{{ $t('Items in this order') }}</h2>
          <div
            v-for="item in task.items"
            :key="item.item_id"
            class="flex items-start justify-between border-t border-slate-100 py-4 first:border-0"
          >
            <div class="flex gap-3">
              <span
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-50 text-slate-400"
              >
                <Icon name="Package" />
              </span>
              <div>
                <p class="font-medium text-slate-800">
                  {{ item.name }}
                </p>
                <p v-if="item.notes" class="mt-1 text-xs text-slate-400">
                  {{ item.notes }}
                </p>
              </div>
            </div>
            <span
              class="rounded-lg bg-slate-50 px-3 py-1 text-xs font-semibold"
            >
              × {{ item.quantity }}
            </span>
          </div>
          <p v-if="!task.items.length" class="text-sm text-slate-400">
            {{ $t('No items attached.') }}
          </p>
        </section>
        <section class="card p-6">
          <h2 class="mb-5 font-semibold">{{ $t('Driver notes') }}</h2>
          <FormField :label="$t('Notes for this order')">
            <textarea
              v-model="notes"
              class="field"
              rows="3"
              :placeholder="$t('Add a order update or handover note.')"
            ></textarea>
          </FormField>
          <button
            class="btn btn-secondary mt-4"
            :disabled="busy"
            @click="saveNotes"
          >
            {{ $t('Save notes') }}
          </button>
        </section>
        <p v-if="actionError" class="text-sm text-red-600" role="alert">
          {{ $t(actionError) }}
        </p>
      </div>
      <div class="space-y-6">
        <section v-if="!auth.driver" class="card p-6">
          <h2 class="mb-5 font-semibold">{{ $t('Driver assignment') }}</h2>
          <DriverBadge :driver="task.driver" />
          <form
            v-if="['pending', 'assigned'].includes(task.status)"
            class="mt-4 space-y-3"
            @submit.prevent="assign"
          >
            <select
              v-model="driverId"
              :aria-label="$t('Assign driver')"
              class="field"
            >
              <option :value="null">{{ $t('Unassigned') }}</option>
              <option v-for="d in drivers" :key="d.id" :value="d.id">
                {{ d.name }}
              </option>
            </select>
            <button class="btn btn-secondary w-full" :disabled="busy">
              {{ $t('Assign driver') }}
            </button>
            <p v-if="task.ride_id" class="text-xs text-slate-400">
              {{
                $t('To change drivers, remove this stop from its ride first.')
              }}
            </p>
          </form>
          <RouterLink
            v-if="task.ride_id"
            :to="`/rides/${task.ride_id}`"
            class="link mt-4 block text-xs"
          >
            {{ $t('View assigned ride →') }}
          </RouterLink>
          <RouterLink
            v-if="task.customer_id"
            :to="`/customers/${task.customer_id}`"
            class="link mt-4 block text-xs"
          >
            {{ $t('View customer →') }}
          </RouterLink>
        </section>
        <section class="card p-6">
          <h2 class="mb-6 font-semibold">{{ $t('Status history') }}</h2>
          <ol class="space-y-5">
            <li
              v-for="entry in task.history"
              :key="entry.id"
              class="relative flex gap-3"
            >
              <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-600"
              ></span>
              <div>
                <p class="text-xs font-semibold capitalize text-slate-700">
                  {{ $t(entry.to_status) }}
                </p>
                <p class="mt-1 text-[11px] text-slate-400">
                  {{
                    new Date(entry.created_at).toLocaleString(intlLocale(), {
                      month: 'short',
                      day: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })
                  }}
                  · {{ entry.user.name }}
                </p>
                <p
                  v-if="entry.notes"
                  class="mt-2 whitespace-pre-wrap text-xs leading-5 text-slate-500"
                >
                  {{ $t(entry.notes) }}
                </p>
              </div>
            </li>
          </ol>
          <p v-if="!task.history.length" class="text-xs text-slate-400">
            {{ $t('No status changes recorded.') }}
          </p>
        </section>
      </div>
    </div>
  </AsyncState>
  <BaseModal
    v-if="deleteOpen"
    :title="$t('Delete this order?')"
    @close="!busy && (deleteOpen = false)"
  >
    <p class="text-sm leading-6 text-slate-600">
      {{
        $t(
          '{reference} will be removed from order lists, driver assignments, and active ride stops. Its record, items, and history will remain stored.',
          { reference: task.reference_number },
        )
      }}
    </p>
    <p v-if="actionError" class="mt-4 text-sm text-red-600" role="alert">
      {{ $t(actionError) }}
    </p>
    <div
      class="mt-6 flex flex-wrap justify-end gap-3 [&>.btn]:flex-1 sm:[&>.btn]:flex-none"
    >
      <button
        class="btn btn-secondary"
        :disabled="busy"
        @click="deleteOpen = false"
      >
        {{ $t('Keep order') }}
      </button>
      <button
        class="btn btn-primary !bg-red-600"
        :disabled="busy"
        @click="deleteOrder"
      >
        {{ $t(busy ? 'Deleting…' : 'Delete order') }}
      </button>
    </div>
  </BaseModal>
</template>
