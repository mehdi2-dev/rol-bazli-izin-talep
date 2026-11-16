import { defineStore } from 'pinia'

export interface CurrentUser {
  id: number
  email: string
  roles: string[]
  isManager: boolean
  department: string | null
  leaveBalance: number
}

const TOKEN_KEY = 'izin_token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null as string | null,
    user: null as CurrentUser | null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
    isManager: (state) => !!state.user?.isManager,
  },

  actions: {
    /** Sayfa yenilendiğinde token'ı localStorage'dan geri yükler. */
    init() {
      if (!this.token && import.meta.client) {
        this.token = localStorage.getItem(TOKEN_KEY)
      }
    },

    async login(email: string, password: string) {
      const config = useRuntimeConfig()
      const res = await $fetch<{ token: string }>('/api/login_check', {
        baseURL: config.public.apiBase as string,
        method: 'POST',
        body: { email, password },
      })
      this.token = res.token
      localStorage.setItem(TOKEN_KEY, res.token)
      await this.fetchMe()
    },

    async fetchMe() {
      const api = useApi()
      this.user = await api<CurrentUser>('/api/me')
    },

    clear() {
      this.token = null
      this.user = null
      if (import.meta.client) localStorage.removeItem(TOKEN_KEY)
    },

    logout() {
      this.clear()
      navigateTo('/login')
    },
  },
})
