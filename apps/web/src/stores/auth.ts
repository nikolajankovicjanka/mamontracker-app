import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type AuthUser = {
    id: number
    tenant_id: number | null
    name: string
    email: string
    role: 'platform_super_admin' | 'tenant_admin' | 'tenant_user'
    is_active: boolean
    last_login_at: string | null
}

export type AuthTenant = {
    id: number
    name: string
    slug: string
    plan: string | null
    is_active: boolean
    settings: Record<string, unknown> | null
}

type LoginPayload = {
    email: string
    password: string
    remember?: boolean
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null)
    const tenant = ref<AuthTenant | null>(null)
    const initialized = ref(false)
    const loading = ref(false)

    const isAuthenticated = computed(() => !!user.value)

    async function fetchCsrfCookie(): Promise<void> {
        await api.get('/sanctum/csrf-cookie')
    }

    async function login(payload: LoginPayload): Promise<void> {
        loading.value = true

        try {
            await fetchCsrfCookie()

            const { data } = await api.post('/api/login', payload)

            user.value = data.user
            tenant.value = data.tenant
            initialized.value = true
        } finally {
            loading.value = false
        }
    }

    async function me(): Promise<void> {
        const { data } = await api.get('/api/me')

        user.value = data.user
        tenant.value = data.tenant
    }

    async function init(): Promise<void> {
        if (initialized.value) {
            return
        }

        try {
            await me()
        } catch {
            user.value = null
            tenant.value = null
        } finally {
            initialized.value = true
        }
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/api/logout')
        } finally {
            user.value = null
            tenant.value = null
            initialized.value = true
        }
    }

    return {
        user,
        tenant,
        initialized,
        loading,
        isAuthenticated,
        fetchCsrfCookie,
        login,
        me,
        init,
        logout,
    }
})