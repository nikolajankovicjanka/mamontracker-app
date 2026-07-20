<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/lib/axios'
import type { VehicleItem, VehicleTelemetry } from '@/stores/vehicles'

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
  vehicle: VehicleItem
}>()

const activeTab = ref<'current' | 'history'>('current')
const loading = ref(false)
const errorMessage = ref('')
const debugMessage = ref('')

const routeData = ref<RouteHistoryResponse['data'] | null>(null)

const mapElement = ref<HTMLDivElement | null>(null)
let map: L.Map | null = null
let layerGroup: L.FeatureGroup | null = null

const form = ref({
  from: '',
  to: '',
})

const telemetry = computed<VehicleTelemetry | null>(() => props.vehicle.gps_device?.telemetry ?? null)

const hasCurrentLocation = computed(
    () =>
        props.vehicle.last_known_lat !== null &&
        props.vehicle.last_known_lng !== null,
)

const hasRoutePoints = computed(() => (routeData.value?.points?.length ?? 0) > 0)

const googleMapsUrl = computed(() => {
  if (!hasCurrentLocation.value) return null

  return `https://www.google.com/maps?q=${props.vehicle.last_known_lat},${props.vehicle.last_known_lng}`
})

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

function formatNumber(value: number | null | undefined, digits = 0) {
  if (value === null || value === undefined) return '—'

  return new Intl.NumberFormat('sr-Latn-RS', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

function gpsStatusBadgeClass(isActive: boolean | undefined) {
  return isActive
      ? 'bg-emerald-50 text-emerald-700'
      : 'bg-rose-50 text-rose-700'
}

function gpsStatusLabel(isActive: boolean | undefined) {
  return isActive ? 'Online' : 'Offline'
}

function ignitionBadgeClass(value: boolean | null | undefined) {
  return value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'
}

function ignitionLabel(value: boolean | null | undefined) {
  return value ? 'Uključen' : 'Isključen'
}

function motionBadgeClass(value: boolean | null | undefined) {
  return value ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700'
}

function motionLabel(value: boolean | null | undefined) {
  return value ? 'U pokretu' : 'Miruje'
}

function fixBadgeClass(value: boolean | null | undefined) {
  return value ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
}

function fixLabel(value: boolean | null | undefined) {
  return value ? 'Validan GPS' : 'Nevalidan GPS'
}

function createMarkerIcon(type: 'current' | 'start' | 'end') {
  const config =
      type === 'current'
          ? { color: '#2563eb', label: 'V' }
          : type === 'start'
              ? { color: '#16a34a', label: 'S' }
              : { color: '#dc2626', label: 'E' }

  return L.divIcon({
    className: 'tracking-map-marker',
    html: `
      <div style="
        width: 30px;
        height: 30px;
        border-radius: 9999px;
        background: ${config.color};
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        border: 3px solid white;
        box-shadow: 0 8px 20px rgba(15,23,42,0.18);
      ">
        ${config.label}
      </div>
    `,
    iconSize: [30, 30],
    iconAnchor: [15, 15],
    popupAnchor: [0, -18],
  })
}

function buildPopup(title: string, rows: Array<[string, string]>) {
  const content = rows
      .map(
          ([label, value]) =>
              `<div style="font-size:13px;color:#475569;margin-bottom:4px;"><strong style="color:#0f172a;">${label}:</strong> ${value}</div>`,
      )
      .join('')

  return `
    <div style="min-width: 200px;">
      <div style="font-weight:700;color:#0f172a;margin-bottom:8px;">${title}</div>
      ${content}
    </div>
  `
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

  layerGroup = L.featureGroup().addTo(map)
  map.setView([44.756, 19.216], 12)
}

function clearMap() {
  layerGroup?.clearLayers()
}

async function renderCurrentLocation() {
  if (!map || !layerGroup) return

  clearMap()
  await nextTick()
  map.invalidateSize()

  if (!hasCurrentLocation.value) {
    debugMessage.value = 'Nema trenutne lokacije za prikaz.'
    map.setView([44.756, 19.216], 12)
    return
  }

  const lat = props.vehicle.last_known_lat as number
  const lng = props.vehicle.last_known_lng as number

  L.marker([lat, lng], {
    icon: createMarkerIcon('current'),
  })
      .bindPopup(
          buildPopup(props.vehicle.name, [
            ['Tablice', props.vehicle.license_plate || '—'],
            ['Zadnja pozicija', formatDateTime(props.vehicle.last_position_at)],
            ['Brzina', `${props.vehicle.last_speed_kph ?? 0} km/h`],
          ]),
      )
      .addTo(layerGroup)

  L.circleMarker([lat, lng], {
    radius: 10,
    color: '#2563eb',
    weight: 2,
    opacity: 1,
    fillColor: '#93c5fd',
    fillOpacity: 0.35,
  }).addTo(layerGroup)

  debugMessage.value = 'Prikazana trenutna lokacija.'

  setTimeout(() => {
    map?.invalidateSize()
    map?.setView([lat, lng], 15)
  }, 120)
}

async function renderRouteHistory() {
  if (!map || !layerGroup) return

  const activeMap = map
  const activeLayerGroup = layerGroup

  clearMap()
  await nextTick()
  activeMap.invalidateSize()

  const points = routeData.value?.points ?? []

  if (!points.length) {
    debugMessage.value = 'Nema tačaka istorije za prikaz.'
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
    debugMessage.value = 'Istorija nema validne GPS tačke.'
    activeMap.setView([44.756, 19.216], 12)
    return
  }

  const latLngs = validPoints.map(
      (point) => [point.latitude, point.longitude] as [number, number],
  )

  const polyline = L.polyline(latLngs, {
    color: '#2563eb',
    weight: 6,
    opacity: 1,
  }).addTo(activeLayerGroup)

  validPoints.forEach((point) => {
    L.circleMarker([point.latitude, point.longitude], {
      radius: 3,
      color: '#1d4ed8',
      weight: 1,
      opacity: 1,
      fillColor: '#60a5fa',
      fillOpacity: 0.9,
    }).addTo(activeLayerGroup)
  })

  const startPoint = validPoints.at(0)
  const endPoint = validPoints.at(-1)

  if (!startPoint || !endPoint) {
    debugMessage.value = 'Istorija nema početnu ili krajnju GPS tačku.'
    return
  }

  L.marker([startPoint.latitude, startPoint.longitude], {
    icon: createMarkerIcon('start'),
  })
      .bindPopup(
          buildPopup('Početak rute', [
            ['Vrijeme', formatDateTime(startPoint.fix_time)],
            ['Brzina', `${startPoint.speed_kph ?? 0} km/h`],
          ]),
      )
      .addTo(activeLayerGroup)

  L.marker([endPoint.latitude, endPoint.longitude], {
    icon: createMarkerIcon('end'),
  })
      .bindPopup(
          buildPopup('Kraj rute', [
            ['Vrijeme', formatDateTime(endPoint.fix_time)],
            ['Brzina', `${endPoint.speed_kph ?? 0} km/h`],
          ]),
      )
      .addTo(activeLayerGroup)

  debugMessage.value = `Renderujem ${latLngs.length} tačaka istorije.`

  const bounds = polyline.getBounds()

  setTimeout(() => {
    map?.invalidateSize()

    if (bounds.isValid()) {
      map?.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom: 17,
      })
    } else {
      map?.setView([startPoint.latitude, startPoint.longitude], 16)
    }
  }, 150)
}

