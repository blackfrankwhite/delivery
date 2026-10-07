import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '../api';
export const useAuth = defineStore('auth', () => {
  const user = ref(null),
    initialized = ref(false);
  const driver = computed(() => user.value?.role === 'driver');
  const admin = computed(() => user.value?.role === 'superadministrator');
  async function restore() {
    try {
      user.value = (await api.get('/api/me')).data.data;
    } catch (e) {
      if ([401, 403, 419].includes(e.response?.status)) user.value = null;
      else throw e;
    } finally {
      initialized.value = true;
    }
  }
  async function login(email, password) {
    await api.get('/sanctum/csrf-cookie');
    user.value = (
      await api.post('/api/auth/login', { email, password })
    ).data.data;
    initialized.value = true;
  }
  async function logout() {
    await api.post('/api/auth/logout');
    user.value = null;
  }
  return { user, initialized, driver, admin, restore, login, logout };
});
