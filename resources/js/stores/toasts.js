import { defineStore } from 'pinia';
import { ref } from 'vue';
export const useToasts = defineStore('toasts', () => {
  const messages = ref([]);
  let sequence = 0;
  function show(message, type = 'success') {
    const id = ++sequence;
    messages.value.push({ id, message, type });
    setTimeout(() => {
      messages.value = messages.value.filter((m) => m.id !== id);
    }, 6000);
  }
  return { messages, show };
});
