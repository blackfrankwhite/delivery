<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { coordinates } from '../utils';
import { locale, t } from '../i18n';

const props = defineProps({
  storage: Object,
  tasks: { type: Array, default: () => [] },
  plan: Object,
});
const emit = defineEmits(['select']);
const orderStatuses = {
  pending: { label: 'pending', color: '#475569' },
  assigned: { label: 'assigned', color: '#1d4ed8' },
  started: { label: 'In progress', color: '#b45309' },
  arrived: { label: 'arrived', color: '#7c3aed' },
  delivered: { label: 'delivered', color: '#047857' },
  failed: { label: 'failed', color: '#b91c1c' },
  cancelled: { label: 'cancelled', color: '#64748b' },
};
function orderStatus(task) {
  return orderStatuses[task.status] || orderStatuses.pending;
}
const container = ref(null),
  tileError = ref(false);
let map, layers, tiles, observer, boundsKey;
const touchMap = ref(false),
  panMap = ref(false);
function togglePan() {
  panMap.value = !panMap.value;
  container.value.style.touchAction = panMap.value ? 'none' : 'pan-y';
  if (panMap.value) map.dragging.enable();
  else map.dragging.disable();
}

const attribution = () =>
  import.meta.env.VITE_MAP_TILE_ATTRIBUTION ||
  `&copy; <a href="https://www.openstreetmap.org/copyright">${t('OpenStreetMap contributors')}</a>`;
