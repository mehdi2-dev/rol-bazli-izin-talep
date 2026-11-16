<script setup lang="ts">
const auth = useAuthStore()
const api = useApi()

const myLeaves = ref<any[]>([])
const pendingTeam = ref<any[]>([])
const error = ref('')

const load = async () => {
  try {
    await auth.fetchMe()
    myLeaves.value = await api<any[]>('/api/leaves')
    if (auth.isManager) pendingTeam.value = await api<any[]>('/api/admin/leaves/pending')
  } catch (e) {
    error.value = apiError(e)
  }
}
onMounted(load)

const count = (status: string) => myLeaves.value.filter((l) => l.status === status).length
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Hoş geldiniz</h1>
      <p class="text-slate-500">{{ auth.user?.email }}</p>
    </div>

    <div v-if="error" class="alert-error">{{ error }}</div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="card">
        <div class="text-sm text-slate-500">Kalan izin bakiyesi</div>
        <div class="text-3xl font-semibold text-indigo-700">{{ auth.user?.leaveBalance ?? '-' }} <span class="text-base font-normal text-slate-500">gün</span></div>
      </div>
      <div class="card">
        <div class="text-sm text-slate-500">Bekleyen taleplerim</div>
        <div class="text-3xl font-semibold text-amber-600">{{ count('pending') }}</div>
      </div>
      <div class="card">
        <div class="text-sm text-slate-500">Onaylanan</div>
        <div class="text-3xl font-semibold text-emerald-600">{{ count('approved') }}</div>
      </div>
      <div class="card">
        <div class="text-sm text-slate-500">Reddedilen</div>
        <div class="text-3xl font-semibold text-rose-600">{{ count('rejected') }}</div>
      </div>
    </div>

    <div v-if="auth.isManager" class="card flex items-center justify-between">
      <div>
        <div class="font-medium">Ekibinizden onay bekleyen {{ pendingTeam.length }} talep var</div>
        <div class="text-sm text-slate-500">Ekip: {{ auth.user?.department ?? '-' }}</div>
      </div>
      <NuxtLink to="/manager/approvals" class="btn-primary">İncele</NuxtLink>
    </div>

    <div class="flex gap-3">
      <NuxtLink to="/leaves/new" class="btn-primary">Yeni izin talebi</NuxtLink>
      <NuxtLink to="/leaves" class="btn-ghost">Taleplerimi gör</NuxtLink>
    </div>
  </div>
</template>
