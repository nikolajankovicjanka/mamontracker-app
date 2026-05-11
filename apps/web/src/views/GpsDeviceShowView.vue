<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useGpsDevicesStore } from '@/stores/gpsDevices'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const gpsDevicesStore = useGpsDevicesStore()

const { currentDevice, loading, deleting } = storeToRefs(gpsDevicesStore)

const deviceId = computed(() => Number(route.params.id))
const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await gpsDevicesStore.fetchDevice(deviceId.value)
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

async function handleDelete() {
  if (!currentDevice.value) return

  const confirmed = window.confirm(
      `Are you sure you want to delete GPS device "${currentDevice.value.device_name}"?`
  )

  if (!confirmed) return

  try {
    await gpsDevicesStore.deleteDevice(currentDevice.value.id)
    await router.push({ name: 'gps-devices' })
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    alert(axiosError.response?.data?.message ?? 'GPS device could not be deleted.')
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
            @click="$router.push({ name: 'gps-devices' })"
        >
          Back to GPS devices
        </button>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          {{ currentDevice?.device_name ?? 'GPS device details' }}
        </h1>
      </div>

      <div v-if="currentDevice && canManage" class="flex flex-wrap gap-3">
        <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            @click="$router.push({ name: 'gps-device-edit', params: { id: currentDevice.id } })"
        >
          Edit GPS device
        </button>

        <button
            type="button"
            class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
            :disabled="deleting"
            @click="handleDelete"
        >
          {{ deleting ? 'Deleting...' : 'Delete GPS device' }}
        </button>
      </div>
    </div>

    <div v-if="loading && !currentDevice" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="text-sm text-slate-500">Loading GPS device details...</div>
    </div>

    <template v-else-if="currentDevice">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Device information</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <div class="text-sm text-slate-500">Device name</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.device_name }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Provider</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.provider }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Model</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.model || '—' }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">IMEI</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.imei }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Traccar device ID</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.traccar_device_id ?? '—' }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">SIM number</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.sim_number || '—' }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Last sync</div>
                <div class="mt-1 font-semibold text-slate-900">{{ formatDateTime(currentDevice.last_sync_at) }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Status</div>
                <div class="mt-1">
                  <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="currentDevice.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                  >
                    {{ currentDevice.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Capabilities</h2>

            <div
                v-if="currentDevice.capabilities && Object.keys(currentDevice.capabilities).length"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
            >
              <div
                  v-for="(value, key) in currentDevice.capabilities"
                  :key="key"
                  class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
              >
                <div class="text-sm text-slate-500">{{ key }}</div>
                <div class="mt-1 font-semibold text-slate-900">{{ String(value) }}</div>
              </div>
            </div>

            <div
                v-else
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No capabilities defined.
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Assigned vehicle</h2>

            <template v-if="currentDevice.vehicle">
              <div class="space-y-3">
                <div>
                  <div class="text-sm text-slate-500">Vehicle</div>
                  <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.vehicle.name }}</div>
                </div>

                <div>
                  <div class="text-sm text-slate-500">License plate</div>
                  <div class="mt-1 font-semibold text-slate-900">{{ currentDevice.vehicle.license_plate || '—' }}</div>
                </div>
              </div>
            </template>

            <template v-else>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                No vehicle is assigned to this GPS device.
              </div>
            </template>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Assignment history</h2>

            <div
                v-if="currentDevice.assignment_history?.length"
                class="space-y-3"
            >
              <div
                  v-for="item in currentDevice.assignment_history"
                  :key="item.id"
                  class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
              >
                <div class="font-semibold text-slate-900">
                  {{ item.vehicle?.name || 'Unknown vehicle' }}
                </div>
                <div class="mt-1 text-sm text-slate-500">
                  {{ item.vehicle?.license_plate || '—' }}
                </div>
                <div class="mt-2 text-xs text-slate-400">
                  Assigned: {{ formatDateTime(item.assigned_at) }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                  Unassigned: {{ formatDateTime(item.unassigned_at) }}
                </div>
              </div>
            </div>

            <div
                v-else
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No assignment history yet.
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>