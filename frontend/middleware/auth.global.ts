/**
 * Her sayfa geçişinde çalışır.
 * Kural: Yetkisiz kullanıcılar ilgili sayfalara erişememelidir.
 *  - Giriş yapmamış kullanıcı -> /login
 *  - Yönetici sayfasına (meta.requiresManager) çalışan girerse -> /
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  auth.init()

  if (to.path === '/login') {
    if (auth.isLoggedIn) return navigateTo('/')
    return
  }

  if (!auth.isLoggedIn) {
    return navigateTo('/login')
  }

  if (!auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.clear()
      return navigateTo('/login')
    }
  }

  if (to.meta.requiresManager && !auth.isManager) {
    return navigateTo('/')
  }
})
