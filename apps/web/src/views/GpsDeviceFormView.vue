<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useGpsDevicesStore } from '@/stores/gpsDevices'

const route = useRoute()
const router = useRouter()
const gpsDevicesStore = useGpsDevicesStore()

const isEdit = computed(() => route.name === 'gps-device-edit')
const deviceId = computed(() => Number(route.params.id))
const errorMessage = ref('')
const initialLoading = ref(false)
const availableVehicles = ref<Array<{ id: number; name: string; license_plate: string | null }>>([])

const form = reactive({
  vehicle_id: null as number | null,
  provider: 'traccar',
  device_name: '',
  model: '',
  imei: '',
  traccar_device_id: null as number | null,
  sim_number: '',
  is_active: true,
  capabilitiesText: '{}',
})

onMounted(async () => {
  await loadAvailableVehicles()

  if (!isEdit.value) return

  initialLoading.value = true
  errorMessage.value = ''

  try {
    const device = await gpsDevicesStore.fetchDevice(deviceId.value)

    form.vehicle_id = device.vehicle_id
    form.provider = device.provider
    form.device_name = device.device_name
    form.model = device.model ?? ''
    form.imei = device.imei
    form.traccar_device_id = device.traccar_device_id ?? null
    form.sim_number = device.sim_number ?? ''
    form.is_active = device.is_active
    form.capabilitiesText = JSON.stringify(device.capabilities ?? {}, null, 2)
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'GPS device could not be loaded.'
  } finally {
    initialLoading.value = false
  }
})

async function loadAvailableVehicles() {
  const { data } = await api.get('/api/vehicles', {
    params: {
      per_page: 100,
    },
  })

  availableVehicles.value = data.data
}

async function handleSubmit() {
  errorMessage.value = ''

  try {
    let capabilities: Record<string, unknown> | null = null

    if (form.capabilitiesText.trim()) {
      capabilities = JSON.parse(form.capabilitiesText)
    }

    const payload = {
      vehicle_id: form.vehicle_id,
      provider: form.provider.trim(),
      device_name: form.device_name.trim(),
      model: form.model.trim() || null,
      imei: form.imei.trim(),
      traccar_device_id: form.traccar_device_id,
      sim_number: form.sim_number.trim() || null,
      capabilities,
      is_active: form.is_active,
    }

    if (isEdit.value) {
      const device = await gpsDevicesStore.updateDevice(deviceId.value, payload)
      await router.push({ name: 'gps-device-show', params: { id: device.id } })
    } else {
      const device = await gpsDevicesStore.createDevice(payload)
      await router.push({ name: 'gps-device-show', params: { id: device.id } })
    }
  } catch (error) {
    if (error instanceof SyntaxError) {
      errorMessage.value = 'Capabilities must be valid JSON.'
      return
    }

    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'GPS device could not be saved.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <button
          type="button"
          class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="$router.push({ name: 'gps-devices' })"
      >
        Back to GPS devices
      </button>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ isEdit ? 'Edit GPS Device' : 'Create GPS Device' }}
      </h1>
    </div>

    <div
        v-if="initialLoading"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="text-sm text-slate-500">Loading GPS device data...</div>
    </div>

    <div
        v-else
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Device name</label>
            <input
                v-model="form.device_name"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Provider</label>
            <input
                v-model="form.provider"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Model</label>
            <input
                v-model="form.model"
                type="text"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">IMEI</label>
            <input
                v-model="form.imei"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Traccar device ID</label>
            <input
                v-model.number="form.traccar_device_id"
                type="number"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">SIM number</label>
            <input
                v-model="form.sim_number"
                type="text"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Assigned vehicle</label>
            <select
                v-model="form.vehicle_id"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            >
              <option :value="null">No vehicle assigned</option>
              <option
                  v-for="vehicle in availableVehicles"
                  :key="vehicle.id"
                  :value="vehicle.id"
              >
                {{ vehicle.name }} <span v-if="vehicle.license_plate">({{ vehicle.license_plate }})</span>
              </option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Capabilities (JSON)</label>
            <textarea
                v-model="form.capabilitiesText"
                rows="8"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 font-mono text-sm outline-none focus:border-slate-500"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
              <input v-model="form.is_active" type="checkbox" />
              Device is active
            </label>
          </div>
        </div>

        <div
            v-if="errorMessage"
            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
          {{ errorMessage }}
        </div>

        <div class="flex flex-wrap gap-3">
          <button
              type="submit"
              class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
              :disabled="gpsDevicesStore.saving"
          >
            {{ gpsDevicesStore.saving ? 'Saving...' : isEdit ? 'Save changes' : 'Create GPS device' }}
          </button>

          <button
              type="button"
              class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
              @click="$router.push({ name: 'gps-devices' })"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</template>