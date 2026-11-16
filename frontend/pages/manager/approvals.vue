<script setup lang="ts">
definePageMeta({ requiresManager: true })

const api = useApi()
const auth = useAuthStore()

const tab = ref<'pending' | 'all'>('pending')
const leaves = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const message = ref('')
const busyId = ref<number | null>(null)
const rejectingId = ref<number | null>(null)
const rejectReason = ref('')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    leaves.value = await api<any[]>(tab.value === 'pending' ? '/api/admin/leaves/pending' : '/api/admin/leaves')
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(tab, load)

const approve = async (id: number) => {
  busyId.value = id
  error.value = ''
  message.value = ''
  try {
    const res: any = await api(`/api/admin/leaves/${id}/approve`, { method: 'PATCH' })
    message.value = `${res.message} Çalışanın kalan bakiyesi: ${res.remainingBalance} gün.`
    await load()
  } catch (e) {
    error.value = apiError(e)
  } finally {
    busyId.value = null
  }
}

const startReject = (id: number) => {
  rejectingId.value = id
  rejectReason.value = ''
  error.value = ''
}

const confirmReject = async (id: number) => {
  // Kural: Reddedilen izinlerde açıklama girilmelidir.
  if (!rejectReason.value.trim()) {
    error.value = 'Ret işleminde açıklama zorunludur.'
    return
  }
  busyId.value = id
  error.value = ''
  message.value = ''
  try {
    const res: any = await api(`/api/admin/leaves/${id}/reject`, { method: 'PATCH', body: { reason: rejectReason.value.trim() } })
    message.value = res.message
    rejectingId.value = null
    await load()
  } catch (e) {
    error.value = apiError(e)
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Ekip İzin Talepleri</h1>
      <p class="text-slate-500">Yalnızca {{ auth.user?.department ?? 'ekibinizin' }} ekibindeki talepler listelenir.</p>
    </div>

    <div class="flex gap-2">
      <button :class="tab === 'pending' ? 'btn-primary' : 'btn-ghost'" @click="tab = 'pending'">Onay bekleyenler</button>
      <button :class="tab === 'all' ? 'btn-primary' : 'btn-ghost'" @click="tab = 'all'">Tüm talepler</button>
    </div>

    <div v-if="message" class="alert-success">{{ message }}</div>
    <div v-if="error" class="alert-error">{{ error }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th>Çalışan</th>
            <th>Tarih aralığı</th>
            <th>Gün</th>
            <th>Açıklama</th>
            <th>Durum</th>
            <th class="w-80">İşlem</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="text-center text-slate-500">Yükleniyor…</td></tr>
          <tr v-else-if="!leaves.length"><td colspan="6" class="text-center text-slate-500">Gösterilecek talep yok.</td></tr>
          <tr v-for="leave in leaves" :key="leave.id">
            <td>{{ leave.user }}</td>
            <td class="whitespace-nowrap">{{ leave.startDate }} → {{ leave.endDate }}</td>
            <td>{{ leave.days }}</td>
            <td>{{ leave.reason }}</td>
            <td>
              <StatusBadge :status="leave.status" />
              <div v-if="leave.rejectionReason" class="text-xs text-slate-500 mt-1">{{ leave.rejectionReason }}</div>
            </td>
            <td>
              <template v-if="leave.status === 'pending' && leave.user !== auth.user?.email">
                <div v-if="rejectingId === leave.id" class="space-y-2">
                  <textarea v-model="rejectReason" rows="2" class="input" placeholder="Ret açıklaması (zorunlu)" />
                  <div class="flex gap-2">
                    <button class="btn-danger" :disabled="busyId === leave.id" @click="confirmReject(leave.id)">Reddet</button>
                    <button class="btn-ghost" @click="rejectingId = null">Vazgeç</button>
                  </div>
                </div>
                <div v-else class="flex gap-2">
                  <button class="btn-success" :disabled="busyId === leave.id" @click="approve(leave.id)">Onayla</button>
                  <button class="btn-ghost" :disabled="busyId === leave.id" @click="startReject(leave.id)">Reddet</button>
                </div>
              </template>
              <span v-else class="text-slate-400">-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
