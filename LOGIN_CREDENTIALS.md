# Login Credentials - Upload Center v12

## SuperUser Account (Admin Panel)
**Hanya bisa dibuat melalui seeding, TIDAK bisa registrasi**

| Name | Email | Password | Division | Role |
|------|-------|----------|----------|------|
| Super Administrator | superuser@test.com | password | SuperUser | super-admin |

**Akses SuperUser:**
- ✅ Melihat semua format dari semua divisi
- ✅ Melihat statistik upload semua divisi (chart 4 minggu terakhir)
- ✅ Upload data ke format manapun
- ✅ Kelola semua data
- ✅ Panel admin lengkap

---

## Regular Division Users

| Name | Email | Password | Division | Role |
|------|-------|----------|----------|------|
| Admin Marketing | marketing@test.com | password | Marketing | division-user |
| Admin Sales | sales@test.com | password | Sales | division-user |
| Admin IT | it@test.com | password | IT | division-user |
| Admin HR | hr@test.com | password | HR | division-user |

**Akses Regular User:**
- ✅ Melihat format divisi sendiri saja
- ✅ Upload data ke format divisi sendiri
- ✅ Kelola data divisi sendiri
- ❌ Tidak bisa melihat data divisi lain

---

## Cara Membuat SuperUser Baru

SuperUser **TIDAK BISA** dibuat melalui form registrasi. Hanya bisa dibuat via seeding:

```bash
php artisan db:seed --class=SuperUserSeeder
```

Atau jika ingin reset database dan buat semua user:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=TestUsersSeeder
php artisan db:seed --class=SuperUserSeeder
```

---

## Keamanan

1. **Registrasi Form**: Otomatis memfilter divisi SuperUser dari dropdown
2. **Validasi Backend**: Mencegah registrasi dengan divisi SuperUser
3. **SuperUser Creation**: Hanya melalui seeding/command line
4. **Role Assignment**: SuperUser otomatis mendapat role `super-admin`

---

## Testing

### Login sebagai SuperUser:
1. Buka http://127.0.0.1:8000/login
2. Email: `superuser@test.com`
3. Password: `password`
4. Dashboard akan menampilkan semua format + chart statistik

### Login sebagai Regular User:
1. Buka http://127.0.0.1:8000/login
2. Email: `marketing@test.com` (atau sales/it/hr)
3. Password: `password`
4. Dashboard hanya menampilkan format divisi sendiri

### Test Registrasi:
1. Buka http://127.0.0.1:8000/register
2. Dropdown divisi **TIDAK** akan menampilkan "SuperUser"
3. Hanya divisi regular (Marketing, Sales, IT, HR) yang muncul

---

## Database Structure

### upload_logs table:
- `id` - Primary key
- `user_id` - Foreign key ke users
- `division_id` - Foreign key ke divisions
- `mapping_index_id` - Foreign key ke mapping_indices
- `file_name` - Nama file yang diupload
- `rows_imported` - Jumlah baris yang berhasil diimport
- `status` - Status upload (success/failed)
- `error_message` - Pesan error jika gagal
- `created_at` - Timestamp upload
- `updated_at` - Timestamp update

Index: `(division_id, created_at)` untuk query statistik yang cepat
