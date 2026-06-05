<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'

const { t, locale } = useI18n()
const auth = useAuthStore()
const settingsStore = useSettingsStore()

const canManageTenant = computed(() => auth.user?.role === 'tenant_admin')

const tenantForm = reactive({
  company_name: '',
  contact_email: '',
  primary_color: '#2563eb',
  timezone: 'Europe/Sarajevo',
  currency: 'EUR',
  registration_warning_days: 7,
  service_due_soon_km: 1000,
  allow_multiple_primary_assignments: false,
})

const preferencesForm = reactive({
  language: 'en',
  theme: 'light' as 'light' | 'dark' | 'system',
})

const tenantMessage = ref('')
const preferencesMessage = ref('')
const tenantError = ref('')
const preferencesError = ref('')

onMounted(async () => {
  await settingsStore.fetchSettings()

  if (settingsStore.tenantSettings) {
    Object.assign(tenantForm, settingsStore.tenantSettings)
  }

  if (settingsStore.preferences) {
    Object.assign(preferencesForm, settingsStore.preferences)
    locale.value = settingsStore.preferences.language
  }
})

async function saveTenantSettings() {
  tenantMessage.value = ''
  tenantError.value = ''

  try {
    await settingsStore.updateTenantSettings({ ...tenantForm })
    tenantMessage.value = t('settings.messages.tenantSaved')
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    tenantError.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        t('settings.messages.saveError')
  }
}

async function savePreferences() {
  preferencesMessage.value = ''
  preferencesError.value = ''

  try {
    await settingsStore.updatePreferences({ ...preferencesForm })
    locale.value = preferencesForm.language
    preferencesMessage.value = t('settings.messages.preferencesSaved')
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    preferencesError.value =
        axiosError.response?.data?.message ??
        Object.values(axiosError.response?.data?.errors ?? {}).flat()[0] ??
        t('settings.messages.saveError')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ t('settings.title') }}
      </h1>
      <p class="mt-2 text-sm text-slate-500">
        {{ t('settings.subtitle') }}
      </p>
    </div>

    <div
        v-if="settingsStore.loading"
        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-sm text-slate-500"
    >
      {{ t('settings.loading') }}
    </div>

    <template v-else>
      <div
          v-if="canManageTenant"
          class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <h2 class="mb-5 text-lg font-semibold text-slate-900">
          {{ t('settings.sections.tenant') }}
        </h2>

        <form class="space-y-5" @submit.prevent="saveTenantSettings">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.companyName') }}
              </label>
              <input
                  v-model="tenantForm.company_name"
                  type="text"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.contactEmail') }}
              </label>
              <input
                  v-model="tenantForm.contact_email"
                  type="email"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.primaryColor') }}
              </label>
              <input
                  v-model="tenantForm.primary_color"
                  type="color"
                  class="h-12 w-full rounded-2xl border border-slate-300 px-2 py-2"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.timezone') }}
              </label>
              <input
                  v-model="tenantForm.timezone"
                  type="text"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.currency') }}
              </label>
              <input
                  v-model="tenantForm.currency"
                  type="text"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.registrationWarningDays') }}
              </label>
              <input
                  v-model.number="tenantForm.registration_warning_days"
                  type="number"
                  min="1"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.serviceDueSoonKm') }}
              </label>
              <input
                  v-model.number="tenantForm.service_due_soon_km"
                  type="number"
                  min="1"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="flex items-center gap-3 text-sm text-slate-700">
                <input
                    v-model="tenantForm.allow_multiple_primary_assignments"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300"
                />
                <span>{{ t('settings.fields.allowMultiplePrimaryAssignments') }}</span>
              </label>
            </div>
          </div>

          <div
              v-if="tenantError"
              class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
          >
            {{ tenantError }}
          </div>

          <div
              v-if="tenantMessage"
              class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
          >
            {{ tenantMessage }}
          </div>

          <button
              type="submit"
              class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
              :disabled="settingsStore.savingTenant"
          >
            {{ settingsStore.savingTenant ? t('settings.actions.saving') : t('settings.actions.saveTenant') }}
          </button>
        </form>
      </div>

      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-5 text-lg font-semibold text-slate-900">
          {{ t('settings.sections.preferences') }}
        </h2>

        <form class="space-y-5" @submit.prevent="savePreferences">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.language') }}
              </label>
              <select
                  v-model="preferencesForm.language"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              >
                <option value="en">English</option>
                <option value="sr-Latn">Srpski (Latinica)</option>
                <option value="sr-Cyrl">Српски (Ћирилица)</option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ t('settings.fields.theme') }}
              </label>
              <select
                  v-model="preferencesForm.theme"
                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-slate-500"
              >
                <option value="light">{{ t('settings.theme.light') }}</option>
                <option value="dark">{{ t('settings.theme.dark') }}</option>
                <option value="system">{{ t('settings.theme.system') }}</option>
              </select>
            </div>
          </div>

          <div
              v-if="preferencesError"
              class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
          >
            {{ preferencesError }}
          </div>

          <div
              v-if="preferencesMessage"
              class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
          >
            {{ preferencesMessage }}
          </div>

          <button
              type="submit"
              class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
              :disabled="settingsStore.savingPreferences"
          >
            {{ settingsStore.savingPreferences ? t('settings.actions.saving') : t('settings.actions.savePreferences') }}
          </button>
        </form>
      </div>
    </template>
  </div>
</template>