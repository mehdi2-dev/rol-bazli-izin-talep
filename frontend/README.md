# İzin Talep Sistemi – Frontend (Nuxt)

## Çalıştırma

```
npm install
npm run dev
```

Uygulama: http://localhost:3000
Backend (Symfony) http://127.0.0.1:8000 adresinde çalışıyor olmalı.

## Test kullanıcıları (şifre: Test1234)

| E-posta | Rol | Ekip |
|---|---|---|
| yonetici@test.com | Yönetici | Yazılım |
| calisan@test.com | Çalışan | Yazılım |
| calisan2@test.com | Çalışan | Yazılım |
| ik.yonetici@test.com | Yönetici | İK |
| ik.calisan@test.com | Çalışan | İK |

## Ekranlar

- `/login` – Giriş
- `/` – Dashboard (bakiye, talep sayıları)
- `/leaves/new` – İzin talebi oluşturma
- `/leaves` – İzin taleplerim
- `/manager/approvals` – Yönetici onay ekranı (yalnızca yönetici)
- `/manager/users` – Kullanıcı yönetimi (yalnızca yönetici)
