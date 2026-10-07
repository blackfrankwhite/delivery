<script setup>
import { cloneVNode, useId, useSlots } from 'vue';

const props = defineProps({
  label: String,
  error: [Array, String],
  required: Boolean,
});
const id = useId();
const slots = useSlots();
// Connect each slotted native control to a concise label and its validation error.
const FieldControl = () =>
  slots.default?.().map((node) =>
    ['input', 'select', 'textarea'].includes(node.type)
      ? cloneVNode(node, {
          id,
          'aria-invalid': props.error ? 'true' : undefined,
          'aria-describedby': props.error ? `${id}-error` : undefined,
        })
      : node,
  );
</script>

<template>
  <div class="block">
    <label :for="id" class="label">
      {{ $t(label) }}
      <span v-if="required" class="ml-1 text-brand-600" aria-hidden="true">
        *
      </span>
    </label>
    <FieldControl />
    <span
      v-if="error"
      :id="`${id}-error`"
      class="mt-1.5 block text-xs text-red-600"
      role="alert"
    >
      {{ $t(Array.isArray(error) ? error[0] : error) }}
    </span>
  </div>
</template>
