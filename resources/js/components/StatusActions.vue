<script setup>
import { ref, computed } from 'vue';
import { api, errorMessage } from '../api';
import { transitions, actionLabels } from '../utils';
import { useToasts } from '../stores/toasts';
import BaseModal from './BaseModal.vue';
import FormField from './FormField.vue';
import Icon from './Icon.vue';
const props = defineProps({
  task: { type: Object, required: true },
  driver: Boolean,
});
const emit = defineEmits(['updated']);
const toasts = useToasts(),
  busy = ref(false),
  error = ref(''),
  target = ref(null),
  notes = ref('');
const options = computed(() => transitions(props.task.status, props.driver));
async function change(status) {
  if (['delivered', 'failed', 'cancelled'].includes(status)) {
    target.value = status;
    notes.value = props.task.driver_notes || '';
    error.value = '';
  } else await submit(status);
}
async function submit(status) {
  busy.value = true;
  error.value = '';
  try {
    const { data } = await api.post(`/api/orders/${props.task.id}/status`, {
      status,
      notes: notes.value || null,
    });
    emit('updated', data.data);
    target.value = null;
    toasts.show('Order status updated');
  } catch (e) {
    error.value = errorMessage(e);
    if (!target.value) toasts.show(error.value, 'error');
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <div v-if="options.length" class="flex flex-wrap gap-2">
    <button
      v-for="status in options"
      :key="status"
      class="btn"
      :class="[
        status === 'failed' || status === 'cancelled'
          ? 'btn-danger'
          : 'btn-primary',
        driver ? 'min-h-12 flex-1' : '',
      ]"
      :disabled="busy"
      @click="change(status)"
    >
      {{ $t(actionLabels[status]) }}
      <Icon
        v-if="!['failed', 'cancelled'].includes(status)"
        name="ArrowRight"
        :size="16"
      />
    </button>
  </div>
  <BaseModal
    v-if="target"
    :title="$t(actionLabels[target])"
    @close="!busy && (target = null)"
  >
    <form class="space-y-5" @submit.prevent="submit(target)">
      <p class="text-sm text-slate-500">
        {{
          $t(
            'Confirm this action for {reference} · {recipient}. This status is final.',
            {
              reference: task.reference_number,
              recipient: task.recipient_name,
            },
          )
        }}
      </p>
      <FormField
        :label="
          $t(target === 'failed' ? 'Reason for failure' : 'Notes (optional)')
        "
        :required="target === 'failed'"
      >
        <textarea
          v-model="notes"
          class="field"
          rows="3"
          :required="target === 'failed'"
        ></textarea>
      </FormField>
      <p v-if="error" class="text-sm text-red-600" role="alert">
        {{ $t(error) }}
      </p>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="target = null"
        >
          {{ $t('Go back') }}
        </button>
        <button
          class="btn"
          :class="target === 'delivered' ? 'btn-primary' : 'btn-danger'"
          :disabled="busy"
        >
          {{
            busy
              ? $t('Updating…')
              : $t('Confirm {action}', { action: $t(actionLabels[target]) })
          }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>
