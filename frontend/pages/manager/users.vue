<script setup lang="ts">
definePageMeta({ requiresManager: true })

const api = useApi()
const auth = useAuthStore()

const users = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const message = ref('')

const form = reactive({ email: '', password: '', leaveBalance: 14 })
const saving = ref(false)

const editingId = ref<number | null>(null)
const editBalance = ref(0)

const load = async () => {
  loading.value = true
  try {
    users.value = await api<any[]>('/api/admin/users')
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

const createUser = async () => {
  error.value = ''
  message.value = ''
  saving.value = true
  try {
    const res: any = await api('/api/admin/users', {
      method: 'POST',
      body: { email: form.email.trim(), password: form.password, leaveBalance: Number(form.leaveBalance) },
    })
    message.value = `${res.user.email} ekibinize eklendi.`
    form.email = ''
    form.password = ''
    form.leaveBalance = 14
    await load()
  } catch (e) {
    error.value = apiError(e)
  } finally {
    saving.value = false
  }
}

const startEdit = (user: any) => {
  editingId.value = user.id
  editBalance.value = user.leaveBalance
}

const saveBalance = async (id: number) => {
  error.value = ''
  message.value = ''
  try {
    await api(`/api/admin/users/${id}`, { method: 'PATCH', body: { leaveBalance: Number(editBalance.value) } })
    message.value = 'İzin bakiyesi güncellendi.'
    editingId.value = null
    await load()
  } catch (e) {
    error.value = apiError(e)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Kullanıcı Yönetimi</h1>
      <p class="text-slate-500">{{ auth.user?.department ?? '' }} ekibinizdeki kullanıcılar.</p>
    </div>

    <div v-if="message" class="alert-success">{{ message }}</div>
    <div v-if="error" class="alert-error">{{ error }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th>E-posta</th>
            <th>Rol</th>
            <th>İzin bakiyesi</th>
            <th class="w-56">İşlem</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="4" class="text-center text-slate-500">Yükleniyor…</td></tr>
          <tr v-for="user in users" :key="user.id">
            <td>{{ user.email }}</td>
            <td>{{ user.isManager ? 'Yönetici' : 'Çalışan' }}</td>
            <td>
              <input v-if="editingId === user.id" v-model.number="editBalance" type="number" min="0" max="60" class="input w-24">
              <span v-else>{{ user.leaveBalance }} gün</span>
            </td>
            <td>
              <div v-if="editingId === user.id" class="flex gap-2">
                <button class="btn-primary" @click="saveBalance(user.id)">Kaydet</button>
                <button class="btn-ghost" @click="editingId = null">Vazgeç</button>
              </div>
              <button v-else-if="!user.isManager" class="btn-ghost" @click="startEdit(user)">Bakiyeyi düzenle</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <form class="card space-y-4 max-w-xl" @submit.prevent="createUser">
      <h2 class="font-semibold">Ekibe yeni çalışan ekle</h2>
      <div>
        <label class="label" for="new-email">E-posta</label>
        <input id="new-email" v-model="form.email" type="email" class="input" required>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="new-password">Şifre (en az 6 karakter)</label>
          <input id="new-password" v-model="form.password" type="password" class="input" minlength="6" required>
        </div>
        <div>
          <label class="label" for="new-balance">İzin bakiyesi (gün)</label>
          <input id="new-balance" v-model.number="form.leaveBalance" type="number" min="0" max="60" class="input">
        </div>
      </div>
      <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Ekleniyor…' : 'Çalışanı ekle' }}</button>
    </form>
  </div>
</template>
