import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/axios'

export type UserPermissions = {
    can_manage_users: boolean
    can_assign_vehicles: boolean
    can_manage_services: boolean
    can_manage_registrations: boolean
    can_manage_gps_devices: boolean
    can_log_fuel: boolean
    can_view_reports: boolean
    can_view_alerts: boolean
}

export type UserAssignmentItem = {
    id: number
    vehicle_id: number
    user_id: number
    assigned_by: number | null
    assignment_type: 'primary' | 'secondary' | 'temporary'
    assigned_from: string | null
    assigned_until: string | null
    unassigned_at: string | null
    status: 'active' | 'ended' | 'cancelled'
    notes: string | null
    start_mileage: number | null
    end_mileage: number | null
    vehicle: {
        id: number
        name: string
        license_plate: string | null
        status: string
        current_mileage: number | null
    } | null
    assigned_by_user: {
        id: number
        name: string
    } | null
}

export type UserItem = {
    id: number
    tenant_id: number
    name: string
    email: string
    role: 'tenant_admin' | 'tenant_user'
    permissions: UserPermissions
    is_active: boolean
    last_login_at: string | null
    vehicle_assignments_count: number | null
    active_vehicle_assignments_count: number | null
    created_at: string | null
    updated_at: string | null
    tenant?: {
        id: number
        name: string
        slug: string
        plan: string
    } | null
    active_assignments?: UserAssignmentItem[]
    assignment_history?: UserAssignmentItem[]
}

type UsersResponse = {
    current_page: number
    data: UserItem[]
    last_page: number
    per_page: number
    total: number
}

type UserPayload = {
    name: string
    email: string
    password?: string
    role: 'tenant_admin' | 'tenant_user'
    permissions: UserPermissions
    is_active: boolean
}

const defaultPermissions = (): UserPermissions => ({
    can_manage_users: false,
    can_assign_vehicles: false,
    can_manage_services: false,
    can_manage_registrations: false,
    can_manage_gps_devices: false,
    can_log_fuel: false,
    can_view_reports: false,
    can_view_alerts: false,
})

export const useUsersStore = defineStore('users', () => {
    const items = ref<UserItem[]>([])
    const currentUser = ref<UserItem | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const updatingStatus = ref(false)

    const page = ref(1)
    const perPage = ref(10)
    const total = ref(0)
    const lastPage = ref(1)

    const search = ref('')
    const role = ref('')
    const status = ref('')

    const hasItems = computed(() => items.value.length > 0)

    async function fetchUsers(customPage?: number): Promise<void> {
        loading.value = true

        try {
            const targetPage = customPage ?? page.value

            const { data } = await api.get<UsersResponse>('/api/users', {
                params: {
                    page: targetPage,
                    per_page: perPage.value,
                    search: search.value || undefined,
                    role: role.value || undefined,
                    status: status.value || undefined,
                },
            })

            items.value = data.data
            page.value = data.current_page
            perPage.value = data.per_page
            total.value = data.total
            lastPage.value = data.last_page
        } finally {
            loading.value = false
        }
    }

    async function fetchUser(id: number | string): Promise<UserItem> {
        loading.value = true

        try {
            const { data } = await api.get<{ data: UserItem }>(`/api/users/${id}`)
            currentUser.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
    }

    async function createUser(payload: UserPayload): Promise<UserItem> {
        saving.value = true

        try {
            const { data } = await api.post<{ data: UserItem }>('/api/users', payload)
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateUser(id: number | string, payload: UserPayload): Promise<UserItem> {
        saving.value = true

        try {
            const { data } = await api.put<{ data: UserItem }>(`/api/users/${id}`, payload)
            currentUser.value = data.data
            return data.data
        } finally {
            saving.value = false
        }
    }

    async function updateUserStatus(id: number | string, isActive: boolean): Promise<UserItem> {
        updatingStatus.value = true

        try {
            const { data } = await api.patch<{ data: UserItem }>(`/api/users/${id}/status`, {
                is_active: isActive,
            })

            const index = items.value.findIndex((item) => item.id === Number(id))

            if (index !== -1) {
                items.value[index] = {
                    ...items.value[index],
                    ...data.data,
                }
            }

            if (currentUser.value?.id === Number(id)) {
                currentUser.value = {
                    ...currentUser.value,
                    ...data.data,
                }
            }

            return data.data
        } finally {
            updatingStatus.value = false
        }
    }

    async function applyFilters(): Promise<void> {
        page.value = 1
        await fetchUsers(1)
    }

    async function goToPage(targetPage: number): Promise<void> {
        if (targetPage < 1 || targetPage > lastPage.value) {
            return
        }

        await fetchUsers(targetPage)
    }

    function clearCurrentUser() {
        currentUser.value = null
    }

    return {
        items,
        currentUser,
        loading,
        saving,
        updatingStatus,
        page,
        perPage,
        total,
        lastPage,
        search,
        role,
        status,
        hasItems,
        defaultPermissions,
        fetchUsers,
        fetchUser,
        createUser,
        updateUser,
        updateUserStatus,
        applyFilters,
        goToPage,
        clearCurrentUser,
    }
})