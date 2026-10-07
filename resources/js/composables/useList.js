import { ref, onMounted, onUnmounted } from 'vue';
import { api, errorMessage } from '../api';
export function useList(path, params) {
  const rows = ref([]),
    meta = ref(null),
    loading = ref(true),
    error = ref('');
  let version = 0;
  async function load(page = 1) {
    const request = ++version;
    loading.value = true;
    error.value = '';
    try {
      const { data } = await api.get(
        typeof path === 'function' ? path() : path,
        { params: { ...params, page } },
      );
      if (request === version) {
        rows.value = data.data;
        meta.value = data.meta;
      }
    } catch (e) {
      if (request === version) error.value = errorMessage(e);
    } finally {
      if (request === version) loading.value = false;
    }
  }
  onMounted(() => load());
  onUnmounted(() => version++);
  return { rows, meta, loading, error, load };
}
