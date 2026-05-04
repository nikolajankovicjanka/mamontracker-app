<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useVehiclesStore } from '@/stores/vehicles'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const vehiclesStore = useVehiclesStore()
const router = useRouter()
const auth = useAuthStore()
const canManage = computed(() => auth.user?.role === 'tenant_admin')

const {
  items,
  loading,
  page,
  lastPage,
  total,
  search,
  status,
  hasItems,
} = storeToRefs(vehiclesStore)

onMounted(async () => {
  await vehiclesStore.fetchVehicles()
})

const statusOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Maintenance', value: 'maintenance' },
]

const from = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * vehiclesStore.perPage + 1
})

const to = computed(() => {
  if (!items.value.length) return 0
  return (page.value - 1) * vehiclesStore.perPage + items.value.length
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

function statusBadgeClass(value: string) {
  if (value === 'active') return 'bg-emerald-50 text-emerald-700'
  if (value === 'inactive') return 'bg-rose-50 text-rose-700'
  return 'bg-amber-50 text-amber-700'
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Vehicles
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Manage and review all tenant vehicles.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="text-sm text-slate-500">
          Total vehicles:
          <span class="font-semibold text-slate-800">{{ total }}</span>
        </div>

        <button
            v-if="canManage"
            type="button"
            class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
            @click="router.push({ name: 'vehicle-create' })"
        >
          Add vehicle
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
              placeholder="Search by name, brand, model, plate or VIN"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @keyup.enter="vehiclesStore.applyFilters()"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
          <select
              v-model="status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
              @change="vehiclesStore.applyFilters()"
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
              @click="vehiclesStore.applyFilters()"
          >
            Apply filters
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Fleet list</h2>
        <div class="text-sm text-slate-500">
          Showing {{ from }}–{{ to }} of {{ total }}
        </div>
      </div>

      <div v-if="loading" class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
        Loading vehicles...
      </div>

      <div
          v-else-if="!hasItems"
          class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500"
      >
        No vehicles found for the current filters.
      </div>

      <template v-else>
        <div class="hidden overflow-x-auto xl:block">
          <table class="min-w-full text-left">
            <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="pb-3 font-medium">Vehicle</th>
              <th class="pb-3 font-medium">Plate</th>
              <th class="pb-3 font-medium">Mileage</th>
              <th class="pb-3 font-medium">Registration</th>
              <th class="pb-3 font-medium">GPS Device</th>
              <th class="pb-3 font-medium">Last sync</th>
              <th class="pb-3 font-medium">Status</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="vehicle in items"
                :key="vehicle.id"
                class="border-b border-slate-100 last:border-b-0"
            >
              <td class="py-4">
                <button
                    type="button"
                    class="text-left"
                    @click="router.push({ name: 'vehicle-show', params: { id: vehicle.id } })"
                >
                  <div class="font-semibold text-slate-900 hover:underline">{{ vehicle.name }}</div>
                  <div class="mt-1 text-sm text-slate-500">
                    {{ vehicle.brand }} {{ vehicle.model }}
                    <span v-if="vehicle.production_year">· {{ vehicle.production_year }}</span>
                  </div>
                </button>
              </td>

              <td class="py-4 text-slate-600">
                {{ vehicle.license_plate || '—' }}
              </td>

              <td class="py-4 font-semibold text-slate-900">
                {{ formatMileage(vehicle.current_mileage) }}
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDate(vehicle.registration_expiry_date) }}
              </td>

              <td class="py-4 text-slate-600">
                <template v-if="vehicle.gps_device">
                  <div class="font-medium text-slate-900">
                    {{ vehicle.gps_device.device_name }}
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ vehicle.gps_device.is_active ? 'Active device' : 'Inactive device' }}
                  </div>
                </template>
                <template v-else>
                  —
                </template>
              </td>

              <td class="py-4 text-slate-600">
                {{ formatDateTime(vehicle.gps_device?.last_sync_at ?? null) }}
              </td>

              <td class="py-4">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="statusBadgeClass(vehicle.status)"
                  >
                    {{ vehicle.status }}
                  </span>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="space-y-3 xl:hidden">
          <div
              v-for="vehicle in items"
              :key="vehicle.id"
              class="rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50"
              @click="router.push({ name: 'vehicle-show', params: { id: vehicle.id } })"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">{{ vehicle.name }}</div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ vehicle.brand }} {{ vehicle.model }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  Plate: {{ vehicle.license_plate || '—' }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  Mileage: {{ formatMileage(vehicle.current_mileage) }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  Registration: {{ formatDate(vehicle.registration_expiry_date) }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  GPS: {{ vehicle.gps_device?.device_name || '—' }}
                </div>
              </div>

              <span
                  class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass(vehicle.status)"
              >
                {{ vehicle.status }}
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
                @click="vehiclesStore.goToPage(page - 1)"
            >
              Previous
            </button>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="page >= lastPage"
                @click="vehiclesStore.goToPage(page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>