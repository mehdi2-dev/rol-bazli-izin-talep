<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()

const links = computed(() => {
  const items = [
    { to: '/', label: 'Dashboard' },
    { to: '/leaves/new', label: 'İzin Talebi Oluştur' },
    { to: '/leaves', label: 'İzin Taleplerim' },
  ]
  if (auth.isManager) {
    items.push({ to: '/manager/approvals', label: 'Onay Bekleyenler' })
    items.push({ to: '/manager/users', label: 'Kullanıcı Yönetimi' })
  }
  return items
})

const isActive = (to: string) => (to === '/' ? route.path === '/' : route.path === to)
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <header class="bg-white border-b border-slate-200">
      <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <NuxtLink to="/" class="font-semibold text-indigo-700">İzin Talep Sistemi</NuxtLink>
        <div v-if="auth.user" class="flex items-center gap-4 text-sm">
          <div class="text-right">
            <div class="font-medium">{{ auth.user.email }}</div>
            <div class="text-slate-500">
              {{ auth.isManager ? 'Yönetici' : 'Çalışan' }}<span v-if="auth.user.department"> · {{ auth.user.department }}</span>
            </div>
          </div>
          <button class="btn-ghost" @click="auth.logout()">Çıkış</button>
        </div>
      </div>
      <nav class="max-w-6xl mx-auto px-6 flex gap-1 overflow-x-auto">
        <NuxtLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="px-3 py-3 text-sm border-b-2 whitespace-nowrap"
          :class="isActive(link.to) ? 'border-indigo-600 text-indigo-700 font-medium' : 'border-transparent text-slate-600 hover:text-slate-900'"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>
    </header>
    <main class="max-w-6xl mx-auto px-6 py-8">
      <slot />
    </main>
  </div>
</template>
