<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useServicesStore } from '@/stores/services'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const servicesStore = useServicesStore()

const { currentService, loading, deleting } = storeToRefs(servicesStore)

const serviceId = computed(() => Number(route.params.id))
const canManage = computed(() => auth.user?.role === 'tenant_admin')

onMounted(async () => {
  await servicesStore.fetchService(serviceId.value)
})

function formatDate(date: string | null) {
  if (!date) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(date))
}

function formatMileage(value: number | null) {
  if (value === null) return '—'
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

function statusBadgeClass(value: string | undefined) {
  if (value === 'ok') return 'bg-emerald-50 text-emerald-700'
  if (value === 'due_soon') return 'bg-amber-50 text-amber-700'
  if (value === 'due') return 'bg-rose-50 text-rose-700'
  return 'bg-slate-100 text-slate-700'
}

function statusLabel(value: string | undefined) {
  if (value === 'ok') return 'U redu'
  if (value === 'due_soon') return 'Uskoro dospijeva'
  if (value === 'due') return 'Dospio'
  return 'Nema limita'
}

function dueLabel(value: number | null) {
  if (value === null) return '—'
  if (value < 0) return `${Math.abs(value)} km prekoračeno`
  if (value === 0) return 'Dospijeva sada'
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

async function handleDelete() {
  if (!currentService.value) return

  const confirmed = window.confirm(
      `Da li ste sigurni da želite obrisati servis "${currentService.value.service_type}"?`
  )

  if (!confirmed) return

  try {
    await servicesStore.deleteService(currentService.value.id)
    await router.push({ name: 'services' })
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    alert(axiosError.response?.data?.message ?? 'Servis nije moguće obrisati.')
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
            @click="$router.push({ name: 'services' })"
        >
          Nazad na servise
        </button>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          {{ currentService?.service_type ?? 'Detalj servisa' }}
        </h1>
      </div>

      <div v-if="currentService && canManage" class="flex flex-wrap gap-3">
        <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            @click="$router.push({ name: 'service-edit', params: { id: currentService.id } })"
        >
          Uredi servis
        </button>

        <button
            type="button"
            class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
            :disabled="deleting"
            @click="handleDelete"
        >
          {{ deleting ? 'Brisanje...' : 'Obriši servis' }}
        </button>
      </div>
    </div>

    <div v-if="loading && !currentService" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="text-sm text-slate-500">Učitavanje servisa...</div>
    </div>

    <template v-else-if="currentService">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-900">Podaci o servisu</h2>
              <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass(currentService.service_status)"
              >
                {{ statusLabel(currentService.service_status) }}
              </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <div class="text-sm text-slate-500">Vozilo</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentService.vehicle?.name || '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Tablice</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentService.vehicle?.license_plate || '—' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Tip servisa</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentService.service_type }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Datum servisa</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatDate(currentService.service_date) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Kilometraža na servisu</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatMileage(currentService.mileage_at_service) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Sljedeći servis na</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatMileage(currentService.next_service_due_km) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Trenutna kilometraža vozila</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatMileage(currentService.vehicle?.current_mileage ?? null) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Preostalo do servisa</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ dueLabel(currentService.mileage_until_due) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Kreirao</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentService.creator?.name || '—' }}
                </div>
              </div>
            </div>

            <div class="mt-6">
              <div class="text-sm text-slate-500">Napomene</div>
              <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                {{ currentService.notes || 'Nema napomena.' }}
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Sažetak</h2>

            <div class="space-y-4">
              <div>
                <div class="text-sm text-slate-500">Status servisa</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ statusLabel(currentService.service_status) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Preostalo kilometara</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ dueLabel(currentService.mileage_until_due) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Posljednje ažuriranje</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatDate(currentService.updated_at ? currentService.updated_at.slice(0, 10) : null) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>