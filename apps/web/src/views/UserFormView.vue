<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { AxiosError } from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useUsersStore, type UserPermissions } from '@/stores/users'

const route = useRoute()
const router = useRouter()
const usersStore = useUsersStore()

const isEdit = computed(() => route.name === 'user-edit')
const userId = computed(() => Number(route.params.id))
const errorMessage = ref('')
const initialLoading = ref(false)

const permissionOptions: Array<{ key: keyof UserPermissions; label: string }> = [
  { key: 'can_manage_users', label: 'Upravljanje korisnicima' },
  { key: 'can_assign_vehicles', label: 'Zaduživanje vozila' },
  { key: 'can_manage_services', label: 'Upravljanje servisima' },
  { key: 'can_manage_registrations', label: 'Upravljanje registracijama' },
  { key: 'can_manage_gps_devices', label: 'Upravljanje GPS uređajima' },
  { key: 'can_log_fuel', label: 'Evidencija goriva' },
  { key: 'can_view_reports', label: 'Pregled izvještaja' },
  { key: 'can_view_alerts', label: 'Pregled obavještenja' },
]

function createDefaultPermissions(): UserPermissions {
  return usersStore.defaultPermissions()
}

function createAllPermissions(): UserPermissions {
  return {
    can_manage_users: true,
    can_assign_vehicles: true,
    can_manage_services: true,
    can_manage_registrations: true,
    can_manage_gps_devices: true,
    can_log_fuel: true,
    can_view_reports: true,
    can_view_alerts: true,
  }
}

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'tenant_user' as 'tenant_admin' | 'tenant_user',
  is_active: true,
  permissions: createDefaultPermissions(),
})

watch(
    () => form.role,
    (value) => {
      if (value === 'tenant_admin') {
        form.permissions = createAllPermissions()
      }
    },
)

onMounted(async () => {
  if (!isEdit.value) {
    return
  }

  initialLoading.value = true
  errorMessage.value = ''

  try {
    const user = await usersStore.fetchUser(userId.value)

    form.name = user.name
    form.email = user.email
    form.password = ''
    form.role = user.role
    form.is_active = user.is_active
    form.permissions = {
      ...createDefaultPermissions(),
      ...user.permissions,
    }
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string }>
    errorMessage.value = axiosError.response?.data?.message ?? 'Korisnik nije mogao biti učitan.'
  } finally {
    initialLoading.value = false
  }
})

async function handleSubmit() {
  errorMessage.value = ''

  try {
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      role: form.role,
      is_active: form.is_active,
      permissions: form.role === 'tenant_admin' ? createAllPermissions() : form.permissions,
      ...(form.password.trim() ? { password: form.password.trim() } : {}),
    }

    if (isEdit.value) {
      const user = await usersStore.updateUser(userId.value, payload)
      await router.push({ name: 'user-show', params: { id: user.id } })
    } else {
      const user = await usersStore.createUser({
        ...payload,
        password: form.password.trim(),
      })
      await router.push({ name: 'user-show', params: { id: user.id } })
    }
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        'Korisnik nije mogao biti sačuvan.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <button
          type="button"
          class="mb-3 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="$router.push({ name: 'users' })"
      >
        Nazad na korisnike
      </button>

      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ isEdit ? 'Uredi korisnika' : 'Dodaj korisnika' }}
      </h1>
    </div>

    <div
        v-if="initialLoading"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="text-sm text-slate-500">Učitavanje korisnika...</div>
    </div>

    <div
        v-else
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Ime i prezime</label>
            <input
                v-model="form.name"
                type="text"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
            <input
                v-model="form.email"
                type="email"
                required
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">
              {{ isEdit ? 'Nova lozinka (opcionalno)' : 'Lozinka' }}
            </label>
            <input
                v-model="form.password"
                :required="!isEdit"
                type="password"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            />
          </div>

          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Rola</label>
            <select
                v-model="form.role"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
            >
              <option value="tenant_admin">Tenant admin</option>
              <option value="tenant_user">Tenant user</option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="flex items-center gap-3 text-sm text-slate-700">
              <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300"
              />
              <span>Korisnik je aktivan</span>
            </label>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 text-sm font-semibold text-slate-900">
            Privilegije
          </div>

          <div
              v-if="form.role === 'tenant_admin'"
              class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700"
          >
            Tenant admin automatski ima sve privilegije.
          </div>

          <div
              v-else
              class="grid grid-cols-1 gap-3 sm:grid-cols-2"
          >
            <label
                v-for="option in permissionOptions"
                :key="option.key"
                class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
            >
              <input
                  v-model="form.permissions[option.key]"
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300"
              />
              <span>{{ option.label }}</span>
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
              :disabled="usersStore.saving"
          >
            {{ usersStore.saving ? 'Čuvanje...' : isEdit ? 'Sačuvaj izmjene' : 'Dodaj korisnika' }}
          </button>

          <button
              type="button"
              class="rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
              @click="$router.push({ name: 'users' })"
          >
            Odustani
          </button>
        </div>
      </form>
    </div>
  </div>
</template>