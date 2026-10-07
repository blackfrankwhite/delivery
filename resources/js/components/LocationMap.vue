<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import L from 'leaflet';
import { locale, t } from '../i18n';
import 'leaflet/dist/leaflet.css';
import { coordinates } from '../utils';
import Icon from './Icon.vue';

const props = defineProps({
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
  editable: Boolean,
  label: { type: String, default: 'Destination map' },
});
const emit = defineEmits(['update:latitude', 'update:longitude']);
const container = ref(null);
const loading = ref(true);
const tileError = ref(false);
const locationError = ref('');
const locating = ref(false);
const point = computed(() => coordinates(props));
const defaultCenter = coordinates({
  latitude: import.meta.env.VITE_MAP_CENTER_LATITUDE ?? 41.7151,
  longitude: import.meta.env.VITE_MAP_CENTER_LONGITUDE ?? 44.8271,
}) || [41.7151, 44.8271];
let map;
const touchMap = ref(false),
  panMap = ref(false);
function togglePan() {
  panMap.value = !panMap.value;
  container.value.style.touchAction = panMap.value ? 'none' : 'pan-y';
  if (panMap.value) map.dragging.enable();
  else map.dragging.disable();
}

let marker;
let tiles;
let resizeObserver;

function defaultAttribution() {
  return `&copy; <a href="https://www.openstreetmap.org/copyright">${t('OpenStreetMap contributors')}</a>`;
}
function localizeMap() {
  if (!map) return;
  const element = marker?.getElement();
  if (element) {
    element.title = t(
      props.editable ? 'Destination pin — drag to move' : 'Order destination',
    );
    element.setAttribute('aria-label', t('Order destination'));
  }
  for (const [selector, key] of [
    ['.leaflet-control-zoom-in', 'Zoom in'],
    ['.leaflet-control-zoom-out', 'Zoom out'],
  ]) {
    const control = container.value.querySelector(selector);
    control?.setAttribute('title', t(key));
    control?.setAttribute('aria-label', t(key));
  }
  if (!import.meta.env.VITE_MAP_TILE_ATTRIBUTION) {
    map.attributionControl.removeAttribution(tiles.options.attribution);
    tiles.options.attribution = defaultAttribution();
    map.attributionControl.addAttribution(tiles.options.attribution);
  }
}
function select(latlng) {
  if (!props.editable) return;
  emit('update:latitude', Number(latlng.lat.toFixed(7)));
  // Keep longitudes valid when panning across the antimeridian.
  emit('update:longitude', Number(latlng.wrap().lng.toFixed(7)));
  locationError.value = '';
}
function showPoint() {
  if (!map) return;
  if (!point.value) {
    marker?.remove();
    marker = null;
    return;
  }
  if (marker) marker.setLatLng(point.value);
  else {
    marker = L.marker(point.value, {
      draggable: props.editable,
      title: t(
        props.editable ? 'Destination pin — drag to move' : 'Order destination',
      ),
      alt: t('Order destination'),
      icon: L.divIcon({
        className: 'destination-pin',
        html: '<span class="destination-pin-body"><span></span></span>',
        iconSize: [32, 40],
        iconAnchor: [16, 40],
      }),
    }).addTo(map);
    if (props.editable) marker.on('dragend', () => select(marker.getLatLng()));
  }
  localizeMap();
  map.setView(point.value, Math.max(map.getZoom(), 16));
}
function clear() {
  emit('update:latitude', null);
  emit('update:longitude', null);
}
function locate() {
  locating.value = true;
  locationError.value = '';
  map.locate({ timeout: 10000, enableHighAccuracy: true });
}
function retry() {
  tileError.value = false;
  loading.value = true;
  tiles.redraw();
}

