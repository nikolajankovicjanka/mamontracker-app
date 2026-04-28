<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
      <p class="mt-2 text-slate-500">
        Welcome back, {{ auth.user?.name }}.
      </p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="text-sm text-slate-500">Tenant</div>
        <div class="mt-2 text-xl font-semibold">{{ auth.tenant?.name }}</div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="text-sm text-slate-500">Plan</div>
        <div class="mt-2 text-xl font-semibold">{{ auth.tenant?.plan ?? '—' }}</div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="text-sm text-slate-500">Role</div>
        <div class="mt-2 text-xl font-semibold">{{ auth.user?.role }}</div>
      </div>
    </div>
  </div>
</template>