<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useGpsDevicesStore } from '@/stores/gpsDevices'

const router = useRouter()
const auth = useAuthStore()
const gpsDevicesStore = useGpsDevicesStore()

const {
  items,
  loading,
  page,
  lastPage,
  total,
  search,
  status,
  hasItems,
} = storeToRefs(gpsDevicesStore)

const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await gpsDevicesStore.fetchDevices()
})

const statusOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
]

const from = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * gpsDevicesStore.perPage + 1
})

const to = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * gpsDevicesStore.perPage + items.value.length
})

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
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          GPS Devices
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Manage GPS devices and assignments.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="text-sm text-slate-500">
          Total devices:
          <span class="font-semibold text-slate-800">{{ total }}</span>
        </div>

        <button
            v-if="canManage"
            type="button"
            class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
            @click="router.push({ name: 'gps-device-create' })"
        >
          Add GPS device
        </button>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_220px_auto]">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Search</label>
          <input
              v-model="search"
              type="text"
              placeholder="Search by device name, model, IMEI, SIM or Traccar ID"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @keyup.enter="gpsDevicesStore.applyFilters()"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
          <select
              v-model="status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="gpsDevicesStore.applyFilters()"
          >
            <option
                v-for="option in statusOptions"
                :key="option.value"
                :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="flex items-end">
          <button
              type="button"
              class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 lg:w-auto"
              @click="gpsDevicesStore.applyFilters()"
          >
            Apply filters
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Device list</h2>
        <div class="text-sm text-slate-500">
          Showing {{ from }}–{{ to }} of {{ total }}
        </div>
      </div>

      <div v-if="loading" class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
        Loading GPS devices...
      </div>

      <div
          v-else-if="!hasItems"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        No GPS devices found for the current filters.
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto xl:block">
          <table class="min-w-full text-left">
            <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="pb-3 font-medium">Device</th>
              <th class="pb-3 font-medium">IMEI</th>
              <th class="pb-3 font-medium">Assigned vehicle</th>
              <th class="pb-3 font-medium">SIM</th>
              <th class="pb-3 font-medium">Last sync</th>
              <th class="pb-3 font-medium">Status</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="device in items"
                :key="device.id"
                class="border-b border-slate-100 last:border-b-0"
            >
              <td class="py-4">
                <button
                    type="button"
                    class="text-left"
                    @click="router.push({ name: 'gps-device-show', params: { id: device.id } })"
                >
                  <div class="font-semibold text-slate-900 hover:underline">{{ device.device_name }}</div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ device.provider }} <span v-if="device.model">· {{ device.model }}</span>
                  </div>
                </button>
              </td>

              <td class="py-4 text-slate-600">
                {{ device.imei }}
              </td>

              <td class="py-4 text-slate-600">
                <template v-if="device.vehicle">
                  {{ device.vehicle.name }}
                  <div class="text-xs text-slate-400">{{ device.vehicle.license_plate }}</div>
                </template>
                <template v-else>
                  —
                </template>
              </td>

              <td class="py-4 text-slate-600">
                {{ device.sim_number || '—' }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDateTime(device.last_sync_at) }}
              </td>

              <td class="py-4">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="device.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                  >
                    {{ device.is_active ? 'Active' : 'Inactive' }}
                  </span>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3 xl:hidden">
          <div
              v-for="device in items"
              :key="device.id"
              class="cursor-pointer rounded-2xl border border-slate-200 p-4 hover:bg-slate-50"
              @click="router.push({ name: 'gps-device-show', params: { id: device.id } })"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">{{ device.device_name }}</div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ device.provider }} <span v-if="device.model">· {{ device.model }}</span>
                </div>
                <div class="mt-1 text-sm text-slate-500">IMEI: {{ device.imei }}</div>
                <div class="mt-1 text-sm text-slate-500">
                  Vehicle: {{ device.vehicle?.name || '—' }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  Last sync: {{ formatDateTime(device.last_sync_at) }}
                </div>
              </div>

              <span
                  class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="device.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
              >
                {{ device.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm text-slate-500">
            Page {{ page }} of {{ lastPage }}
          </div>

          <div class="flex gap-2">
            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page <= 1"
                @click="gpsDevicesStore.goToPage(page - 1)"
            >
              Previous
            </button>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page >= lastPage"
                @click="gpsDevicesStore.goToPage(page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>