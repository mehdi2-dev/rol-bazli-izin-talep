<script setup lang="ts">
const api = useApi()
const route = useRoute()

const leaves = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const success = computed(() => (route.query.created ? 'İzin talebiniz oluşturuldu ve yönetici onayına gönderildi.' : ''))

onMounted(async () => {
  try {
    leaves.value = await api<any[]>('/api/leaves')
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">İzin Taleplerim</h1>
      <NuxtLink to="/leaves/new" class="btn-primary">Yeni talep</NuxtLink>
    </div>

    <div v-if="success" class="alert-success">{{ success }}</div>
    <div v-if="error" class="alert-error">{{ error }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th>Tarih aralığı</th>
            <th>Gün</th>
            <th>Açıklama</th>
            <th>Durum</th>
            <th>Ret açıklaması</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5" class="text-center text-slate-500">Yükleniyor…</td></tr>
          <tr v-else-if="!leaves.length"><td colspan="5" class="text-center text-slate-500">Henüz izin talebiniz yok.</td></tr>
          <tr v-for="leave in leaves" :key="leave.id">
            <td class="whitespace-nowrap">{{ leave.startDate }} → {{ leave.endDate }}</td>
            <td>{{ leave.days }}</td>
            <td>{{ leave.reason }}</td>
            <td><StatusBadge :status="leave.status" /></td>
            <td class="text-slate-600">{{ leave.rejectionReason || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
