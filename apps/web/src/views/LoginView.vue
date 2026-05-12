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
        'Prijava nije uspjela.'
  }
}
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-slate-100">
    <div class="absolute inset-0">
      <div class="absolute left-[-120px] top-[-120px] h-72 w-72 rounded-full bg-blue-200/40 blur-3xl" />
      <div class="absolute bottom-[-140px] right-[-100px] h-80 w-80 rounded-full bg-sky-200/40 blur-3xl" />
      <div class="absolute left-1/3 top-1/4 h-56 w-56 rounded-full bg-indigo-100/50 blur-3xl" />
    </div>

    <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
      <div class="grid w-full max-w-6xl overflow-hidden rounded-[32px] border border-white/70 bg-white/80 shadow-2xl backdrop-blur lg:grid-cols-[1.05fr_0.95fr]">
        <div class="relative hidden min-h-[680px] overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-sky-500 p-10 text-white lg:flex lg:items-center lg:justify-center">
          <div class="absolute inset-0 opacity-20">
            <div class="absolute left-10 top-16 h-48 w-48 rounded-full border border-white/30" />
            <div class="absolute right-12 top-28 h-24 w-24 rounded-full border border-white/30" />
            <div class="absolute bottom-16 left-16 h-64 w-64 rounded-full border border-white/20" />
          </div>

          <div class="relative flex flex-col items-center text-center">
            <div class="relative flex h-[300px] w-full items-center justify-center">
              <img
                  src="/mamontracker-logo-white.png"
                  alt="Mamontracker logo"
                  class="relative z-10 w-full max-w-[340px] object-contain opacity-95 drop-shadow-[0_24px_50px_rgba(15,23,42,0.22)]"
              />
            </div>

            <div class="mt-8 max-w-md">
              <h1 class="text-3xl font-bold tracking-tight">
                Mamontracker platforma
              </h1>
              <p class="mt-4 text-base leading-7 text-white/85">
                Upravljanje voznim parkom, GPS uređajima, korisnicima i operativnim podacima na jednom mjestu.
              </p>
            </div>
          </div>
        </div>

        <div class="flex min-h-[680px] items-center justify-center bg-white/80 p-6 sm:p-10">
          <div class="w-full max-w-md">
            <div class="mb-8 text-center lg:text-left">
              <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">
                Mamontracker
              </div>
              <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                Prijava
              </h2>
              <p class="mt-2 text-sm leading-6 text-slate-500">
                Prijavite se za pristup vozilima, GPS uređajima i pregledima unutar vašeg tenant naloga.
              </p>
            </div>

            <form class="space-y-5" @submit.prevent="handleSubmit">
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Email adresa</label>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white"
                    placeholder="unesite email adresu"
                />
              </div>

              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Lozinka</label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white"
                    placeholder="unesite lozinku"
                />
              </div>

              <div class="flex items-center justify-between gap-4">
                <label class="flex items-center gap-3 text-sm text-slate-600">
                  <input
                      v-model="form.remember"
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>Zapamti me</span>
                </label>

                <span class="text-xs font-medium text-slate-400">
                  Siguran pristup
                </span>
              </div>

              <div
                  v-if="errorMessage"
                  class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
              >
                {{ errorMessage }}
              </div>

              <button
                  type="submit"
                  class="w-full rounded-2xl bg-slate-900 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/10 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="auth.loading"
              >
                {{ auth.loading ? 'Prijava u toku...' : 'Prijava' }}
              </button>
            </form>

            <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-slate-800">
                Upravljanje voznim parkom
              </div>
              <p class="mt-1 text-sm leading-6 text-slate-500">
                Svaki tenant ima pristup isključivo svojim vozilima, uređajima, korisnicima i operativnim podacima.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>