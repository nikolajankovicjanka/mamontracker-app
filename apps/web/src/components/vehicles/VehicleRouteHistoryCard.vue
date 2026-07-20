<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/lib/axios'

type RoutePoint = {
  id: number | null
  latitude: number
  longitude: number
  speed_knots: number | null
  speed_kph: number | null
  course: number | null
  altitude: number | null
  address: string | null
  accuracy: number | null
  device_time: string | null
  fix_time: string | null
  server_time: string | null
  attributes: Record<string, unknown>
}

type RouteHistoryResponse = {
  data: {
    vehicle: {
      id: number
      name: string
      license_plate: string | null
    }
    device: {
      id: number
      device_name: string
      imei: string | null
      traccar_device_id: number | null
    }
    from: string
    to: string
    points_count: number
    start_point: RoutePoint | null
    end_point: RoutePoint | null
    points: RoutePoint[]
  }
}

const props = defineProps<{
  vehicleId: number
}>()

const loading = ref(false)
const errorMessage = ref('')
const debugMessage = ref('')
const routeData = ref<RouteHistoryResponse['data'] | null>(null)

const mapElement = ref<HTMLDivElement | null>(null)

let map: L.Map | null = null
let routeLayer: L.FeatureGroup | null = null

const form = ref({
  from: '',
  to: '',
})

const hasPoints = computed(() => (routeData.value?.points?.length ?? 0) > 0)

function getTodayRange() {
  const now = new Date()
  const start = new Date(now)
  start.setHours(0, 0, 0, 0)

  return {
    from: toLocalDateTimeInputValue(start),
    to: toLocalDateTimeInputValue(now),
  }
}

function getLast7DaysRange() {
  const now = new Date()
  const start = new Date(now)
  start.setDate(start.getDate() - 7)

  return {
    from: toLocalDateTimeInputValue(start),
    to: toLocalDateTimeInputValue(now),
  }
}

function toLocalDateTimeInputValue(date: Date) {
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

function toIsoString(value: string) {
  return new Date(value).toISOString()
}

function formatDateTime(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

function buildPopup(point: RoutePoint, label: string) {
  return `
    <div style="min-width: 190px;">
      <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">
        ${label}
      </div>
      <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
        Vrijeme: ${formatDateTime(point.fix_time)}
      </div>
      <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
        Brzina: ${point.speed_kph ?? 0} km/h
      </div>
      <div style="font-size: 13px; color: #475569;">
        Lat/Lng: ${point.latitude}, ${point.longitude}
      </div>
    </div>
  `
}

function createMarkerIcon(type: 'start' | 'end') {
  const color = type === 'start' ? '#16a34a' : '#dc2626'
  const label = type === 'start' ? 'S' : 'E'

  return L.divIcon({
    className: 'route-history-marker',
    html: `
      <div style="
        width: 30px;
        height: 30px;
        border-radius: 9999px;
        background: ${color};
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        border: 3px solid white;
        box-shadow: 0 8px 20px rgba(15,23,42,0.18);
      ">
        ${label}
      </div>
    `,
    iconSize: [30, 30],
    iconAnchor: [15, 15],
    popupAnchor: [0, -18],
  })
}

function initMap() {
  if (!mapElement.value || map) return

  map = L.map(mapElement.value, {
    zoomControl: true,
    attributionControl: true,
    preferCanvas: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)

  routeLayer = L.featureGroup().addTo(map)
  map.setView([44.756, 19.216], 12)
}

function clearRoute() {
  routeLayer?.clearLayers()
}

async function renderRoute() {
  if (!map || !routeLayer) {
    debugMessage.value = 'Mapa nije inicijalizovana.'
    return
  }

  const activeMap = map
  const activeRouteLayer = routeLayer

  clearRoute()
  await nextTick()
  activeMap.invalidateSize()

  const points = routeData.value?.points ?? []

  if (!points.length) {
    debugMessage.value = 'Nema tačaka za prikaz.'
    activeMap.setView([44.756, 19.216], 12)
    return
  }

  const validPoints = points.filter(
      (point) =>
          typeof point.latitude === 'number' &&
          typeof point.longitude === 'number' &&
          !Number.isNaN(point.latitude) &&
          !Number.isNaN(point.longitude),
  )

  if (!validPoints.length) {
    debugMessage.value = 'Nema validnih GPS tačaka.'
    activeMap.setView([44.756, 19.216], 12)
    return
  }

  const latLngs = validPoints.map(
      (point) => [point.latitude, point.longitude] as [number, number],
  )

  debugMessage.value = `Renderujem ${latLngs.length} tačaka.`

  const polyline = L.polyline(latLngs, {
    color: '#2563eb',
    weight: 6,
    opacity: 1,
  }).addTo(activeRouteLayer)

  validPoints.forEach((point) => {
    L.circleMarker([point.latitude, point.longitude], {
      radius: 3,
      color: '#1d4ed8',
      weight: 1,
      opacity: 1,
      fillColor: '#60a5fa',
      fillOpacity: 0.9,
    }).addTo(activeRouteLayer)
  })

  const startPoint = points.at(0)
  const endPoint = points.at(-1)

  if (!startPoint || !endPoint) {
    return
  }

  L.marker([startPoint.latitude, startPoint.longitude], {
    icon: createMarkerIcon('start'),
  })
      .bindPopup(buildPopup(startPoint, 'Početak rute'))
      .addTo(activeRouteLayer)

  L.marker([endPoint.latitude, endPoint.longitude], {
    icon: createMarkerIcon('end'),
  })
      .bindPopup(buildPopup(endPoint, 'Kraj rute'))
      .addTo(activeRouteLayer)

  const bounds = activeRouteLayer.getBounds()

  setTimeout(() => {
    map?.invalidateSize()

    if (bounds.isValid()) {
      map?.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom: 17,
      })
    } else {
      map?.setView([startPoint.latitude, startPoint.longitude], 17)
    }
  }, 200)
}

async function fetchRouteHistory() {
  loading.value = true
  errorMessage.value = ''
  debugMessage.value = 'Učitavam rutu...'

  try {
    const { data } = await api.get<RouteHistoryResponse>(
        `/api/vehicles/${props.vehicleId}/route-history`,
        {
          params: {
            from: toIsoString(form.value.from),
            to: toIsoString(form.value.to),
          },
        },
    )

    routeData.value = data.data
    await renderRoute()
  } catch (error: any) {
    routeData.value = null
    clearRoute()
    errorMessage.value =
        error?.response?.data?.message ?? 'Ruta nije mogla biti učitana.'
    debugMessage.value = 'Greška pri učitavanju rute.'
  } finally {
    loading.value = false
  }
}

async function applyToday() {
  form.value = getTodayRange()
  await fetchRouteHistory()
}

async function applyLast7Days() {
  form.value = getLast7DaysRange()
  await fetchRouteHistory()
}

onMounted(async () => {
  initMap()
  form.value = getTodayRange()
  await fetchRouteHistory()

  setTimeout(async () => {
    map?.invalidateSize()
    await renderRoute()
  }, 300)
})

watch(
    () => props.vehicleId,
    async () => {
      form.value = getTodayRange()
      await fetchRouteHistory()
    },
)

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    routeLayer = null
  }
})
</script>

