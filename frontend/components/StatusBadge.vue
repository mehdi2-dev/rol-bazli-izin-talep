<script setup lang="ts">
const props = defineProps<{ status: string }>()

const map: Record<string, { label: string; cls: string }> = {
  pending: { label: 'Beklemede', cls: 'bg-amber-100 text-amber-800' },
  approved: { label: 'Onaylandı', cls: 'bg-emerald-100 text-emerald-800' },
  rejected: { label: 'Reddedildi', cls: 'bg-rose-100 text-rose-800' },
}
const info = computed(() => map[props.status] ?? { label: props.status, cls: 'bg-slate-100 text-slate-700' })
</script>

<template>
  <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium" :class="info.cls">{{ info.label }}</span>
</template>
