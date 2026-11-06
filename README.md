# Bank Sampah Digital API

REST API Laravel untuk pencatatan setoran sampah, saldo poin, dan penukaran hadiah. Seluruh endpoint API memakai prefix `/api/v1`.

## Menjalankan proyek

1. Pastikan PHP 8.2+, Composer, dan ekstensi PDO SQLite aktif.
2. Jika `.env` belum ada, salin `.env.example`, lalu buat application key:

   ```powershell
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   composer install
   php artisan key:generate
   ```

3. Migrasikan dan isi katalog awal:

   ```powershell
   php artisan migrate --seed
   php artisan serve
   ```

   API berjalan di `http://127.0.0.1:8000/api/v1`; klien demo di `/client`; dokumentasi Swagger UI di `/docs` dan OpenAPI di `/openapi.yaml`.

4. Daftarkan nasabah melalui `POST /api/v1/auth/register`. Pendaftaran publik selalu membuat peran `nasabah`. Untuk menyiapkan petugas/admin lokal, ubah peran melalui Tinker setelah mendaftar:

   ```powershell
   php artisan tinker
   ```

   ```php
   App\Models\User::where('email', 'petugas@example.test')->update(['role' => 'petugas']);
   App\Models\User::where('email', 'admin@example.test')->update(['role' => 'admin']);
   ```

   Gunakan alamat email yang sudah didaftarkan. Peran yang tersedia: `nasabah`, `petugas`, `admin`.

## Autentikasi dan format respons

Login menghasilkan token Bearer acak yang hanya disimpan dalam bentuk hash di database dan kedaluwarsa setelah 12 jam. Kirim token pada endpoint privat:

```http
Authorization: Bearer <token>
Accept: application/json
```

Respons sukses menggunakan `{ "data": ..., "meta": ... }`. Daftar menggunakan pagination dan `meta` berisi `current_page`, `last_page`, `per_page`, dan `total`. Kesalahan menggunakan `{ "error": { "code": "...", "message": "...", "details": ... } }`; kesalahan validasi mengembalikan HTTP 422 dan `details` per field.

## Endpoint

| Metode | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/auth/register` | Publik | Daftar nasabah; wajib `name`, `email`, `password`, `password_confirmation`; `phone` dan `address` opsional. |
| POST | `/auth/login` | Publik | Login dengan `email` dan `password`; dibatasi 5 percobaan per menit. |
| GET | `/auth/me` | Semua peran | Profil pemilik token. |
| POST | `/auth/logout` | Semua peran | Cabut token saat ini. |
| GET | `/jenis-sampah` | Publik | Daftar aktif; filter `search`, `sort`, `direction`, `per_page`. |
| GET | `/jenis-sampah/{id}` | Publik | Detail jenis sampah aktif. |
| POST | `/jenis-sampah` | Admin | Tambah jenis; `name`, `price_per_kg`, opsional `description`, `is_active`. |
| PUT/PATCH | `/jenis-sampah/{id}` | Admin | Ubah nama, deskripsi, harga, atau status aktif. |
| DELETE | `/jenis-sampah/{id}` | Admin | Nonaktifkan jenis (tidak menghapus riwayat setoran). |
| GET | `/hadiah` | Publik | Daftar hadiah aktif; filter pencarian, urutan, dan pagination. |
| GET | `/hadiah/{id}` | Publik | Detail hadiah aktif. |
| POST | `/hadiah` | Admin | Tambah hadiah; `name`, `points_required`, `stock`. |
| PUT/PATCH | `/hadiah/{id}` | Admin | Ubah data atau stok hadiah. |
| DELETE | `/hadiah/{id}` | Admin | Nonaktifkan hadiah tanpa menghapus histori. |
| GET | `/titik-setor` | Publik | Daftar titik setor beserta jenis sampah yang diterima; filter pencarian dan pagination. |
| GET | `/titik-setor/{id}` | Publik | Detail titik setor dan jenis sampah yang diterima. |
| POST | `/titik-setor` | Admin | Buat titik setor dengan `name`, `address`, serta `jenis_sampah_ids` (array ID jenis sampah aktif). |
| PUT/PATCH | `/titik-setor/{id}` | Admin | Ubah titik setor dan/atau sinkronkan jenis sampah yang diterima. |
| DELETE | `/titik-setor/{id}` | Admin | Nonaktifkan titik setor. |
| GET | `/nasabah` | Admin | Daftar; filter `search`, `role`, pagination. |
| GET | `/nasabah/{id}` | Pemilik, petugas, admin | Lihat profil nasabah. |
| PATCH | `/nasabah/{id}` | Pemilik, petugas, admin | Ubah nama, telepon, alamat; role/email tidak dapat diubah lewat endpoint ini. |
| PATCH | `/nasabah/{id}/role` | Admin | Atur `role` ke `nasabah`, `petugas`, atau `admin`. |
| GET | `/nasabah/{id}/saldo` | Pemilik, petugas, admin | Hitung saldo dari ledger transaksi. |
| GET | `/nasabah/{id}/transaksi` | Pemilik, petugas, admin | Riwayat; filter `type`, `from`, `to`, `direction`, `per_page`. |
| GET | `/setoran` | Semua peran | Daftar setoran; nasabah hanya melihat miliknya. Filter `nasabah_id`, `jenis_sampah_id`, `from`, `to`. |
| GET | `/setoran/{id}` | Semua peran | Detail setoran; nasabah hanya dapat melihat miliknya. |
| POST | `/setoran` | Petugas, admin | Catat setoran dengan `nasabah_id`, `jenis_sampah_id`, `berat_kg`, opsional `catatan`. |
| GET | `/transaksi` | Semua peran | Daftar ledger; filter `nasabah_id`, `type`, `from`, `to`. |
| GET | `/transaksi/{id}` | Semua peran | Detail ledger; nasabah hanya dapat melihat miliknya. |
| GET | `/penukaran` | Semua peran | Daftar penukaran; nasabah hanya melihat miliknya. |
| GET | `/penukaran/{id}` | Semua peran | Detail penukaran; nasabah hanya dapat melihat miliknya. |
| POST | `/penukaran` | Nasabah | Tukar `hadiah_id` dengan `jumlah`; saldo dan stok dicek secara atomik. |
| GET | `/laporan/bulanan` | Admin | Ringkasan setoran menurut jenis; query `tahun` dan `bulan` opsional. |

Tambahkan prefix `/api/v1` pada semua path di tabel. Daftar endpoint, skema, autentikasi, dan respons juga tersedia pada dokumen OpenAPI.
Untuk riwayat transaksi nasabah, parameter tanggal `dari` dan `sampai` juga diterima sebagai alias `from` dan `to`.

### Contoh request dan respons

Login:

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{"email":"ayu@example.test","password":"password-yang-dipilih"}
```

