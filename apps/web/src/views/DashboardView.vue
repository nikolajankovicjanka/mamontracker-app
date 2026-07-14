<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import {
  CarFront,
  Wifi,
  WifiOff,
  BadgeAlert,
  Maximize2,
  Minimize2,
  MapPinned,
  BellRing,
} from 'lucide-vue-next'
import FleetMap from '@/components/FleetMap.vue'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const auth = useAuthStore()
const dashboard = useDashboardStore()

const { summary, loading } = storeToRefs(dashboard)
const mapExpanded = ref(false)

let refreshInterval: number | null = null

onMounted(async () => {
  await dashboard.fetchSummary()

  refreshInterval = window.setInterval(async () => {
    if (document.hidden) return
    await dashboard.refreshSummary()
  }, 30000)
})

onBeforeUnmount(() => {
  if (refreshInterval) {
    window.clearInterval(refreshInterval)
    refreshInterval = null
  }
})

const overview = computed(() => summary.value?.overview)

const onlinePercentage = computed(() => {
  if (!overview.value?.total_vehicles) return 0
  return Math.round((overview.value.online_vehicles / overview.value.total_vehicles) * 100)
})

const offlinePercentage = computed(() => {
  if (!overview.value?.total_vehicles) return 0
  return Math.round((overview.value.offline_vehicles / overview.value.total_vehicles) * 100)
})

const mockMapVehicles = [
  {
    id: 1001,
    name: 'Audi A4 Test',
    license_plate: 'A12-K-345',
    lat: 44.7569,
    lng: 19.2164,
    status: 'active',
    online: true,
    last_position_at: new Date().toISOString(),
  },
  {
    id: 1002,
    name: 'VW Crafter Test',
    license_plate: 'M45-T-778',
    lat: 44.5384,
    lng: 18.6671,
    status: 'active',
    online: false,
    last_position_at: new Date().toISOString(),
  },
]

const mapVehicles = computed(() => {
  if (summary.value?.map_vehicles?.length) {
    return summary.value.map_vehicles
  }

  return mockMapVehicles
})

function formatDate(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(date))
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

