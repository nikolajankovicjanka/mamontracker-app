<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import api from '@/lib/axios'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useVehiclesStore } from '@/stores/vehicles'
import { useVehicleAssignmentsStore } from '@/stores/vehicleAssignments'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const vehiclesStore = useVehiclesStore()
const assignmentsStore = useVehicleAssignmentsStore()

const { currentVehicle, loading, deleting } = storeToRefs(vehiclesStore)

const vehicleId = computed(() => Number(route.params.id))
const canManage = computed(() => auth.user?.role === 'tenant_admin')

const assignmentErrorMessage = ref('')
const assignmentSuccessMessage = ref('')
const availableUsers = ref<Array<{
  id: number
  name: string
  email: string
  role: 'tenant_admin' | 'tenant_user'
  is_active: boolean
}>>([])

const assignmentForm = reactive({
  user_id: null as number | null,
  assignment_type: 'primary' as 'primary' | 'secondary' | 'temporary',
  assigned_from: '',
  assigned_until: '',
  notes: '',
  start_mileage: null as number | null,
})

onMounted(async () => {
  await vehiclesStore.fetchVehicle(vehicleId.value)

  if (canManage.value) {
    await loadUsers()
    assignmentForm.assigned_from = getNowInputValue()
  }
})

async function loadUsers() {
  const { data } = await api.get('/api/users', {
    params: {
      per_page: 100,
      status: 'active',
    },
  })

  availableUsers.value = data.data
}

function getNowInputValue() {
  const now = new Date()
  const timezoneOffset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 16)
}

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

function userRoleLabel(value: string | undefined) {
  if (value === 'tenant_admin') return 'Tenant admin'
  if (value === 'tenant_user') return 'Tenant user'
  return value ?? '—'
}

async function handleCreateAssignment() {
  if (!currentVehicle.value) return

  assignmentErrorMessage.value = ''
  assignmentSuccessMessage.value = ''

  try {
    await assignmentsStore.createAssignment({
      vehicle_id: currentVehicle.value.id,
      user_id: Number(assignmentForm.user_id),
      assignment_type: assignmentForm.assignment_type,
      assigned_from: assignmentForm.assigned_from,
      assigned_until: assignmentForm.assigned_until || null,
      notes: assignmentForm.notes.trim() || null,
      start_mileage: assignmentForm.start_mileage,
    })

    assignmentForm.user_id = null
    assignmentForm.assignment_type = 'primary'
    assignmentForm.assigned_from = getNowInputValue()
    assignmentForm.assigned_until = ''
    assignmentForm.notes = ''
    assignmentForm.start_mileage = null

    assignmentSuccessMessage.value = 'Korisnik je uspješno zadužen na vozilo.'

    await vehiclesStore.fetchVehicle(vehicleId.value)
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
      end_mileage: currentVehicle.value?.current_mileage ?? null,
      notes: null,
    })

    assignmentSuccessMessage.value = 'Zaduženje je uspješno završeno.'

    await vehiclesStore.fetchVehicle(vehicleId.value)
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    assignmentErrorMessage.value =
        axiosError.response?.data?.message ??
        'Zaduženje nije moglo biti završeno.'
  }
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
            <div class="mb-5 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-900">Current assignments</h2>
              <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                {{ currentVehicle.active_assignments_count ?? 0 }} active
              </span>
            </div>

            <div
                v-if="!currentVehicle.active_assignments?.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No users are currently assigned to this vehicle.
            </div>

            <div v-else class="space-y-3">
              <div
                  v-for="assignment in currentVehicle.active_assignments"
                  :key="assignment.id"
                  class="rounded-2xl border border-slate-200 p-4"
              >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div class="font-semibold text-slate-900">
                      {{ assignment.user?.name || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ assignment.user?.email || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ userRoleLabel(assignment.user?.role) }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500">
                      {{ assignmentTypeLabel(assignment.assignment_type) }} ·
                      od {{ formatDateTime(assignment.assigned_from) }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      Dodijelio: {{ assignment.assigned_by_user?.name || '—' }}
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
                        v-if="assignment.user"
                        type="button"
                        class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="router.push({ name: 'user-show', params: { id: assignment.user.id } })"
                    >
                      Open user
                    </button>

                    <button
                        v-if="canManage"
                        type="button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"
                        @click="handleEndAssignment(assignment.id)"
                    >
                      End assignment
                    </button>
                  </div>
                </div>

                <div v-if="assignment.notes" class="mt-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                  {{ assignment.notes }}
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Assignment history</h2>

            <div
                v-if="!currentVehicle.assignment_history?.length"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
              No assignment history available.
            </div>

            <div v-else class="space-y-3">
              <div
                  v-for="assignment in currentVehicle.assignment_history"
                  :key="assignment.id"
                  class="rounded-2xl border border-slate-200 p-4"
              >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div>
                    <div class="font-semibold text-slate-900">
                      {{ assignment.user?.name || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      {{ assignment.user?.email || '—' }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500">
                      {{ assignmentTypeLabel(assignment.assignment_type) }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      Od: {{ formatDateTime(assignment.assigned_from) }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">
                      Do: {{ formatDateTime(assignment.unassigned_at || assignment.assigned_until) }}
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
                        v-if="assignment.user"
                        type="button"
                        class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="router.push({ name: 'user-show', params: { id: assignment.user.id } })"
                    >
                      Open user
                    </button>
                  </div>
                </div>
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
          <div
              v-if="canManage"
              class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
          >
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Zaduži vozilo na</h2>

            <form class="space-y-4" @submit.prevent="handleCreateAssignment">
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">User</label>
                <select
                    v-model="assignmentForm.user_id"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                >
                  <option :value="null">Odaberi korisnika</option>
                  <option
                      v-for="user in availableUsers"
                      :key="user.id"
                      :value="user.id"
                  >
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Assignment type</label>
                <select
                    v-model="assignmentForm.assignment_type"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                >
                  <option value="primary">Primary</option>
                  <option value="secondary">Secondary</option>
                  <option value="temporary">Temporary</option>
                </select>
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Assigned from</label>
                <input
                    v-model="assignmentForm.assigned_from"
                    type="datetime-local"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Assigned until (optional)</label>
                <input
                    v-model="assignmentForm.assigned_until"
                    type="datetime-local"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Start mileage</label>
                <input
                    v-model.number="assignmentForm.start_mileage"
                    type="number"
                    min="0"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Notes</label>
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
                {{ assignmentsStore.saving ? 'Saving...' : 'Assign user' }}
              </button>
            </form>
          </div>

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

          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold text-slate-900">Assignment summary</h2>

            <div class="space-y-4">
              <div>
                <div class="text-sm text-slate-500">Active users</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.active_assignments_count ?? 0 }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Total assignment records</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ currentVehicle.assignment_history?.length ?? 0 }}
                </div>
              </div>

              <div>
                <div class="text-sm text-slate-500">Current mileage</div>
                <div class="mt-1 font-semibold text-slate-900">
                  {{ formatMileage(currentVehicle.current_mileage) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>