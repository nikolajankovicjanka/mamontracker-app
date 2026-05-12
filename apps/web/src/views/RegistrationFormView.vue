<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useRegistrationsStore } from '@/stores/registrations'

const route = useRoute()
const router = useRouter()
const registrationsStore = useRegistrationsStore()

const vehicleId = Number(route.params.vehicleId)
const errorMessage = ref('')
const initialLoading = ref(false)

const form = reactive({
  registration_expiry_date: '',
})

onMounted(async () => {
  initialLoading.value = true
  errorMessage.value = ''

  try {
    const registration = await registrationsStore.fetchRegistration(vehicleId)
    form.registration_expiry_date = registration.registration_expiry_date ?? ''
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'Registracija nije mogla biti učitana.'
  } finally {
    initialLoading.value = false
  }
})

async function handleSubmit() {
  errorMessage.value = ''

  try {
    const registration = await registrationsStore.updateRegistration(vehicleId, {
      registration_expiry_date: form.registration_expiry_date || null,
    })

    await router.push({ name: 'registrations' })
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'Registracija nije mogla biti sačuvana.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <button
          type="button"
          class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="$router.push({ name: 'registrations' })"
      >
        Nazad na registracije
      </button>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        Uredi registraciju
      </h1>
      <p class="mt-2 text-sm text-slate-500">
        Ažuriraj datum isteka registracije za odabrano vozilo.
      </p>
    </div>

    <div
        v-if="initialLoading"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="text-sm text-slate-500">Učitavanje podataka...</div>
    </div>

    <div
        v-else
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div v-if="registrationsStore.currentRegistration" class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="font-semibold text-slate-900">
          {{ registrationsStore.currentRegistration.name }}
        </div>
        <div class="mt-1 text-sm text-slate-500">
          {{ registrationsStore.currentRegistration.brand }} {{ registrationsStore.currentRegistration.model }}
        </div>
        <div class="mt-1 text-sm text-slate-500">
          {{ registrationsStore.currentRegistration.license_plate || '—' }}
        </div>
      </div>

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">
            Datum isteka registracije
          </label>
          <input
              v-model="form.registration_expiry_date"
              type="date"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
          />
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
              :disabled="registrationsStore.saving"
          >
            {{ registrationsStore.saving ? 'Čuvanje...' : 'Sačuvaj izmjene' }}
          </button>

          <button
              type="button"
              class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
              @click="$router.push({ name: 'registrations' })"
          >
            Odustani
          </button>
        </div>
      </form>
    </div>
  </div>
</template>