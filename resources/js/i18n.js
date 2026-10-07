import { ref, watch } from 'vue';
import georgian from './locales/ka.json';

const storageKey = 'dispatch.locale';
function savedLocale() {
  try {
    return localStorage.getItem(storageKey) === 'en' ? 'en' : 'ka';
  } catch {
    return 'ka';
  }
}
export const locale = ref(savedLocale());
export const intlLocale = () => (locale.value === 'ka' ? 'ka-GE' : 'en-GB');
export function setLocale(value) {
  if (!['ka', 'en'].includes(value)) return;
  locale.value = value;
  try {
    localStorage.setItem(storageKey, value);
  } catch {
    // The language still works when browser storage is unavailable.
  }
}
export function t(key, parameters = {}) {
  if (key == null) return '';
  const source = String(key);
  const removedRide = source.match(/^Removed from deleted ride #(\d+)$/);
  if (removedRide)
    return t('Removed from deleted ride #{id}', { id: removedRide[1] });
  const message = locale.value === 'ka' ? (georgian[source] ?? source) : source;
  return message.replace(/\{(\w+)\}/g, (match, name) =>
    Object.hasOwn(parameters, name) ? String(parameters[name]) : match,
  );
}
watch(
  locale,
  () => {
    document.documentElement.lang = locale.value;
    document.title = t('Dispatch · Order operations');
    document
      .querySelector('meta[name="description"]')
      ?.setAttribute('content', t('Dispatch · Order operations'));
  },
  { immediate: true },
);
