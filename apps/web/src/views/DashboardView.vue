<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const auth = useAuthStore()
const dashboard = useDashboardStore()

const { summary, loading } = storeToRefs(dashboard)

onMounted(async () => {
  await dashboard.fetchSummary()
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

const europeBounds = {
  minLat: 35,
  maxLat: 72,
  minLng: -10,
  maxLng: 40,
}

function markerStyle(lat: number, lng: number) {
  const x = ((lng - europeBounds.minLng) / (europeBounds.maxLng - europeBounds.minLng)) * 100
  const y = ((europeBounds.maxLat - lat) / (europeBounds.maxLat - europeBounds.minLat)) * 100

  const clampedX = Math.min(Math.max(x, 4), 96)
  const clampedY = Math.min(Math.max(y, 6), 94)

  return {
    left: `${clampedX}%`,
    top: `${clampedY}%`,
  }
}

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
  if (daysLeft === 0) return 'today'
  if (daysLeft === 1) return '1 day'
  return `${daysLeft} days`
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
          Fleet Dashboard
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Real-time overview for {{ auth.tenant?.name }} fleet operations.
        </p>
      </div>

      <div class="text-sm text-slate-500">
        Last updated:
        <span class="font-medium text-slate-700">
          {{ formatDateTime(summary?.generated_at ?? null) }}
        </span>
      </div>
    </div>

    <div v-if="loading && !summary" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <div class="text-sm text-slate-500">Loading dashboard...</div>
    </div>

    <template v-else-if="summary && overview">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="rounded-2xl bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700">
              Fleet
            </div>
            <div class="text-xs font-medium text-emerald-600">
              total
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview.total_vehicles }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Total vehicles
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full w-full rounded-full bg-blue-500" />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
              Online
            </div>
            <div class="text-xs font-medium text-emerald-600">
              {{ onlinePercentage }}%
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview.online_vehicles }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Online vehicles
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${onlinePercentage}%` }" />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="rounded-2xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
              Offline
            </div>
            <div class="text-xs font-medium text-rose-600">
              {{ offlinePercentage }}%
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview.offline_vehicles }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Offline vehicles
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-rose-500" :style="{ width: `${offlinePercentage}%` }" />
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center justify-between">
            <div class="rounded-2xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700">
              Registrations
            </div>
            <div class="text-xs font-medium text-amber-600">
              urgent
            </div>
          </div>

          <div class="text-3xl font-bold text-slate-900 sm:text-4xl">
            {{ overview.expiring_registrations_count }}
          </div>
          <div class="mt-2 text-sm text-slate-500">
            Expiring soon
          </div>

          <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-amber-500"
                :style="{ width: `${Math.min((overview.expiring_registrations_count / Math.max(overview.total_vehicles, 1)) * 100, 100)}%` }"
            />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-lg font-semibold text-slate-900">Live Fleet Map</h2>
              <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                Live
              </span>
            </div>

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
          </div>

          <div class="relative h-[280px] overflow-hidden rounded-3xl border border-slate-200 bg-sky-50 sm:h-[360px]">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.12)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.12)_1px,transparent_1px)] bg-[size:48px_48px]" />
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(59,130,246,0.10),transparent_25%),radial-gradient(circle_at_65%_35%,rgba(37,99,235,0.10),transparent_28%),radial-gradient(circle_at_50%_70%,rgba(14,165,233,0.10),transparent_24%)]" />

            <template v-if="summary.map_vehicles.length">
              <div
                  v-for="vehicle in summary.map_vehicles"
                  :key="vehicle.id"
                  class="absolute -translate-x-1/2 -translate-y-1/2"
                  :style="markerStyle(vehicle.lat, vehicle.lng)"
              >
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white text-xs font-bold text-white shadow-md"
                    :class="vehicle.online ? 'bg-emerald-500' : 'bg-rose-500'"
                    :title="`${vehicle.name} (${vehicle.license_plate})`"
                >
                  V
                </div>
              </div>
            </template>

            <div
                v-else
                class="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-slate-500"
            >
              No vehicle positions available yet.
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Expiring Registrations</h2>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
              {{ summary.expiring_registrations.length }} shown
            </span>
          </div>

          <div class="space-y-3">
            <div
                v-for="vehicle in summary.expiring_registrations"
                :key="vehicle.id"
                class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4"
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
                v-if="!summary.expiring_registrations.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No registrations expiring soon.
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Highest Mileage Vehicles</h2>
            <span class="text-sm font-medium text-slate-400">
              Top {{ summary.highest_mileage_vehicles.length }}
            </span>
          </div>

          <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left">
              <thead>
              <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <th class="pb-3 font-medium">Vehicle</th>
                <th class="pb-3 font-medium">Plate</th>
                <th class="pb-3 font-medium">Mileage</th>
                <th class="pb-3 font-medium">Status</th>
              </tr>
              </thead>
              <tbody>
              <tr
                  v-for="vehicle in summary.highest_mileage_vehicles"
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
                v-for="vehicle in summary.highest_mileage_vehicles"
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
            <h2 class="text-lg font-semibold text-slate-900">Recent Activity</h2>
            <span class="text-sm font-medium text-slate-400">
              Latest events
            </span>
          </div>

          <div class="space-y-3">
            <div
                v-for="item in summary.recent_activity"
                :key="item.id"
                class="rounded-2xl border border-slate-100 bg-slate-50 p-4"
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
                  {{ item.severity ?? 'info' }}
                </span>
              </div>
            </div>

            <div
                v-if="!summary.recent_activity.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No recent activity yet.
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>