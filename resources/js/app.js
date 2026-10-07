import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import '../css/app.css';
import { t } from './i18n';
const app = createApp(App);
app.config.globalProperties.$t = t;
app.use(createPinia()).use(router).mount('#app');

// A stale session returns the user to login instead of leaving a broken screen.
import { api } from './api';
import { useAuth } from './stores/auth';
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if ([401, 419].includes(error.response?.status) && useAuth().user) {
      useAuth().user = null;
      router.push({
        path: '/login',
        query: { redirect: router.currentRoute.value.fullPath },
      });
    }
    return Promise.reject(error);
  },
);
