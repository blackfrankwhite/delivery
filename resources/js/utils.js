import { intlLocale, t } from './i18n';
export const appTimezone = import.meta.env.VITE_APP_TIMEZONE || 'Asia/Tbilisi';
export function today() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: appTimezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type).value;
  return `${get('year')}-${get('month')}-${get('day')}`;
}
export function dateLabel(date) {
  return new Date(`${date}T12:00:00`).toLocaleDateString(intlLocale(), {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
export function timeLabel(task) {
  return task.time_from
    ? `${task.time_from.slice(0, 5)}${task.time_to ? ' – ' + task.time_to.slice(0, 5) : ''}`
    : t('Any time');
}
export function initials(name = '') {
  return name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('');
}
// Both persisted decimals (strings) and newly picked coordinates are supported.
export function coordinates(location) {
  if (
    [location.latitude, location.longitude].some(
      (value) => value == null || String(value).trim() === '',
    )
  )
    return null;
  const latitude = Number(location.latitude),
    longitude = Number(location.longitude);
  return Number.isFinite(latitude) &&
    Number.isFinite(longitude) &&
    Math.abs(latitude) <= 90 &&
    Math.abs(longitude) <= 180
    ? [latitude, longitude]
    : null;
}
export function mapLink(task) {
  const point = coordinates(task);
  const target = point
    ? point.join(',')
    : [task.address, task.city].filter(Boolean).join(', ');
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(target)}`;
}
export const statuses = [
  'pending',
  'assigned',
  'started',
  'arrived',
  'delivered',
  'failed',
  'cancelled',
];
export function transitions(status, driver = false) {
  const options = {
    assigned: ['started', 'failed'],
    started: ['arrived', 'failed'],
    arrived: ['delivered', 'failed'],
  };
  return [
    ...(options[status] || []),
    ...(!driver && !['delivered', 'failed', 'cancelled'].includes(status)
      ? ['cancelled']
      : []),
  ];
}
export const actionLabels = {
  started: 'Start order',
  arrived: 'Mark arrived',
  delivered: 'Mark delivered',
  failed: 'Order failed',
  cancelled: 'Cancel order',
};

export function orderDateLabel(order) {
  const from = order.date_from || order.delivery_date;
  const to = order.date_to || from;
  return from === to
    ? dateLabel(from)
    : `${dateLabel(from)} – ${dateLabel(to)}`;
}
