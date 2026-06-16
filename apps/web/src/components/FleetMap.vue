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

const validVehicles = computed(() =>
    props.vehicles.filter(
        (vehicle) =>
            typeof vehicle.lat === 'number' &&
            typeof vehicle.lng === 'number' &&
            !Number.isNaN(vehicle.lat) &&
            !Number.isNaN(vehicle.lng),
    ),
)

function createVehicleIcon(online: boolean) {
  const color = online ? '#2563eb' : '#ef4444' // plavo online, crveno offline
  const glow = online ? 'rgba(37, 99, 235, 0.28)' : 'rgba(239, 68, 68, 0.28)'

  return L.divIcon({
    className: 'custom-fleet-div-icon',
    html: `
      <div style="position: relative; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
        <span
          style="
            position: absolute;
            width: 34px;
            height: 34px;
            border-radius: 9999px;
            background: ${glow};
            animation: carPulse 2s ease-out infinite;
          "
        ></span>

        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 64 64"
          width="30"
          height="30"
          style="position: relative; z-index: 2; filter: drop-shadow(0 6px 12px rgba(15,23,42,0.18));"
        >
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
      </div>
    `,
    iconSize: [38, 38],
    iconAnchor: [19, 19],
    popupAnchor: [0, -18],
  })
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

function buildPopupContent(vehicle: DashboardMapVehicle) {
  return `
    <div style="min-width: 190px;">
      <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">
        ${vehicle.name}
      </div>
      <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
        Tablice: ${vehicle.license_plate || '—'}
      </div>
      <div style="font-size: 13px; color: #475569; margin-bottom: 4px;">
        Status: ${vehicle.online ? 'Online' : 'Offline'}
      </div>
      <div style="font-size: 13px; color: #475569;">
        Zadnja pozicija: ${formatDateTime(vehicle.last_position_at)}
      </div>
    </div>
  `
}

function renderMarkers() {
  if (!map) return

  if (markersLayer) {
    markersLayer.clearLayers()
  } else {
    markersLayer = L.layerGroup().addTo(map)
  }

  if (!validVehicles.value.length) {
    map.setView([44.5, 18.7], 6)
    return
  }

  const bounds = L.latLngBounds([])

  validVehicles.value.forEach((vehicle) => {
    const marker = L.marker([vehicle.lat, vehicle.lng], {
      icon: createVehicleIcon(vehicle.online),
      title: `${vehicle.name} (${vehicle.license_plate})`,
    })

    marker.bindPopup(buildPopupContent(vehicle))

    marker.on('click', () => {
      marker.openPopup()
    })

    marker.on('dblclick', () => {
      router.push({ name: 'vehicle-show', params: { id: vehicle.id } })
    })

    marker.addTo(markersLayer!)
    bounds.extend([vehicle.lat, vehicle.lng])
  })

  if (validVehicles.value.length === 1) {
    const vehicle = validVehicles.value[0]
    map.setView([vehicle.lat, vehicle.lng], 12)
  } else {
    map.fitBounds(bounds, {
      padding: [40, 40],
    })
  }
}

function initMap() {
  if (!mapElement.value || map) return

  map = L.map(mapElement.value, {
    zoomControl: true,
    attributionControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)

  renderMarkers()
}

watch(
    () => props.vehicles,
    async () => {
      await nextTick()
      renderMarkers()
    },
    { deep: true },
)

watch(
    () => props.expanded,
    async () => {
      await nextTick()

      setTimeout(() => {
        map?.invalidateSize()
        renderMarkers()
      }, 120)
    },
)

onMounted(async () => {
  await nextTick()
  initMap()

  setTimeout(() => {
    map?.invalidateSize()
    renderMarkers()
  }, 80)
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    markersLayer = null
  }
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

:deep(.fleet-marker) {
  position: relative;
  width: 24px;
  height: 24px;
}

:deep(.fleet-marker__pulse) {
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  background: color-mix(in srgb, var(--marker-color) 24%, transparent);
  animation: markerPulse 2s ease-out infinite;
}

:deep(.fleet-marker__dot) {
  position: absolute;
  inset: 4px;
  border-radius: 9999px;
  background: var(--marker-color);
  border: 3px solid white;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.18);
}

@keyframes markerPulse {
  0% {
    transform: scale(0.7);
    opacity: 0.8;
  }
  70% {
    transform: scale(1.8);
    opacity: 0;
  }
  100% {
    transform: scale(1.8);
    opacity: 0;
  }
}
</style>