async function fetchRouteHistory() {
  loading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<RouteHistoryResponse>(
        `/api/vehicles/${props.vehicle.id}/route-history`,
        {
          params: {
            from: toIsoString(form.value.from),
            to: toIsoString(form.value.to),
          },
        },
    )

    routeData.value = data.data
    await renderRouteHistory()
  } catch (error: any) {
    routeData.value = null
    clearMap()
    errorMessage.value =
        error?.response?.data?.message ?? 'Ruta nije mogla biti učitana.'
    debugMessage.value = 'Greška pri učitavanju istorije.'
  } finally {
    loading.value = false
  }
}

async function activateCurrentTab() {
  activeTab.value = 'current'
  await renderCurrentLocation()
}

async function activateHistoryTab() {
  activeTab.value = 'history'
  if (!routeData.value) {
    await fetchRouteHistory()
  } else {
    await renderRouteHistory()
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
  await renderCurrentLocation()

  setTimeout(async () => {
    map?.invalidateSize()
    await renderCurrentLocation()
  }, 250)
})

watch(
    () => props.vehicle.id,
    async () => {
      form.value = getTodayRange()
      routeData.value = null
      activeTab.value = 'current'
      await renderCurrentLocation()
    },
)

watch(
    () => [
      props.vehicle.last_known_lat,
      props.vehicle.last_known_lng,
      props.vehicle.last_position_at,
      props.vehicle.last_speed_kph,
    ],
    async () => {
      if (activeTab.value === 'current') {
        await renderCurrentLocation()
      }
    },
)

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    layerGroup = null
  }
})
</script>

