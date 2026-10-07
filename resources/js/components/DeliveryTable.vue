<script setup>
import StatusBadge from './StatusBadge.vue';
import DriverBadge from './DriverBadge.vue';
import { timeLabel, orderDateLabel } from '../utils';
defineProps({ tasks: Array, driver: Boolean, compact: Boolean });
</script>
<template>
  <div class="divide-y divide-slate-100 md:hidden">
    <article v-for="task in tasks" :key="task.id" class="space-y-3 p-4">
      <div class="flex items-start justify-between gap-3">
        <RouterLink
          :to="driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`"
          class="min-w-0 flex-1 py-1"
        >
          <span class="text-xs font-semibold text-brand-700">
            {{ task.reference_number }}
          </span>
          <h3 class="mt-1 break-words text-base font-semibold">
            {{ task.recipient_name }}
          </h3>
        </RouterLink>
        <StatusBadge :status="task.status" />
      </div>
      <p class="break-words text-sm text-slate-600">{{ task.address }}</p>
      <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500">
        <span>{{ orderDateLabel(task) }}</span>
        <span>{{ timeLabel(task) }}</span>
        <span
          v-if="['high', 'urgent'].includes(task.priority)"
          class="font-semibold text-amber-700"
        >
          {{ $t(task.priority) }} {{ $t('priority') }}
        </span>
      </div>
      <DriverBadge v-if="!driver" :driver="task.driver" />
      <div
        v-if="!compact && task.items?.length"
        class="rounded-lg bg-slate-50 p-3 text-sm"
      >
        <p v-for="item in task.items" :key="item.item_id">
          {{ item.quantity }} × {{ item.name }}
        </p>
      </div>
      <RouterLink
        :to="driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`"
        class="btn btn-secondary w-full"
      >
        {{ $t('Order details') }}
      </RouterLink>
    </article>
  </div>
  <div class="table-wrap hidden md:block">
    <table class="data-table">
      <thead>
        <tr>
          <th>{{ $t('Reference') }}</th>
          <th>{{ $t('Recipient / destination') }}</th>
          <th v-if="!compact">{{ $t('Items') }}</th>
          <th v-if="!driver">{{ $t('Driver') }}</th>
          <th>{{ $t('Scheduled') }}</th>
          <th>{{ $t('Status') }}</th>
          <th>
            <span class="sr-only">{{ $t('Actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="task in tasks" :key="task.id">
          <td>
            <RouterLink
              :to="driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`"
              class="text-xs font-semibold text-brand-600"
            >
              {{ task.reference_number }}
            </RouterLink>
            <div
              v-if="task.priority === 'high' || task.priority === 'urgent'"
              class="mt-1 text-[10px] font-medium capitalize text-amber-600"
            >
              {{ $t(task.priority) }} {{ $t('priority') }}
            </div>
          </td>
          <td>
            <div class="font-medium text-slate-800">
              {{ task.recipient_name }}
            </div>
            <div class="mt-1 max-w-[270px] truncate text-xs text-slate-400">
              {{ task.address }}
            </div>
          </td>
          <td v-if="!compact">
            <div
              v-for="item in task.items"
              :key="item.item_id"
              class="text-xs text-slate-500"
            >
              {{ item.quantity }} × {{ item.name }}
            </div>
            <span v-if="!task.items?.length" class="text-slate-400">—</span>
          </td>
          <td v-if="!driver">
            <DriverBadge :driver="task.driver" />
          </td>
          <td>
            <div class="text-xs text-slate-600">
              {{ timeLabel(task) }}
            </div>
            <div class="mt-1 text-[11px] text-slate-400">
              {{ orderDateLabel(task) }}
            </div>
          </td>
          <td><StatusBadge :status="task.status" /></td>
          <td>
            <RouterLink
              :to="driver ? `/driver/orders/${task.id}` : `/orders/${task.id}`"
              class="link text-xs"
            >
              {{ $t('View →') }}
            </RouterLink>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