function draw() {
  if (!map) return;
  layers.clearLayers();
  const bounds = [];
  const start = props.storage && coordinates(props.storage);
  if (start) {
    const marker = L.marker(start, {
      title: t('Storage start'),
      icon: L.divIcon({
        className: 'ride-map-pin ride-map-start',
        html: '<span>⌂</span>',
        iconSize: [48, 48],
        iconAnchor: [24, 24],
      }),
    }).addTo(layers);
    const popup = document.createElement('div');
    for (const value of [
      props.storage.name,
      props.storage.address,
      props.storage.instructions,
    ]) {
      if (!value) continue;
      const line = document.createElement('p');
      line.textContent = value;
      popup.append(line);
    }
    marker.bindPopup(popup);
    bounds.push(start);
  }
  props.tasks.forEach((task, index) => {
    const point = coordinates(task);
    if (!point) return;
    const marker = L.marker(point, {
      title: `${t('Stop {number}', { number: index + 1 })} · ${task.recipient_name} · ${t(orderStatus(task).label)}`,
      icon: L.divIcon({
        className: 'ride-map-pin',
        html: `<span style="background-color: ${orderStatus(task).color}">${index + 1}</span>`,
        iconSize: [44, 44],
        iconAnchor: [22, 22],
      }),
    }).addTo(layers);
    const label = document.createElement('span');
    label.textContent = `${task.reference_number} · ${task.recipient_name} · ${t(orderStatus(task).label)}`;
    const samePlace = props.tasks.filter(
      (other) => JSON.stringify(coordinates(other)) === JSON.stringify(point),
    );
    if (samePlace.length > 1) {
      const popup = document.createElement('div');
      const heading = document.createElement('p');
      heading.textContent = t('Orders at this location');
      popup.append(heading);
      for (const other of samePlace) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'block py-2 text-left font-semibold text-brand-700';
        button.textContent = `${props.tasks.indexOf(other) + 1}. ${other.reference_number} · ${other.recipient_name} · ${t(orderStatus(other).label)}`;
        button.addEventListener('click', () => emit('select', other.id));
        popup.append(button);
      }
      marker.bindTooltip(label).bindPopup(popup);
    } else marker.bindTooltip(label).on('click', () => emit('select', task.id));
    bounds.push(point);
  });
  if (props.plan?.geometry) {
    const road = L.geoJSON(props.plan.geometry, {
      style: { color: '#087f63', weight: 5, opacity: 0.85 },
      interactive: false,
    }).addTo(layers);
    if (road.getBounds().isValid())
      bounds.push(
        road.getBounds().getSouthWest(),
        road.getBounds().getNorthEast(),
      );
  }
  const key = JSON.stringify([
    start,
    props.tasks.map((task) => [task.id, coordinates(task)]),
    props.plan?.calculated_at,
  ]);
  if (bounds.length && key !== boundsKey) {
    map.fitBounds(L.latLngBounds(bounds), { padding: [35, 35], maxZoom: 16 });
    boundsKey = key;
  }
}
function localize() {
  if (!map) return;
  map.attributionControl.removeAttribution(tiles.options.attribution);
  tiles.options.attribution = attribution();
  map.attributionControl.addAttribution(tiles.options.attribution);
  for (const [selector, key] of [
    ['.leaflet-control-zoom-in', 'Zoom in'],
    ['.leaflet-control-zoom-out', 'Zoom out'],
  ]) {
    const control = container.value.querySelector(selector);
    control?.setAttribute('title', t(key));
    control?.setAttribute('aria-label', t(key));
  }
  draw();
}
onMounted(() => {
  map = L.map(container.value, {
    center: coordinates({
      latitude: import.meta.env.VITE_MAP_CENTER_LATITUDE ?? 41.7151,
      longitude: import.meta.env.VITE_MAP_CENTER_LONGITUDE ?? 44.8271,
    }) || [41.7151, 44.8271],
    zoom: 12,
    scrollWheelZoom: false,
    zoomControl: false,
  });
  touchMap.value = window.matchMedia('(pointer: coarse)').matches;
  if (touchMap.value) {
    map.dragging.disable();
    container.value.style.touchAction = 'pan-y';
  }
  L.control
    .zoom({ zoomInTitle: t('Zoom in'), zoomOutTitle: t('Zoom out') })
    .addTo(map);
  tiles = L.tileLayer(
    import.meta.env.VITE_MAP_TILE_URL ||
      'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    { maxZoom: 19, attribution: attribution() },
  )
    .on('tileerror', () => (tileError.value = true))
    .addTo(map);
  layers = L.featureGroup().addTo(map);
  observer = new ResizeObserver(() => map.invalidateSize());
  observer.observe(container.value);
  draw();
});
watch(() => [props.storage, props.tasks, props.plan], draw, { deep: true });
watch(locale, localize);
onUnmounted(() => {
  observer?.disconnect();
  map?.remove();
});
</script>
<template>
  <div class="ride-route-map min-w-0">
    <div
      v-if="touchMap"
      class="mb-3 flex flex-wrap items-center justify-between gap-2"
    >
      <span class="text-xs text-slate-500">
        {{ $t('Pinch to zoom. Enable map movement to pan.') }}
      </span>
      <button
        type="button"
        class="btn btn-secondary text-xs"
        :aria-pressed="panMap"
        @click="togglePan"
      >
        {{ $t(panMap ? 'Scroll page' : 'Move map') }}
      </button>
    </div>
    <div
      class="relative isolate overflow-hidden rounded-xl border border-slate-200"
    >
      <div
        ref="container"
        class="ride-map-canvas h-[380px] w-full sm:h-[460px]"
        role="region"
        :aria-label="$t('Ride directions map')"
      ></div>
    </div>
    <p class="mt-3 text-xs text-slate-500">
      {{
        $t(
          'The house pin is storage. Numbered pins are order stops. Click a stop to manage its order.',
        )
      }}
    </p>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600">
      <li
        v-for="(status, key) in orderStatuses"
        :key="key"
        class="flex items-center gap-1.5 capitalize"
      >
        <span
          class="h-3 w-3 shrink-0 rounded-full"
          :style="{ backgroundColor: status.color }"
          aria-hidden="true"
        ></span>
        {{ $t(status.label) }}
      </li>
    </ul>
    <p v-if="tileError" class="mt-3 text-xs text-amber-700" role="alert">
      {{
        $t(
          'Map tiles could not load. Your saved pin is retained; you can still enter coordinates or use navigation.',
        )
      }}
      <button
        class="font-semibold underline"
        @click="
          tileError = false;
          tiles.redraw();
        "
      >
        {{ $t('Retry map') }}
      </button>
    </p>
  </div>
</template>
<style>
.ride-route-map .leaflet-container {
  z-index: 0;
  font-family: inherit;
}
.ride-map-pin span {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border: 2px solid white;
  border-radius: 50%;
  background: #087f63;
  color: white;
  font-size: 13px;
  font-weight: 700;
  box-shadow: 0 2px 7px #0f172a44;
}
.ride-map-start span {
  width: 48px;
  height: 48px;
  background: #0f172a;
  font-size: 24px;
}
.ride-route-map .leaflet-control-attribution {
  font-size: 10px;
}
</style>