<template>
  <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">GPS status & tracking</h2>
        <p class="mt-1 text-sm text-slate-500">
          Trenutna lokacija i istorija kretanja vozila na jednoj mapi.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="gpsStatusBadgeClass(vehicle.gps_device?.is_active)"
        >
          {{ gpsStatusLabel(vehicle.gps_device?.is_active) }}
        </span>
        <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="ignitionBadgeClass(telemetry?.ignition)"
        >
          Motor: {{ ignitionLabel(telemetry?.ignition) }}
        </span>
        <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="motionBadgeClass(telemetry?.motion)"
        >
          Kretanje: {{ motionLabel(telemetry?.motion) }}
        </span>
        <span
            class="rounded-full px-2.5 py-1 text-xs font-medium"
            :class="fixBadgeClass(telemetry?.valid_fix)"
        >
          GPS: {{ fixLabel(telemetry?.valid_fix) }}
        </span>
      </div>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
      <button
          type="button"
          class="rounded-xl px-4 py-2 text-sm font-medium"
          :class="
          activeTab === 'current'
            ? 'bg-slate-900 text-white'
            : 'border border-slate-300 text-slate-700 hover:bg-slate-100'
        "
          @click="activateCurrentTab"
      >
        Trenutna lokacija
      </button>

      <button
          type="button"
          class="rounded-xl px-4 py-2 text-sm font-medium"
          :class="
          activeTab === 'history'
            ? 'bg-slate-900 text-white'
            : 'border border-slate-300 text-slate-700 hover:bg-slate-100'
        "
          @click="activateHistoryTab"
      >
        Istorija kretanja
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Trenutna brzina</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.speed_kph ?? vehicle.last_speed_kph ?? 0, 0) }} km/h
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Zadnja pozicija</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatDateTime(vehicle.last_position_at) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Last sync</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatDateTime(vehicle.gps_device?.last_sync_at ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Traccar device ID</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ vehicle.gps_device?.traccar_device_id ?? '—' }}
        </div>
      </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Sateliti</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.satellites ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">RSSI signal</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.rssi ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Napon</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.power_voltage ?? null, 2) }} V
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Battery current</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.battery_current ?? null) }}
        </div>
      </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Odometer</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.odometer ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Ukupna distanca</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.total_distance ?? null, 0) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Engine hours</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ formatNumber(telemetry?.engine_hours ?? null) }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Fuel level</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ telemetry?.oem_fuel_level ?? '—' }}
        </div>
      </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Telemetry VIN</div>
        <div class="mt-1 break-all font-semibold text-slate-900">
          {{ telemetry?.vin || '—' }}
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">Operator code</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ telemetry?.operator_code ?? '—' }}
        </div>
      </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <div class="text-sm text-slate-500">Latitude</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ vehicle.last_known_lat ?? '—' }}
        </div>
      </div>

      <div>
        <div class="text-sm text-slate-500">Longitude</div>
        <div class="mt-1 font-semibold text-slate-900">
          {{ vehicle.last_known_lng ?? '—' }}
        </div>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <a
          v-if="googleMapsUrl"
          :href="googleMapsUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
      >
        Otvori u Google Maps
      </a>
    </div>

    <div v-if="activeTab === 'history'" class="mt-6">
      <div class="mb-4 flex flex-wrap gap-2">
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

      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
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
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
      {{ debugMessage }}
    </div>

    <div
        v-if="errorMessage"
        class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ errorMessage }}
    </div>

    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-2">
      <div
          ref="mapElement"
          style="height: 420px; width: 100%; min-height: 420px; display: block;"
      />
    </div>

    <div
        v-if="activeTab === 'history' && !hasRoutePoints && !loading"
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

:deep(.tracking-map-marker) {
  background: transparent !important;
  border: 0 !important;
}
</style>