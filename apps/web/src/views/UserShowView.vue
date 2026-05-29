<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import api from '@/lib/axios'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore, type UserPermissions } from '@/stores/users'
import { useVehicleAssignmentsStore } from '@/stores/vehicleAssignments'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const usersStore = useUsersStore()
const assignmentsStore = useVehicleAssignmentsStore()

const { currentUser, loading } = storeToRefs(usersStore)

const userId = computed(() => Number(route.params.id))
const canManage = computed(() => auth.user?.role === 'tenant_admin')
const assignmentErrorMessage = ref('')
const assignmentSuccessMessage = ref('')
const availableVehicles = ref<Array<{
  id: number
  name: string
  license_plate: string | null
  status: string
  current_mileage: number | null
}>>([])

const assignmentForm = reactive({
  vehicle_id: null as number | null,
  assignment_type: 'primary' as 'primary' | 'secondary' | 'temporary',
  assigned_from: '',
  assigned_until: '',
  notes: '',
  start_mileage: null as number | null,
})

const permissionLabels: Record<keyof UserPermissions, string> = {
  can_manage_users: 'Upravljanje korisnicima',
  can_assign_vehicles: 'Zaduživanje vozila',
  can_manage_services: 'Upravljanje servisima',
  can_manage_registrations: 'Upravljanje registracijama',
  can_manage_gps_devices: 'Upravljanje GPS uređajima',
  can_log_fuel: 'Evidencija goriva',
  can_view_reports: 'Pregled izvještaja',
  can_view_alerts: 'Pregled obavještenja',
}

onMounted(async () => {
  await loadUser()
  await loadVehicles()
  assignmentForm.assigned_from = getNowInputValue()
})

async function loadUser() {
  await usersStore.fetchUser(userId.value)
}

async function loadVehicles() {
  const { data } = await api.get('/api/vehicles', {
    params: {
      per_page: 100,
    },
  })

  availableVehicles.value = data.data
}

function roleLabel(value: string | undefined) {
  if (value === 'tenant_admin') return 'Tenant admin'
  if (value === 'tenant_user') return 'Tenant user'
  return value ?? '—'
}

function statusBadgeClass(isActive: boolean | undefined) {
  return isActive
      ? 'bg-emerald-50 text-emerald-700'
      : 'bg-slate-100 text-slate-700'
}

function assignmentTypeLabel(value: string) {
  if (value === 'primary') return 'Primarno'
  if (value === 'secondary') return 'Sekundarno'
  return 'Privremeno'
}

function assignmentStatusBadgeClass(value: string) {
  if (value === 'active') return 'bg-emerald-50 text-emerald-700'
  if (value === 'ended') return 'bg-slate-100 text-slate-700'
  return 'bg-rose-50 text-rose-700'
}

function assignmentStatusLabel(value: string) {
  if (value === 'active') return 'Aktivno'
  if (value === 'ended') return 'Završeno'
  return 'Otkazano'
}

