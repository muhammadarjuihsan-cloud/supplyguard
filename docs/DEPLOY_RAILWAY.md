# Deployment SupplyGuard ke Railway

Patch ini menyiapkan SupplyGuard untuk Railway:

- mempercayai reverse proxy Railway;
- memakai zona waktu dari environment variable;
- membangun asset Vite saat deployment;
- menjalankan migration sebelum aplikasi aktif;
- memakai endpoint `/up` sebagai health check;
- menyediakan contoh environment production tanpa rahasia.

## Instalasi patch

Ekstrak isi ZIP ke folder utama project:

```text
C:\Users\ASUS\supplyguard
```

Pilih **Replace the files in the destination**.

## Pemeriksaan lokal

```powershell
cd C:\Users\ASUS\supplyguard
php -l bootstrap\app.php
php -l config\app.php
npm ci
npm run build
php artisan optimize:clear
php artisan test
```

## Commit

```powershell
git add bootstrap\app.php config\app.php .env.example railway.json docs\DEPLOY_RAILWAY.md
git diff --cached --check
git commit -m "chore: siapkan deployment SupplyGuard ke Railway"
git push origin main
```

Jangan memasukkan `.env`, API key, folder `vendor`, atau `node_modules` ke GitHub.
