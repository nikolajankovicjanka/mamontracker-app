<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useServicesStore } from '@/stores/services'

const route = useRoute()
const router = useRouter()
const servicesStore = useServicesStore()

const isEdit = computed(() => route.name === 'service-edit')
const serviceId = computed(() => Number(route.params.id))
const errorMessage = ref('')
const initialLoading = ref(false)
const availableVehicles = ref<Array<{ id: number; name: string; license_plate: string | null }>>([])

const form = reactive({
  vehicle_id: null as number | null,
  service_type: '',
  service_date: '',
  mileage_at_service: null as number | null,
  next_service_due_km: null as number | null,
  notes: '',
})

onMounted(async () => {
  await loadVehicles()

  if (!isEdit.value) return

  initialLoading.value = true
  errorMessage.value = ''

  try {
    const service = await servicesStore.fetchService(serviceId.value)

    form.vehicle_id = service.vehicle_id
    form.service_type = service.service_type
    form.service_date = service.service_date ?? ''
    form.mileage_at_service = service.mileage_at_service
    form.next_service_due_km = service.next_service_due_km
    form.notes = service.notes ?? ''
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'Servis nije mogao biti učitan.'
  } finally {
    initialLoading.value = false
  }
})

async function loadVehicles() {
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
    const payload = {
      vehicle_id: Number(form.vehicle_id),
      service_type: form.service_type.trim(),
      service_date: form.service_date,
      mileage_at_service: Number(form.mileage_at_service),
      next_service_due_km: form.next_service_due_km,
      notes: form.notes.trim() || null,
    }

    if (isEdit.value) {
      const service = await servicesStore.updateService(serviceId.value, payload)
      await router.push({ name: 'service-show', params: { id: service.id } })
    } else {
      const service = await servicesStore.createService(payload)
      await router.push({ name: 'service-show', params: { id: service.id } })
    }
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'Servis nije mogao biti sačuvan.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <button
          type="button"
          class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="$router.push({ name: 'services' })"
      >
        Nazad na servise
      </button>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ isEdit ? 'Uredi servis' : 'Dodaj servis' }}
      </h1>
    </div>

    <div
        v-if="initialLoading"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="text-sm text-slate-500">Učitavanje servisa...</div>
    </div>

    <div
        v-else
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Vozilo</label>
            <select
                v-model="form.vehicle_id"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            >
              <option :value="null">Odaberite vozilo</option>
              <option
                  v-for="vehicle in availableVehicles"
                  :key="vehicle.id"
                  :value="vehicle.id"
              >
                {{ vehicle.name }} <span v-if="vehicle.license_plate">({{ vehicle.license_plate }})</span>
              </option>
            </select>
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Tip servisa</label>
            <input
                v-model="form.service_type"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Datum servisa</label>
            <input
                v-model="form.service_date"
                type="date"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Kilometraža na servisu</label>
            <input
                v-model.number="form.mileage_at_service"
                type="number"
                min="0"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Sljedeći servis na (km)</label>
            <input
                v-model.number="form.next_service_due_km"
                type="number"
                min="0"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Napomene</label>
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
              :disabled="servicesStore.saving"
          >
            {{ servicesStore.saving ? 'Čuvanje...' : isEdit ? 'Sačuvaj izmjene' : 'Dodaj servis' }}
          </button>

          <button
              type="button"
              class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
              @click="$router.push({ name: 'services' })"
          >
            Odustani
          </button>
        </div>
      </form>
    </div>
  </div>
</template>