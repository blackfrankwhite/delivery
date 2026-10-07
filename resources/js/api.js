import axios from 'axios';
import { locale } from './i18n';
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || window.location.origin,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
});
api.interceptors.request.use((config) => {
  config.headers['Accept-Language'] = locale.value;
  return config;
});
export function errorMessage(error) {
  const d = error.response?.data;
  return (
    Object.values(d?.errors || {})
      .flat()
      .join(' ') ||
    d?.message ||
    'Unable to connect. Check your connection and try again.'
  );
}
export async function allPages(path, params = {}) {
  let page = 1,
    rows = [],
    last = 1;
  do {
    const { data } = await api.get(path, {
      params: { ...params, page, per_page: 100 },
    });
    rows.push(...data.data);
    last = data.meta?.last_page || 1;
    page++;
  } while (page <= last);
  return rows;
}