<template>
  <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Istorija kretanja</h2>
        <p class="mt-1 text-sm text-slate-500">
          Pregled rute vozila za odabrani vremenski period.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            @click="applyToday"
        >
          Danas
        </button>

        <button
            type="button"
            class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            @click="applyLast7Days"
        >
          Zadnjih 7 dana
        </button>
      </div>
    </div>

    <form class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1fr_auto]" @submit.prevent="fetchRouteHistory">
      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Od</label>
        <input
            v-model="form.from"
            type="datetime-local"
            class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
        />
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Do</label>
        <input
            v-model="form.to"
            type="datetime-local"
            class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
        />
      </div>

      <div class="flex items-end">
        <button
            type="submit"
            class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
            :disabled="loading || !form.from || !form.to"
        >
          {{ loading ? 'Učitavanje...' : 'Prikaži rutu' }}
        </button>
      </div>
    </form>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
      {{ debugMessage }}
    </div>

    <div
        v-if="errorMessage"
        class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ errorMessage }}
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Broj tačaka</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ routeData?.points_count ?? 0 }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Početak rute</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatDateTime(routeData?.start_point?.fix_time ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Kraj rute</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatDateTime(routeData?.end_point?.fix_time ?? null) }}
        </div>
      </div>
    </div>

    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-2">
      <div
          ref="mapElement"
          style="height: 420px; width: 100%; min-height: 420px; display: block;"
      />
    </div>

    <div
        v-if="!hasPoints && !loading"
        class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500"
    >
      Nema GPS tačaka za odabrani period.
    </div>
  </div>
</template>

<style scoped>
:deep(.leaflet-container) {
  height: 420px !important;
  width: 100% !important;
  min-height: 420px !important;
  display: block !important;
  font-family: inherit;
  background: #eaf4ff;
}

:deep(.leaflet-control-zoom),
:deep(.leaflet-control-attribution) {
  border: 0 !important;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08) !important;
}

:deep(.leaflet-popup-content-wrapper) {
  border-radius: 1rem;
}

:deep(.route-history-marker) {
  background: transparent !important;
  border: 0 !important;
}
</style>