<script setup lang="ts">
import { reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  email: 'admin@mamont.local',
  password: 'password123',
  remember: true,
})

const errorMessage = ref('')

async function handleSubmit() {
  errorMessage.value = ''

  try {
    await auth.login(form)
    await router.push({ name: 'dashboard' })
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    errorMessage.value =
        axiosError.response?.data?.errors?.email?.[0] ??
        axiosError.response?.data?.message ??
        'Login failed.'
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-100 px-6">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
      <div class="mb-8 text-center">
        <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
          Mamontrucker
        </div>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
          Tenant Login
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Login to manage your fleet, users and devices.
        </p>
      </div>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
          <input
              v-model="form.email"
              type="email"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Password</label>
          <input
              v-model="form.password"
              type="password"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-slate-500"
          />
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-600">
          <input v-model="form.remember" type="checkbox" />
          Remember me
        </label>

        <div
            v-if="errorMessage"
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          {{ errorMessage }}
        </div>

        <button
            type="submit"
            class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
            :disabled="auth.loading"
        >
          {{ auth.loading ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>
    </div>
  </div>
</template>