```json
{
  "data": {
    "user": {"id": 1, "name": "Ayu", "email": "ayu@example.test", "role": "nasabah"},
    "token": "<token-hanya-ditampilkan-saat-dibuat>",
    "token_type": "Bearer",
    "expires_at": "2026-11-06T16:00:00.000000Z"
  },
  "meta": {}
}
```

Mencatat setoran (token petugas/admin):

```http
POST /api/v1/setoran
Authorization: Bearer <token>
Content-Type: application/json

{"nasabah_id":1,"jenis_sampah_id":1,"berat_kg":1.25,"catatan":"Botol bersih"}
```

Nilai poin menggunakan `berat_kg × harga_per_kg`, dibulatkan ke rupiah terdekat. Harga disalin ke catatan setoran sehingga perubahan harga berikutnya tidak mengubah ledger lama. Saldo adalah jumlah seluruh transaksi: setoran bernilai positif dan penukaran negatif.

Penukaran gagal dengan HTTP 422 bila poin atau stok tidak mencukupi; transaksi, saldo, dan stok diperbarui dalam satu database transaction. Entri setoran, penukaran, dan ledger bersifat append-only (tidak ada endpoint ubah/hapus).

## Skema data

- **users**: nasabah/petugas/admin.
- **waste_types**: jenis sampah dan harga per kg.
- **collection_points**: titik setor yang menerima jenis sampah.
- **collection_point_waste_type**: pivot many-to-many titik setor ↔ jenis sampah.
- **deposits**: berat, harga saat setoran, poin, nasabah, petugas.
- **rewards**: hadiah, harga poin, dan stok.
- **redemptions**: snapshot harga poin dan jumlah hadiah yang ditukar.
- **transactions**: ledger bertanda positif/negatif dan saldo setelah transaksi.
- **api_tokens**: hash token, waktu kedaluwarsa, dan aktivitas terakhir.

Seed mengisi lima jenis sampah, tiga hadiah, dan satu titik setor contoh dengan beberapa jenis sampah, tetapi tidak membuat akun dengan kata sandi bawaan. Relasi `users → deposits` adalah one-to-many; relasi titik setor ↔ jenis sampah adalah many-to-many.

## Pengujian

```powershell
php artisan test
```

Tes fitur mencakup validasi, pendaftaran, autentikasi, pembatasan peran, pembatasan akses data nasabah, dan konsistensi saldo/stok pada setoran serta penukaran.

Impor [koleksi Postman](./postman/Bank-Sampah-Digital.postman_collection.json), isi variabel akun dan `member_id`, lalu jalankan request berurutan untuk skenario sukses dan gagal.
