# CRUD Lengkap Admin SupplyGuard

Update ini melengkapi fungsi administrator untuk:

- mengubah nama, email, peran, dan kata sandi pengguna;
- mengubah artikel internal;
- mengubah kata positif;
- mengubah kata negatif;
- tetap mempertahankan tambah dan hapus yang sudah ada.

Akun administrator yang sedang digunakan tidak dapat menurunkan perannya
sendiri untuk mencegah kehilangan akses ke halaman admin.

## Menjalankan

```powershell
php -l app\Http\Controllers\AdminController.php
php -l routes\web.php
php artisan optimize:clear
php artisan route:list --path=admin
php artisan test
```
