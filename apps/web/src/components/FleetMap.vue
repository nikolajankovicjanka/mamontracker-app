<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { DashboardMapVehicle } from '@/stores/dashboard'

const props = defineProps<{
  vehicles: DashboardMapVehicle[]
  expanded?: boolean
}>()

const router = useRouter()
const mapElement = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let initialBoundsApplied = false
let disposed = false
let resizeTimeout: number | null = null

const markers = new Map<number, { marker: L.Marker; online: boolean }>()
const validVehicles = computed(() => props.vehicles.filter((vehicle) =>
    typeof vehicle.lat === 'number'
    && typeof vehicle.lng === 'number'
    && Number.isFinite(vehicle.lat)
    && Number.isFinite(vehicle.lng)
    && Math.abs(vehicle.lat) <= 90
    && Math.abs(vehicle.lng) <= 180,
))

function createVehicleIcon(online: boolean) {
  const color = online ? '#2563eb' : '#ef4444'
  const glow = online ? 'rgba(37, 99, 235, 0.28)' : 'rgba(239, 68, 68, 0.28)'

  return L.divIcon({
    className: 'custom-fleet-div-icon',
    html: `
      <div style="position:relative;width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
        <span class="fleet-car-pulse" style="position:absolute;width:34px;height:34px;border-radius:9999px;background:${glow};"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="30" height="30"
          style="position:relative;z-index:2;filter:drop-shadow(0 6px 12px rgba(15,23,42,0.18));">
          <g>
            <rect x="12" y="26" width="40" height="16" rx="6" fill="${color}" />
            <path d="M20 26 L26 18 H40 L47 26 Z" fill="${color}" />
            <rect x="26" y="20" width="12" height="6" rx="2" fill="#dbeafe" />
            <circle cx="22" cy="44" r="5" fill="#0f172a" />
            <circle cx="42" cy="44" r="5" fill="#0f172a" />
            <circle cx="22" cy="44" r="2.2" fill="#cbd5e1" />
            <circle cx="42" cy="44" r="2.2" fill="#cbd5e1" />
            <rect x="14" y="30" width="5" height="3" rx="1" fill="#f8fafc" />
            <rect x="45" y="30" width="5" height="3" rx="1" fill="#f8fafc" />
          </g>
        </svg>
      </div>`,
    iconSize: [38, 38],
    iconAnchor: [19, 19],
    popupAnchor: [0, -18],
  })
}

function formatDateTime(value: string | null): string {
  const date = value ? new Date(value) : null
  if (!date || !Number.isFinite(date.getTime())) return '—'
  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  }).format(date)
}

function buildPopupContent(vehicle: DashboardMapVehicle): HTMLElement {
  const container = document.createElement('div')
  container.style.minWidth = '190px'

  const title = document.createElement('div')
  title.style.cssText = 'font-weight:700;color:#0f172a;margin-bottom:6px;'
  title.textContent = vehicle.name
  container.appendChild(title)

  const lines = [
    `Tablice: ${vehicle.license_plate || '—'}`,
    `Status: ${vehicle.online ? 'Online' : 'Offline'}`,
    `Zadnja pozicija: ${formatDateTime(vehicle.last_position_at)}`,
  ]
  if (typeof vehicle.last_speed_kph === 'number' && Number.isFinite(vehicle.last_speed_kph)) {
    lines.push(`Brzina: ${Math.round(vehicle.last_speed_kph)} km/h`)
  }

  for (const text of lines) {
    const row = document.createElement('div')
    row.style.cssText = 'font-size:13px;color:#475569;margin-bottom:4px;'
    row.textContent = text
    container.appendChild(row)
  }
  return container
}

function renderMarkers(): void {
  if (!map || !markersLayer || disposed) return

  const visibleIds = new Set(validVehicles.value.map((vehicle) => vehicle.id))
  for (const [id, entry] of markers) {
    if (!visibleIds.has(id)) {
      entry.marker.off()
      markersLayer.removeLayer(entry.marker)
      markers.delete(id)
    }
  }

  for (const vehicle of validVehicles.value) {
    const entry = markers.get(vehicle.id)

    if (entry) {
      const position = entry.marker.getLatLng()
      if (position.lat !== vehicle.lat || position.lng !== vehicle.lng) {
        entry.marker.setLatLng([vehicle.lat, vehicle.lng])
      }
      if (entry.online !== vehicle.online) {
        entry.marker.setIcon(createVehicleIcon(vehicle.online))
        entry.online = vehicle.online
      }
      const element = entry.marker.getElement()
      if (element) element.title = `${vehicle.name} (${vehicle.license_plate})`
      entry.marker.setPopupContent(buildPopupContent(vehicle))
      continue
    }

    const marker = L.marker([vehicle.lat, vehicle.lng], {
      icon: createVehicleIcon(vehicle.online),
      title: `${vehicle.name} (${vehicle.license_plate})`,
    }).bindPopup(buildPopupContent(vehicle), { autoPan: false })

    marker.on('dblclick', () => {
      void router.push({ name: 'vehicle-show', params: { id: vehicle.id } })
    })
    marker.addTo(markersLayer)
    markers.set(vehicle.id, { marker, online: vehicle.online })
  }

  // Fit once when the first valid positions arrive; updates preserve user zoom/pan.
  if (!initialBoundsApplied && validVehicles.value.length) {
    const first = validVehicles.value[0]
    if (validVehicles.value.length === 1 && first) {
      map.setView([first.lat, first.lng], 12)
    } else {
      const bounds = L.latLngBounds(
          validVehicles.value.map((vehicle) => L.latLng(vehicle.lat, vehicle.lng)),
      )
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 })
    }
    initialBoundsApplied = true
  }
}

function scheduleResize(delay: number): void {
  if (resizeTimeout !== null) window.clearTimeout(resizeTimeout)
  resizeTimeout = window.setTimeout(() => {
    resizeTimeout = null
    if (!disposed) map?.invalidateSize({ pan: false })
  }, delay)
}

watch(() => props.vehicles, renderMarkers, { deep: true, flush: 'post' })
watch(() => props.expanded, async () => {
  await nextTick()
  if (!disposed) scheduleResize(350)
})

onMounted(async () => {
  await nextTick()
  if (disposed || !mapElement.value) return

  map = L.map(mapElement.value, {
    zoomControl: true,
    attributionControl: true,
  }).setView([44.5, 18.7], 6)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)
  renderMarkers()
  scheduleResize(80)
})

onBeforeUnmount(() => {
  disposed = true
  if (resizeTimeout !== null) window.clearTimeout(resizeTimeout)
  for (const entry of markers.values()) entry.marker.off()
  markers.clear()
  map?.remove()
  map = null
  markersLayer = null
})
</script>

<template>
  <div class="relative h-full w-full">
    <div
        v-if="!validVehicles.length"
        class="absolute inset-0 z-[500] flex items-center justify-center bg-slate-50/85 px-3 text-center text-sm text-slate-500"
    >
      Pozicije vozila još nisu dostupne.
    </div>
    <div ref="mapElement" class="h-full w-full rounded-3xl" />
  </div>
</template>

<style scoped>
:deep(.leaflet-container) {
  height: 100%;
  width: 100%;
  border-radius: 1.5rem;
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

:deep(.custom-fleet-div-icon) {
  background: transparent !important;
  border: 0 !important;
}

:deep(.fleet-car-pulse) {
  animation: carPulse 2s ease-out infinite;
}

@keyframes carPulse {
  0% { transform: scale(0.7); opacity: 0.8; }
  70%, 100% { transform: scale(1.8); opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  :deep(.fleet-car-pulse) { animation: none; }
}
</style>
