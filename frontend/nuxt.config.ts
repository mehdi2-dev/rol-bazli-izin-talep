// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  // SPA modu: token tarayıcıda (localStorage) tutulduğu için sunucu tarafı render kapalı
  ssr: false,
  devtools: { enabled: false },
  compatibilityDate: '2025-01-01',
  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      // Symfony API adresi. Değiştirmek için: NUXT_PUBLIC_API_BASE=http://... npm run dev
      apiBase: 'http://127.0.0.1:8000',
    },
  },
  app: {
    head: {
      title: 'İzin Talep Sistemi',
      htmlAttrs: { lang: 'tr' },
    },
  },
})
