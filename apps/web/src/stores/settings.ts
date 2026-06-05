import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type TenantSettings = {
    company_name: string
    contact_email: string
    primary_color: string
    timezone: string
    currency: string
    registration_warning_days: number
    service_due_soon_km: number
    allow_multiple_primary_assignments: boolean
}

export type UserPreferences = {
    language: string
    theme: 'light' | 'dark' | 'system'
}

type SettingsResponse = {
    data: {
        tenant: {
            name: string
            slug: string
            plan: string
            settings: TenantSettings
        }
        preferences: UserPreferences
    }
}

export const useSettingsStore = defineStore('settings', () => {
    const tenantSettings = ref<TenantSettings | null>(null)
    const preferences = ref<UserPreferences | null>(null)
    const loading = ref(false)
    const savingTenant = ref(false)
    const savingPreferences = ref(false)

    async function fetchSettings(): Promise<void> {
        loading.value = true

        try {
            const { data } = await api.get<SettingsResponse>('/api/settings')
            tenantSettings.value = data.data.tenant.settings
            preferences.value = data.data.preferences
        } finally {
            loading.value = false
        }
    }

    async function updateTenantSettings(payload: TenantSettings): Promise<void> {
        savingTenant.value = true

        try {
            const { data } = await api.put<{ data: { settings: TenantSettings } }>('/api/settings/tenant', payload)
            tenantSettings.value = data.data.settings
        } finally {
            savingTenant.value = false
        }
    }

    async function updatePreferences(payload: UserPreferences): Promise<void> {
        savingPreferences.value = true

        try {
            const { data } = await api.put<{ data: { preferences: UserPreferences } }>('/api/settings/preferences', payload)
            preferences.value = data.data.preferences
        } finally {
            savingPreferences.value = false
        }
    }

    return {
        tenantSettings,
        preferences,
        loading,
        savingTenant,
        savingPreferences,
        fetchSettings,
        updateTenantSettings,
        updatePreferences,
    }
})