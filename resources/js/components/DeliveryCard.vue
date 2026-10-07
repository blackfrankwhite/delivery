<script setup>
import { timeLabel, mapLink, coordinates } from '../utils';
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';
import StatusActions from './StatusActions.vue';
defineProps({ task: Object, index: Number });
defineEmits(['updated']);
</script>
<template>
  <article class="card overflow-hidden">
    <div
      class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/50 px-5 py-3"
    >
      <span
        class="flex items-center gap-2 text-xs font-semibold text-slate-500"
      >
        <span
          class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-50 text-brand-700"
        >
          {{ task.sequence || index + 1 }}
        </span>
        {{ $t('Stop') }} {{ task.sequence || index + 1 }}
        <span v-if="task.ride_id" class="font-normal text-slate-400">
          · {{ $t('Ride #{id}', { id: task.ride_id }) }}
        </span>
      </span>
      <StatusBadge :status="task.status" />
    </div>
    <div class="p-5">
      <RouterLink
        :to="`/driver/orders/${task.id}`"
        class="text-lg font-semibold text-slate-900"
      >
        {{ task.recipient_name }}
      </RouterLink>
      <p class="mt-1 text-[10px] text-slate-400">
        {{ task.reference_number }} · {{ timeLabel(task) }}
      </p>
      <div class="mt-5 flex gap-3">
        <Icon name="MapPin" class="mt-0.5 shrink-0 text-brand-600" :size="20" />
        <div>
          <p class="text-base leading-6 text-slate-700">
            {{ task.address }}
          </p>
          <p class="mt-1 text-xs text-slate-500">
            {{
              [
                task.city,
                task.entrance &&
                  $t('Entrance {value}', { value: task.entrance }),
                task.floor && $t('Floor {value}', { value: task.floor }),
                task.apartment && $t('Apt {value}', { value: task.apartment }),
              ]
                .filter(Boolean)
                .join(' · ')
            }}
          </p>
        </div>
      </div>
      <RouterLink
        v-if="coordinates(task)"
        :to="`/driver/orders/${task.id}`"
        class="link mt-3 inline-flex items-center gap-1.5 text-xs"
      >
        <Icon name="MapPin" :size="14" />
        {{ $t('View pinned destination →') }}
      </RouterLink>
      <div class="mt-5 grid gap-3 min-[380px]:grid-cols-2">
        <a
          :href="`tel:${task.recipient_phone}`"
          class="btn btn-secondary min-h-12 !px-2 text-xs"
        >
          <Icon name="Phone" :size="17" />
          {{ task.recipient_phone }}
        </a>
        <a
          :href="mapLink(task)"
          target="_blank"
          rel="noopener noreferrer"
          class="btn btn-secondary min-h-12"
        >
          <Icon name="Navigation" :size="17" />
          {{ $t('Navigate') }}
        </a>
      </div>
      <div v-if="task.items.length" class="mt-5 rounded-lg bg-slate-50 p-3">
        <p
          v-for="item in task.items"
          :key="item.item_id"
          class="text-sm leading-6"
        >
          <span class="font-semibold">{{ item.quantity }} ×</span>
          {{ item.name }}
          <span v-if="item.notes" class="text-xs text-slate-500">
            · {{ item.notes }}
          </span>
        </p>
      </div>
      <p
        v-if="task.notes"
        class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-500"
      >
        {{ task.notes }}
      </p>
      <p v-if="task.driver_notes" class="mt-3 text-xs text-slate-400">
        {{ $t('Your note:') }} {{ task.driver_notes }}
      </p>
      <div class="mt-5">
        <StatusActions
          :task="task"
          driver
          @updated="$emit('updated', $event)"
        />
      </div>
      <RouterLink
        :to="`/driver/orders/${task.id}`"
        class="link mt-4 block text-center text-xs"
      >
        {{ $t('Details & driver notes →') }}
      </RouterLink>
    </div>
  </article>
</template>
