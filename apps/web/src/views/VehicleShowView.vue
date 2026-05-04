<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useVehiclesStore } from '@/stores/vehicles'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const vehiclesStore = useVehiclesStore()

const { currentVehicle, loading, deleting } = storeToRefs(vehiclesStore)

const vehicleId = computed(() => Number(route.params.id))
const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await vehiclesStore.fetchVehicle(vehicleId.value)
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

function formatMileage(value: number | null | undefined) {
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value ?? 0)} km`
}

function statusBadgeClass(value: string | undefined) {
  if (value === 'active') return 'bg-emerald-50 text-emerald-700'
  if (value === 'inactive') return 'bg-rose-50 text-rose-700'
  return 'bg-amber-50 text-amber-700'
}

async function handleDelete() {
  if (!currentVehicle.value) return

  const confirmed = window.confirm(
      `Are you sure you want to delete vehicle "${currentVehicle.value.name}"?`
  )

  if (!confirmed) return

  try {
    await vehiclesStore.deleteVehicle(currentVehicle.value.id)
    await router.push({ name: 'vehicles' })
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    alert(axiosError.response?.data?.message ?? 'Vehicle could not be deleted.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <button
            type="button"
            class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            @click="$router.push({ name: 'vehicles' })"
        >
          Back to vehicles
        </button>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          {{ currentVehicle?.name ?? 'Vehicle details' }}
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Detailed vehicle overview and current assignment status.
        </p>
      </div>

      <div v-if="currentVehicle && canManage" class="flex flex-wrap gap-3">
        <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            @click="$router.push({ name: 'vehicle-edit', params: { id: currentVehicle.id } })"
        >
          Edit vehicle
        </button>

        <button
            type="button"
            class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
            :disabled="deleting"
            @click="handleDelete"
        >
          {{ deleting ? 'Deleting...' : 'Delete vehicle' }}
        </button>
      </div>
    </div>

    <div v-if="loading && !currentVehicle" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="text-sm text-slate-500">Loading vehicle details...</div>
    </div>

    <template v-else-if="currentVehicle">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-900">Vehicle Information</h2>
              <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass(currentVehicle.status)"
              >
                {{ currentVehicle.status }}
              </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <div class="text-sm text-slate-500">Name</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentVehicle.name }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Brand / Model</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.brand }} {{ currentVehicle.model }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Production year</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.production_year ?? '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">License plate</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.license_plate || '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">VIN</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.vin || '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Current mileage</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatMileage(currentVehicle.current_mileage) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Registration expiry</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatDate(currentVehicle.registration_expiry_date) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Last speed</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.last_speed_kph ?? '—' }} km/h
                </div>
              </div>
            </div>

            <div class="mt-6">
              <div class="text-sm text-slate-500">Notes</div>
              <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                {{ currentVehicle.notes || 'No notes available.' }}
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Position & Tracking</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <div class="text-sm text-slate-500">Latitude</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.last_known_lat ?? '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Longitude</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.last_known_lng ?? '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Last position update</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatDateTime(currentVehicle.last_position_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Assigned GPS Device</h2>

            <template v-if="currentVehicle.gps_device">
              <div class="space-y-4">
                <div>
                  <div class="text-sm text-slate-500">Device name</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ currentVehicle.gps_device.device_name }}
                  </div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">Model</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ currentVehicle.gps_device.model || '—' }}
                  </div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">Provider</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ currentVehicle.gps_device.provider || '—' }}
                  </div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">IMEI</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ currentVehicle.gps_device.imei || '—' }}
                  </div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">Traccar device ID</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ currentVehicle.gps_device.traccar_device_id ?? '—' }}
                  </div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">Last sync</div>
                  <div class="mt-1 font-semibold text-slate-900">
                    {{ formatDateTime(currentVehicle.gps_device.last_sync_at) }}
                  </div>
                </div>

                <div>
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="currentVehicle.gps_device.is_active
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-rose-50 text-rose-700'"
                  >
                    {{ currentVehicle.gps_device.is_active ? 'Active device' : 'Inactive device' }}
                  </span>
                </div>
              </div>
            </template>

            <template v-else>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                No GPS device is assigned to this vehicle.
              </div>
            </template>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>