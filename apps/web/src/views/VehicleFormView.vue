<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useVehiclesStore } from '@/stores/vehicles'

const route = useRoute()
const router = useRouter()
const vehiclesStore = useVehiclesStore()

const isEdit = computed(() => route.name === 'vehicle-edit')
const vehicleId = computed(() => Number(route.params.id))
const errorMessage = ref('')
const initialLoading = ref(false)

const form = reactive({
  name: '',
  brand: '',
  model: '',
  production_year: null as number | null,
  license_plate: '',
  vin: '',
  registration_expiry_date: '',
  current_mileage: null as number | null,
  status: 'active' as 'active' | 'inactive' | 'maintenance',
  notes: '',
})

onMounted(async () => {
  if (!isEdit.value) return

  initialLoading.value = true
  errorMessage.value = ''

  try {
    const vehicle = await vehiclesStore.fetchVehicle(vehicleId.value)

    form.name = vehicle.name
    form.brand = vehicle.brand ?? ''
    form.model = vehicle.model ?? ''
    form.production_year = vehicle.production_year ?? null
    form.license_plate = vehicle.license_plate ?? ''
    form.vin = vehicle.vin ?? ''
    form.registration_expiry_date = vehicle.registration_expiry_date ?? ''
    form.current_mileage = vehicle.current_mileage ?? null
    form.status = vehicle.status
    form.notes = vehicle.notes ?? ''
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'Vehicle could not be loaded.'
  } finally {
    initialLoading.value = false
  }
})

async function handleSubmit() {
  errorMessage.value = ''

  try {
    const payload = {
      name: form.name.trim(),
      brand: form.brand.trim(),
      model: form.model.trim(),
      production_year: form.production_year,
      license_plate: form.license_plate.trim(),
      vin: form.vin.trim() || null,
      registration_expiry_date: form.registration_expiry_date || null,
      current_mileage: form.current_mileage,
      status: form.status,
      notes: form.notes.trim() || null,
    }

    if (isEdit.value) {
      const vehicle = await vehiclesStore.updateVehicle(vehicleId.value, payload)
      await router.push({ name: 'vehicle-show', params: { id: vehicle.id } })
    } else {
      const vehicle = await vehiclesStore.createVehicle(payload)
      await router.push({ name: 'vehicle-show', params: { id: vehicle.id } })
    }
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'Vehicle could not be saved.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <button
          type="button"
          class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="$router.push({ name: 'vehicles' })"
      >
        Back to vehicles
      </button>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ isEdit ? 'Edit Vehicle' : 'Create Vehicle' }}
      </h1>
      <p class="mt-2 text-sm text-slate-500">
        {{ isEdit ? 'Update vehicle information.' : 'Add a new vehicle to the tenant fleet.' }}
      </p>
    </div>

    <div
        v-if="initialLoading"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="text-sm text-slate-500">Loading vehicle data...</div>
    </div>

    <div
        v-else
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Name</label>
            <input
                v-model="form.name"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Brand</label>
            <input
                v-model="form.brand"
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
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Production year</label>
            <input
                v-model.number="form.production_year"
                type="number"
                min="1900"
                :max="new Date().getFullYear() + 1"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">License plate</label>
            <input
                v-model="form.license_plate"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">VIN</label>
            <input
                v-model="form.vin"
                type="text"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Registration expiry date</label>
            <input
                v-model="form.registration_expiry_date"
                type="date"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Current mileage</label>
            <input
                v-model.number="form.current_mileage"
                type="number"
                min="0"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
            <select
                v-model="form.status"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Notes</label>
            <textarea
                v-model="form.notes"
                rows="5"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
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
              :disabled="vehiclesStore.saving"
          >
            {{ vehiclesStore.saving ? 'Saving...' : isEdit ? 'Save changes' : 'Create vehicle' }}
          </button>

          <button
              type="button"
              class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
              @click="$router.push({ name: 'vehicles' })"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</template>