onMounted(() => {
  map = L.map(container.value, {
    center: point.value || defaultCenter,
    zoom: point.value ? 16 : 12,
    scrollWheelZoom: false,
    worldCopyJump: true,
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
    {
      maxZoom: 19,
      attribution:
        import.meta.env.VITE_MAP_TILE_ATTRIBUTION || defaultAttribution(),
    },
  );
  tiles.on('tileload', () => {
    loading.value = false;
  });
  tiles.on('load', () => {
    loading.value = false;
  });
  tiles.on('tileerror', () => {
    loading.value = false;
    tileError.value = true;
  });
  tiles.addTo(map);
  if (props.editable) {
    map.on('click', (event) => select(event.latlng));
    map.on('locationfound', (event) => {
      locating.value = false;
      select(event.latlng);
    });
    map.on('locationerror', () => {
      locating.value = false;
      locationError.value =
        'Could not get your location. Allow location access or place the pin on the map.';
    });
  }
  showPoint();
  resizeObserver = new ResizeObserver(() => map.invalidateSize());
  resizeObserver.observe(container.value);
});
watch(point, showPoint);
watch(locale, localizeMap);
onUnmounted(() => {
  resizeObserver?.disconnect();
  map?.stopLocate();
  map?.remove();
});
</script>

<template>
  <section class="location-map min-w-0">
    <p v-if="editable" class="mb-3 text-xs leading-5 text-slate-500">
      {{
        $t(
          'Click the map to place the destination pin, then drag it to the exact entrance. Keep the written address and access instructions above.',
        )
      }}
    </p>
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
        class="map-canvas h-72 w-full sm:h-80"
        role="region"
        :aria-label="$t(label)"
      ></div>
      <div
        v-if="loading"
        class="pointer-events-none absolute top-3 right-3 z-[500] rounded-lg bg-white/95 px-3 py-2 text-xs text-slate-500 shadow-sm"
        role="status"
      >
        {{ $t('Loading map…') }}
      </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-slate-500" aria-live="polite">
        <span
          v-if="point"
          class="inline-flex items-center gap-1.5 text-brand-700"
        >
          <Icon name="MapPin" :size="14" />
          {{ $t('Location pinned ·') }} {{ point[0].toFixed(6) }},
          {{ point[1].toFixed(6) }}
        </span>
        <span v-else>{{ $t('No location pinned yet.') }}</span>
      </p>
      <div class="flex flex-wrap gap-2">
        <button
          v-if="editable"
          type="button"
          class="btn btn-secondary !min-h-8 !px-2.5 !py-1 !text-xs"
          @click="select(map.getCenter())"
        >
          {{ $t('Pin map center') }}
        </button>
        <button
          v-if="point"
          type="button"
          class="btn btn-secondary !min-h-8 !px-2.5 !py-1 !text-xs"
          @click="showPoint"
        >
          {{ $t('Show pin') }}
        </button>
        <button
          v-if="editable && point"
          type="button"
          class="btn btn-secondary !min-h-8 !px-2.5 !py-1 !text-xs"
          @click="clear"
        >
          {{ $t('Clear pin') }}
        </button>
        <button
          v-if="editable"
          type="button"
          class="btn btn-secondary !min-h-8 !px-2.5 !py-1 !text-xs"
          :disabled="locating"
          @click="locate"
        >
          {{ $t(locating ? 'Locating…' : 'Use my location') }}
        </button>
      </div>
    </div>
    <div
      v-if="tileError"
      class="mt-3 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800"
      role="alert"
    >
      {{
        $t(
          'Map tiles could not load. Your saved pin is retained; you can still enter coordinates or use navigation.',
        )
      }}
      <button type="button" class="ml-1 font-semibold underline" @click="retry">
        {{ $t('Retry map') }}
      </button>
    </div>
    <p v-if="locationError" class="mt-3 text-xs text-red-600" role="alert">
      {{ $t(locationError) }}
    </p>
  </section>
</template>

<style>
/* Keep Leaflet controls below the application's dialogs and navigation. */
.location-map .map-canvas {
  z-index: 0;
  font-family: inherit;
}
.location-map .leaflet-control-attribution {
  font-size: 10px;
}
.location-map .leaflet-control-zoom a {
  color: #475569;
}
.destination-pin {
  background: transparent;
  border: 0;
}
.destination-pin-body {
  display: flex;
  width: 30px;
  height: 30px;
  margin: 1px;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  border-radius: 50% 50% 50% 0;
  background: #087f63;
  transform: rotate(-45deg);
  box-shadow: 0 2px 6px #0f172a55;
}
.destination-pin-body > span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: white;
}
</style>
