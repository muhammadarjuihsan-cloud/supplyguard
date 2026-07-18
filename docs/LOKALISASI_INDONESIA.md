# Lokalisasi Bahasa Indonesia

Update ini menyediakan terjemahan Bahasa Indonesia untuk:

- pesan validasi formulir;
- autentikasi;
- pengaturan ulang kata sandi;
- pagination;
- nama atribut yang terlihat oleh pengguna.

## Mengaktifkan locale

Jalankan dari folder project:

```powershell
powershell -ExecutionPolicy Bypass -File tools\set-indonesian-locale.ps1
php artisan optimize:clear
php artisan test
```

Script memperbarui `.env` dan `.env.example` menjadi:

```env
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID
```

File `.env` tetap tidak boleh dimasukkan ke GitHub.