function formatDateTime(value: string | null) {
  if (!value) return '—'

  return new Intl.DateTimeFormat('sr-Latn-RS', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function formatMileage(value: number | null | undefined) {
  if (value === null || value === undefined) return '—'
  return `${new Intl.NumberFormat('sr-Latn-RS').format(value)} km`
}

function getNowInputValue() {
  const now = new Date()
  const timezoneOffset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 16)
}

const enabledPermissions = computed(() => {
  if (!currentUser.value) return []

  return Object.entries(currentUser.value.permissions)
      .filter(([, value]) => value)
      .map(([key]) => permissionLabels[key as keyof UserPermissions])
})

async function handleCreateAssignment() {
  if (!currentUser.value) return

  assignmentErrorMessage.value = ''
  assignmentSuccessMessage.value = ''

  try {
    await assignmentsStore.createAssignment({
      vehicle_id: Number(assignmentForm.vehicle_id),
      user_id: currentUser.value.id,
      assignment_type: assignmentForm.assignment_type,
      assigned_from: assignmentForm.assigned_from,
      assigned_until: assignmentForm.assigned_until || null,
      notes: assignmentForm.notes.trim() || null,
      start_mileage: assignmentForm.start_mileage,
    })

    assignmentForm.vehicle_id = null
    assignmentForm.assignment_type = 'primary'
    assignmentForm.assigned_from = getNowInputValue()
    assignmentForm.assigned_until = ''
    assignmentForm.notes = ''
    assignmentForm.start_mileage = null

    assignmentSuccessMessage.value = 'Vozilo je uspješno zaduženo korisniku.'

    await loadUser()
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    assignmentErrorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'Zaduženje nije moglo biti kreirano.'
  }
}

async function handleEndAssignment(assignmentId: number) {
  assignmentErrorMessage.value = ''
  assignmentSuccessMessage.value = ''

  try {
    await assignmentsStore.endAssignment(assignmentId, {
      unassigned_at: new Date().toISOString(),
      end_mileage: null,
      notes: null,
    })

    assignmentSuccessMessage.value = 'Zaduženje je uspješno završeno.'

    await loadUser()
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    assignmentErrorMessage.value =
        axiosError.response?.data?.message ??
        'Zaduženje nije moglo biti završeno.'
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
            @click="$router.push({ name: 'users' })"
        >
          Nazad na korisnike
        </button>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          {{ currentUser?.name ?? 'Detalj korisnika' }}
        </h1>
      </div>

      <div v-if="currentUser && canManage" class="flex gap-3">
        <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            @click="router.push({ name: 'user-edit', params: { id: currentUser.id } })"
        >
          Uredi korisnika
        </button>
      </div>
    </div>

    <div
        v-if="loading && !currentUser"
        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
    >
      <div class="text-sm text-slate-500">Učitavanje korisnika...</div>
    </div>

    <template v-else-if="currentUser">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-900">Osnovni podaci</h2>
              <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusBadgeClass(currentUser.is_active)"
              >
                {{ currentUser.is_active ? 'Aktivan' : 'Neaktivan' }}
              </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <div class="text-sm text-slate-500">Ime i prezime</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentUser.name }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Email</div>
                <div class="mt-1 font-semibold text-slate-900">{{ currentUser.email }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Rola</div>
                <div class="mt-1 font-semibold text-slate-900">{{ roleLabel(currentUser.role) }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Posljednji login</div>
                <div class="mt-1 font-semibold text-slate-900">{{ formatDateTime(currentUser.last_login_at) }}</div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Aktivna zaduženja</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentUser.active_assignments?.length ?? 0 }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Ukupna istorija zaduženja</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentUser.assignment_history?.length ?? 0 }}
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Privilegije</h2>

            <div
                v-if="!enabledPermissions.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              Nema posebnih privilegija.
            </div>

            <div
                v-else
                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
            >
              <div
                  v-for="permission in enabledPermissions"
                  :key="permission"
                  class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700"
              >
                {{ permission }}
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-900">Aktivna zaduženja</h2>
            </div>

            <div
                v-if="!currentUser.active_assignments?.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              Korisnik trenutno nema aktivno zadužena vozila.
            </div>

            <div v-else class="space-y-3">
              <div
                  v-for="assignment in currentUser.active_assignments"
                  :key="assignment.id"
                  class="rounded-2xl border border-slate-200 p-4"
              >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div class="font-semibold text-slate-900">
                      {{ assignment.vehicle?.name || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ assignment.vehicle?.license_plate || '—' }} · {{ assignmentTypeLabel(assignment.assignment_type) }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500">
                      Od: {{ formatDateTime(assignment.assigned_from) }}
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="assignmentStatusBadgeClass(assignment.status)"
                    >
                      {{ assignmentStatusLabel(assignment.status) }}
                    </span>

                    <button
                        v-if="canManage"
                        type="button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"
                        @click="handleEndAssignment(assignment.id)"
                    >
                      Razduži vozilo
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Istorija zaduženja</h2>

            <div
                v-if="!currentUser.assignment_history?.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              Nema istorije zaduženja.
            </div>

            <div v-else class="space-y-3">
              <div
                  v-for="assignment in currentUser.assignment_history"
                  :key="assignment.id"
                  class="rounded-2xl border border-slate-200 p-4"
              >
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div class="font-semibold text-slate-900">
                      {{ assignment.vehicle?.name || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ assignment.vehicle?.license_plate || '—' }} · {{ assignmentTypeLabel(assignment.assignment_type) }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500">
                      Od: {{ formatDateTime(assignment.assigned_from) }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      Do: {{ formatDateTime(assignment.unassigned_at || assignment.assigned_until) }}
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="assignmentStatusBadgeClass(assignment.status)"
                    >
                      {{ assignmentStatusLabel(assignment.status) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div
              v-if="canManage"
              class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
          >
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Novo zaduženje vozila</h2>

            <form class="space-y-4" @submit.prevent="handleCreateAssignment">
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Vozilo</label>
                <select
                    v-model="assignmentForm.vehicle_id"
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
                <label class="mb-2 block text-sm font-medium text-slate-700">Tip zaduženja</label>
                <select
                    v-model="assignmentForm.assignment_type"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                >
                  <option value="primary">Primarno</option>
                  <option value="secondary">Sekundarno</option>
                  <option value="temporary">Privremeno</option>
                </select>
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Zadužen od</label>
                <input
                    v-model="assignmentForm.assigned_from"
                    type="datetime-local"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Zadužen do (opcionalno)</label>
                <input
                    v-model="assignmentForm.assigned_until"
                    type="datetime-local"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Početna kilometraža</label>
                <input
                    v-model.number="assignmentForm.start_mileage"
                    type="number"
                    min="0"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Napomene</label>
                <textarea
                    v-model="assignmentForm.notes"
                    rows="4"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div
                  v-if="assignmentErrorMessage"
                  class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
              >
                {{ assignmentErrorMessage }}
              </div>

              <div
                  v-if="assignmentSuccessMessage"
                  class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
              >
                {{ assignmentSuccessMessage }}
              </div>

              <button
                  type="submit"
                  class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                  :disabled="assignmentsStore.saving"
              >
                {{ assignmentsStore.saving ? 'Čuvanje...' : 'Zaduži vozilo' }}
              </button>
            </form>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Sažetak</h2>

            <div class="space-y-4">
              <div>
                <div class="text-sm text-slate-500">Status korisnika</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentUser.is_active ? 'Aktivan' : 'Neaktivan' }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Rola</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ roleLabel(currentUser.role) }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Aktivna zaduženja</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentUser.active_assignments?.length ?? 0 }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Ukupno zaduženja</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentUser.assignment_history?.length ?? 0 }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>