function formatMileage(value: number) {
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

function daysLeftLabel(daysLeft: number | null) {
  if (daysLeft === null) return '—'
  if (daysLeft === 0) return 'danas'
  if (daysLeft === 1) return '1 dan'
  return `${daysLeft} dana`
}

function severityLabel(severity: string | null) {
  switch (severity) {
    case 'high':
      return 'visok'
    case 'medium':
      return 'srednji'
    case 'low':
      return 'nizak'
    default:
      return 'info'
  }
}

function activityBadgeClass(severity: string | null) {
  switch (severity) {
    case 'high':
      return 'bg-red-50 text-red-700 ring-red-200'
    case 'medium':
      return 'bg-amber-50 text-amber-700 ring-amber-200'
    case 'low':
      return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
    default:
      return 'bg-slate-100 text-slate-700 ring-slate-200'
  }
}
</script>

<template>
  <div class="space-y-6 lg:space-y-8">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Pregled voznog parka
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Pregled u realnom vremenu za vozni park kompanije {{ auth.tenant?.name }}.
        </p>
      </div>

      <div class="text-sm text-slate-500">
        Posljednje ažuriranje:
        <span class="font-medium text-slate-700">
          {{ formatDateTime(summary?.generated_at ?? null) }}
        </span>
      </div>
    </div>

    <div
        v-if="loading && !summary"
        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
    >
      <div class="text-sm text-slate-500">Učitavanje dashboarda...</div>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <CarFront class="h-5 w-5" />
              </div>
              <div class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                Vozni park
              </div>
            </div>

            <div class="text-xs font-medium text-emerald-600">
              ukupno
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview?.total_vehicles ?? 0 }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Ukupno vozila
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full w-full rounded-full bg-blue-500" />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <Wifi class="h-5 w-5" />
              </div>
              <div class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Online
              </div>
            </div>

            <div class="text-xs font-medium text-emerald-600">
              {{ onlinePercentage }}%
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview?.online_vehicles ?? 0 }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Online vozila
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                :style="{ width: `${onlinePercentage}%` }"
            />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                <WifiOff class="h-5 w-5" />
              </div>
              <div class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                Offline
              </div>
            </div>

            <div class="text-xs font-medium text-rose-600">
              {{ offlinePercentage }}%
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview?.offline_vehicles ?? 0 }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Offline vozila
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-rose-500 transition-all duration-500"
                :style="{ width: `${offlinePercentage}%` }"
            />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <BadgeAlert class="h-5 w-5" />
              </div>
              <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                Registracije
              </div>
            </div>

            <div class="text-xs font-medium text-amber-600">
              hitno
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview?.expiring_registrations_count ?? 0 }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Ističu uskoro
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-amber-500 transition-all duration-500"
                :style="{ width: `${Math.min((((overview?.expiring_registrations_count ?? 0) / Math.max((overview?.total_vehicles ?? 1), 1)) * 100), 100)}%` }"
            />
          </div>
        </div>
      </div>

      <div
          v-if="mapExpanded"
          class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px]"
          @click="mapExpanded = false"
      />

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div
            class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition-all duration-300 sm:p-5"
            :class="mapExpanded ? 'fixed inset-4 z-50 !m-0 overflow-hidden shadow-2xl' : ''"
        >
          <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-lg font-semibold text-slate-900">Mapa voznog parka uživo</h2>
              <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                Uživo
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                  <span>Online</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full bg-rose-500" />
                  <span>Offline</span>
                </div>
              </div>

              <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                  @click.stop="mapExpanded = !mapExpanded"
              >
                <component :is="mapExpanded ? Minimize2 : Maximize2" class="h-4.5 w-4.5" />
                <span>{{ mapExpanded ? 'Smanji' : 'Proširi mapu' }}</span>
              </button>
            </div>
          </div>

          <div
              class="relative overflow-hidden rounded-3xl border border-slate-200 bg-sky-50"
              :class="mapExpanded ? 'h-[calc(100vh-11rem)]' : 'h-[300px] sm:h-[380px]'"
          >
            <div class="absolute left-4 top-4 z-10 rounded-2xl border border-white/70 bg-white/90 px-3 py-2 text-xs font-medium text-slate-600 shadow-sm backdrop-blur">
              <div class="flex items-center gap-2">
                <MapPinned class="h-4 w-4 text-blue-600" />
                <span>Fleet tracking mapa</span>
              </div>
            </div>

            <FleetMap
                :vehicles="mapVehicles"
                :expanded="mapExpanded"
            />
          </div>
        </div>

        <div
            class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
            :class="mapExpanded ? 'xl:hidden' : ''"
        >
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Registracije koje ističu</h2>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
              Prikazano: {{ summary?.expiring_registrations?.length ?? 0 }}
            </span>
          </div>

          <div class="space-y-3">
            <div
                v-for="vehicle in summary?.expiring_registrations ?? []"
                :key="vehicle.id"
                class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 transition hover:bg-amber-50"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <div class="truncate font-semibold text-slate-900">
                    {{ vehicle.name }}
                  </div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ vehicle.license_plate }}
                  </div>
                </div>

                <div class="shrink-0 text-right">
                  <div class="text-sm font-semibold text-amber-700">
                    {{ daysLeftLabel(vehicle.days_left) }}
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ formatDate(vehicle.registration_expiry_date) }}
                  </div>
                </div>
              </div>
            </div>

            <div
                v-if="!(summary?.expiring_registrations?.length)"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              Nema registracija koje uskoro ističu.
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <CarFront class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Vozila sa najvećom kilometražom</h2>
                <div class="text-sm text-slate-400">
                  Top {{ summary?.highest_mileage_vehicles?.length ?? 0 }}
                </div>
              </div>
            </div>
          </div>

          <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left">
              <thead>
              <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <th class="pb-3 font-medium">Vozilo</th>
                <th class="pb-3 font-medium">Tablice</th>
                <th class="pb-3 font-medium">Kilometraža</th>
                <th class="pb-3 font-medium">Status</th>
              </tr>
              </thead>
              <tbody>
              <tr
                  v-for="vehicle in summary?.highest_mileage_vehicles ?? []"
                  :key="vehicle.id"
                  class="border-b border-slate-100 last:border-b-0"
              >
                <td class="py-4 font-medium text-slate-900">
                  {{ vehicle.name }}
                </td>
                <td class="py-4 text-slate-500">
                  {{ vehicle.license_plate }}
                </td>
                <td class="py-4 font-semibold text-slate-900">
                  {{ formatMileage(vehicle.current_mileage) }}
                </td>
                <td class="py-4">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="vehicle.online
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-rose-50 text-rose-700'"
                    >
                      {{ vehicle.online ? 'Online' : 'Offline' }}
                    </span>
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div class="space-y-3 md:hidden">
            <div
                v-for="vehicle in summary?.highest_mileage_vehicles ?? []"
                :key="vehicle.id"
                class="rounded-2xl border border-slate-200 p-4"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <div class="font-semibold text-slate-900">
                    {{ vehicle.name }}
                  </div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ vehicle.license_plate }}
                  </div>
                  <div class="mt-2 text-sm font-semibold text-slate-900">
                    {{ formatMileage(vehicle.current_mileage) }}
                  </div>
                </div>

                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="vehicle.online
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-rose-50 text-rose-700'"
                >
                  {{ vehicle.online ? 'Online' : 'Offline' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <BellRing class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Nedavne aktivnosti</h2>
                <div class="text-sm text-slate-400">
                  Posljednji događaji
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <div
                v-for="item in summary?.recent_activity ?? []"
                :key="item.id"
                class="rounded-2xl border border-slate-100 bg-slate-50 p-4 transition hover:bg-slate-100/70"
            >
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                  <div class="font-medium text-slate-900">
                    {{ item.title }}
                  </div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ item.message }}
                  </div>
                  <div class="mt-2 text-xs text-slate-400">
                    {{ formatDateTime(item.sent_at) }}
                  </div>
                </div>

                <span
                    class="w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                    :class="activityBadgeClass(item.severity)"
                >
                  {{ severityLabel(item.severity) }}
                </span>
              </div>
            </div>

            <div
                v-if="!(summary?.recent_activity?.length)"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              Još nema nedavnih aktivnosti.
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>