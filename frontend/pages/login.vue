<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const submit = async () => {
  error.value = ''
  if (!email.value || !password.value) {
    error.value = 'E-posta ve şifre zorunludur.'
    return
  }
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    await navigateTo('/')
  } catch (e: any) {
    error.value = e?.statusCode === 401 || e?.status === 401 ? 'E-posta veya şifre hatalı.' : apiError(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
    <div class="card w-full max-w-sm">
      <h1 class="text-xl font-semibold mb-1">İzin Talep Sistemi</h1>
      <p class="text-sm text-slate-500 mb-6">Devam etmek için giriş yapın.</p>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="email">E-posta</label>
          <input id="email" v-model="email" type="email" class="input" autocomplete="username" placeholder="ornek@test.com">
        </div>
        <div>
          <label class="label" for="password">Şifre</label>
          <input id="password" v-model="password" type="password" class="input" autocomplete="current-password">
        </div>
        <div v-if="error" class="alert-error">{{ error }}</div>
        <button type="submit" class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Giriş yapılıyor…' : 'Giriş Yap' }}
        </button>
      </form>
    </div>
  </div>
</template>
