<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-8">
    <h1 class="text-2xl font-bold">{{ title }}</h1>
    <p class="mt-3 text-slate-500">
      This page is the next step after auth and layout.
    </p>
  </div>
</template>