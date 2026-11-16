/**
 * Symfony API'ye istek atmak için ortak fonksiyon.
 * Token varsa her isteğe "Authorization: Bearer <token>" başlığını ekler.
 * 401 gelirse (token süresi dolmuş vb.) kullanıcıyı login sayfasına yönlendirir.
 */
export const useApi = () => {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  return $fetch.create({
    baseURL: config.public.apiBase as string,
    onRequest({ options }) {
      if (auth.token) {
        const headers = new Headers(options.headers as HeadersInit | undefined)
        headers.set('Authorization', `Bearer ${auth.token}`)
        options.headers = headers
      }
    },
    onResponseError({ response }) {
      if (response.status === 401) {
        auth.clear()
        navigateTo('/login')
      }
    },
  })
}

/** API hata cevabından kullanıcıya gösterilecek mesajı çıkarır. */
export const apiError = (e: any): string => {
  return e?.data?.error || e?.data?.message || 'Sunucuya ulaşılamadı. Backend çalışıyor mu?'
}
