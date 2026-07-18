# Keamanan Repository Publik

Repository SupplyGuard bersifat publik. Sebelum push, pastikan:

- `.env` tidak dilacak Git;
- API key pada `.env.example` kosong;
- export SQL hanya berisi akun demo;
- tidak ada email pribadi pada `database/supplyguard.sql`;
- hasil `git diff --check` bersih.

Jalankan:

```powershell
powershell -ExecutionPolicy Bypass -File tools\check-public-repo.ps1
```

Email yang diizinkan pada SQL publik:

```text
admin@supplyguard.test
user@supplyguard.test
```

Catatan: apabila data pribadi pernah masuk pada commit GitHub sebelumnya,
mengganti file pada commit terbaru tidak menghapus versi lama dari riwayat Git.
Riwayat perlu dibersihkan secara terpisah apabila benar-benar ingin menghapusnya.
