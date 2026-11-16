<script setup lang="ts">
const auth = useAuthStore()
const api = useApi()

const today = new Date().toISOString().slice(0, 10)
const startDate = ref('')
const endDate = ref('')
const reason = ref('')
const error = ref('')
const loading = ref(false)

/** Hafta sonları hariç iş günü sayısı (backend ile aynı hesap). */
const workingDays = computed(() => {
  if (!startDate.value || !endDate.value) return 0
  const start = new Date(startDate.value + 'T00:00:00')
  const end = new Date(endDate.value + 'T00:00:00')
  if (end < start) return 0
  let days = 0
  for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const day = d.getDay()
    if (day !== 0 && day !== 6) days++
  }
  return days
})

const submit = async () => {
  error.value = ''
  if (!startDate.value || !endDate.value || !reason.value.trim()) {
    error.value = 'Tüm alanları doldurun.'
    return
  }
  if (endDate.value < startDate.value) {
    error.value = 'Bitiş tarihi başlangıç tarihinden önce olamaz.'
    return
  }
  loading.value = true
  try {
    await api('/api/leaves', {
      method: 'POST',
      body: { startDate: startDate.value, endDate: endDate.value, reason: reason.value.trim() },
    })
    await navigateTo({ path: '/leaves', query: { created: '1' } })
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-semibold mb-6">İzin Talebi Oluştur</h1>

    <form class="card space-y-4" @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="start">Başlangıç tarihi</label>
          <input id="start" v-model="startDate" type="date" class="input" :min="today">
        </div>
        <div>
          <label class="label" for="end">Bitiş tarihi</label>
          <input id="end" v-model="endDate" type="date" class="input" :min="startDate || today">
        </div>
      </div>

      <div>
        <label class="label" for="reason">Açıklama</label>
        <textarea id="reason" v-model="reason" rows="3" class="input" maxlength="255" placeholder="Örn. Yıllık izin" />
      </div>

      <div class="text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-3">
        Talep edilen: <strong>{{ workingDays }}</strong> iş günü ·
        Kalan bakiye: <strong>{{ auth.user?.leaveBalance ?? '-' }}</strong> gün
        <span v-if="auth.user && workingDays > auth.user.leaveBalance" class="block text-rose-600 mt-1">
          Bakiyeniz bu talep için yetersiz.
        </span>
      </div>

      <div v-if="error" class="alert-error">{{ error }}</div>

      <div class="flex gap-3">
        <button type="submit" class="btn-primary" :disabled="loading">{{ loading ? 'Gönderiliyor…' : 'Talebi Gönder' }}</button>
        <NuxtLink to="/leaves" class="btn-ghost">Vazgeç</NuxtLink>
      </div>
    </form>
  </div>
</template>
