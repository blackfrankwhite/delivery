<script setup>
import { reactive, ref } from 'vue';
import { api, errorMessage } from '../api';
import BaseModal from './BaseModal.vue';
import FormField from './FormField.vue';
import AddressAutocomplete from './AddressAutocomplete.vue';
import LocationMap from './LocationMap.vue';
const emit = defineEmits(['close', 'created']);
const busy = ref(false),
  errors = ref({}),
  error = ref('');
const form = reactive({
  name: '',
  phone: '',
  email: '',
  notes: '',
  active: true,
  default_address: {
    label: 'ძირითადი მისამართი',
    address: '',
    city: '',
    latitude: null,
    longitude: null,
    entrance: '',
    floor: '',
    apartment: '',
    instructions: '',
    is_default: true,
  },
});
async function save() {
  busy.value = true;
  errors.value = {};
  error.value = '';
  try {
    emit('created', (await api.post('/api/customers', form)).data.data);
  } catch (e) {
    errors.value = e.response?.data.errors || {};
    error.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <BaseModal :title="$t('Create customer')" @close="!busy && $emit('close')">
    <form class="space-y-4" @submit.prevent="save">
      <FormField
        v-for="[key, label, type] in [
          ['name', 'Customer name', 'text'],
          ['phone', 'Phone', 'tel'],
          ['email', 'Email (optional)', 'email'],
        ]"
        :key="key"
        :label="$t(label)"
        :error="errors[key]"
      >
        <input
          v-model="form[key]"
          class="field"
          :type="type"
          :required="key !== 'email'"
        />
      </FormField>
      <h3 class="border-t border-slate-100 pt-4 font-semibold">
        {{ $t('Default address') }}
      </h3>
      <AddressAutocomplete
        v-model="form.default_address.address"
        :city="form.default_address.city"
        :label="$t('Address')"
        required
        :error="errors['default_address.address']"
        @select="
          (suggestion) =>
            Object.assign(form.default_address, {
              latitude: suggestion.latitude,
              longitude: suggestion.longitude,
              city: suggestion.city || '',
            })
        "
      />
      <div class="grid grid-cols-2 gap-4">
        <FormField
          v-for="[key, label] in [
            ['label', 'Label'],
            ['city', 'City'],
            ['entrance', 'Entrance'],
            ['floor', 'Floor'],
            ['apartment', 'Apartment'],
          ]"
          :key="key"
          :label="$t(label)"
          :error="errors['default_address.' + key]"
        >
          <input
            v-model="form.default_address[key]"
            class="field"
            :required="key === 'label'"
          />
        </FormField>
      </div>
      <FormField
        :label="$t('Instructions')"
        :error="errors['default_address.instructions']"
      >
        <textarea
          v-model="form.default_address.instructions"
          class="field"
          rows="2"
        ></textarea>
      </FormField>
      <LocationMap
        v-model:latitude="form.default_address.latitude"
        v-model:longitude="form.default_address.longitude"
        editable
        :label="$t('Saved address location picker')"
      />
      <p
        v-if="
          errors['default_address.latitude'] ||
          errors['default_address.longitude']
        "
        class="text-xs text-red-600"
      >
        {{
          (errors['default_address.latitude'] ||
            errors['default_address.longitude'])[0]
        }}
      </p>
      <FormField :label="$t('Notes')" :error="errors.notes">
        <textarea v-model="form.notes" class="field" rows="2"></textarea>
      </FormField>
      <p v-if="error" class="text-sm text-red-600" role="alert">
        {{ $t(error) }}
      </p>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="$emit('close')"
        >
          {{ $t('Cancel') }}
        </button>
        <button class="btn btn-primary" :disabled="busy">
          {{ $t(busy ? 'Saving…' : 'Create customer') }}
        </button>
      </div>
    </form>
  </BaseModal>
</template>
