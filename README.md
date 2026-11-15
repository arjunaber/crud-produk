## Daftar Materi & Insight Baru

1. **Persiapan Awal:** Clone repository dari GitHub & setup environment Laravel + MySQL.
2. **To-Do 1:** Upgrade Primary Key ke **UUID**, Auto-Generate **SKU** (_Stock Keeping Unit_), & **Soft Deletes**.
3. **To-Do 2:** Upgrade tabel statis ke **DataTables** & notifikasi ke **SweetAlert2**.
4. **To-Do 3:** Menghidupkan tombol **Edit** (_Resource Route_, _Route Model Binding_, & `@method('PUT')`).
5. **To-Do 4:** Menghidupkan tombol **Hapus** (`@method('DELETE')` & konfirmasi interaktif **SweetAlert2**).

##  Persiapan Awal: Clone & Setup Project dari GitHub
Ikuti langkah-langkah berikut secara berurutan agar aplikasi awal (*As-Is*) berjalan di laptopmu sebelum sesi *live coding* dimulai:

### 1. Clone Repository & Masuk ke Folder Project
Buka terminal (PowerShell / Git Bash / Terminal VS Code) di folder tempat kamu biasa menyimpan proyek (misalnya `Documents` atau `htdocs`), lalu jalankan:

```bash
git clone https://github.com/arjunaber/crud-produk.git
cd crud-produk
```

*(Opsional)* Buka folder proyek di VS Code langsung dari terminal:
```bash
code .
```

### 2. Install Dependency Laravel (`vendor`)
Folder `vendor/` tidak ikut diunggah ke GitHub (diabaikan oleh `.gitignore`), sehingga kita wajib mengunduhnya lewat Composer:

```bash
composer install
```

### 3. Buat File `.env` & Generate Application Key
File `.env` berisi konfigurasi rahasia lokal sehingga tidak tersedia di GitHub. Salin dari file contoh `.env.example`, lalu buat *encryption key* Laravel:

```bash
cp .env.example .env
php artisan key:generate
```
*(Catatan: Jika menggunakan Windows CMD biasa dan perintah `cp` tidak dikenali, gunakan `copy .env.example .env`).*

### 4. Konfigurasi Database MySQL
1. Pastikan **Apache & MySQL** di **XAMPP / Laragon** sudah dalam kondisi **Start (Aktif)**.
2. Buka file `.env` di VS Code, cari bagian `DB_CONNECTION`, lalu ubah dari `sqlite` menjadi konfigurasi `mysql` berikut (hapus tanda `#` jika masih dikomentari):

Pastikan konfigurasi database di file `.env` sudah mengarah ke MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crud_produk
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan migrasi awal dan server lokal:

```bash
php artisan migrate --seed
php artisan serve
```

---

## To-Do 1: Upgrade ID ke UUID, Auto-Generate SKU, & Soft Deletes

### Insight Industri

- **UUID (`char(36)`):** Menggantikan _auto-increment integer_ (`1, 2, 3`) untuk mencegah celah keamanan **IDOR** (_Insecure Direct Object Reference_), di mana pengguna dapat menebak ID data lain lewat URL.
- **Auto-Generate SKU:** Menggunakan _Model Event_ (`creating`) agar kode produk dibuat otomatis di level Model (_Skinny Controller, Fat Model_).
- **Soft Deletes:** Data yang dihapus tidak langsung hilang permanen dari MySQL, melainkan ditandai dengan _timestamp_ pada kolom `deleted_at` untuk kebutuhan audit.

### Langkah Coding

#### 1. Ubah struktur tabel di `database/migrations/xxxx_xx_xx_create_products_table.php`

Ganti `$table->id()` dengan `uuid`, tambahkan kolom `sku`, dan aktifkan `softDeletes()`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();       // Primary Key menggunakan UUID (36 karakter)
            $table->string('sku')->unique();     // Kode produk unik (otomatis diisi Model)
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->softDeletes();               // Menambah kolom deleted_at
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

#### 2. Pasang Trait & Auto-Generate SKU di `app/Models/Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['sku', 'name', 'price', 'stock'];

    // Otomatis mengisi kode SKU sesaat sebelum data baru disimpan ke MySQL
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . date('Y') . '-' . Str::upper(Str::random(5));
            }
        });
    }
}
```

#### 3. Sesuaikan kolom tabel di `resources/views/products/index.blade.php`

Ubah header tabel (`<thead>`) dan isi baris (`<tbody>`) untuk menampilkan **Kode SKU** dan **UUID**:

```html
<thead class="table-dark">
    <tr>
        <th>Kode SKU</th>
        <th>UUID</th>
        <th>Nama Produk</th>
        <th>Harga</th>
        <th>Stok</th>
        <th class="text-center" style="width: 150px;">Aksi</th>
    </tr>
</thead>
<tbody>
    @foreach ($products as $product)
    <tr>
        <td><span class="badge bg-secondary">{{ $product->sku }}</span></td>
        <td><code>{{ $product->id }}</code></td>
        <td class="fw-semibold">{{ $product->name }}</td>
        <td>Rp {{ number_format($product->price, 0, ',', '.') }}</td>
        <td>{{ $product->stock }} pcs</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-warning">Edit</button>
            <button type="button" class="btn btn-sm btn-danger">Hapus</button>
        </td>
    </tr>
    @endforeach
</tbody>
```

#### 4. Reset database dan isi ulang data dummy via Terminal

```bash
php artisan migrate:fresh --seed
```

> _Refresh browser—20 data produk kini otomatis memiliki UUID dan kode SKU._

---

## To-Do 2: Upgrade Tabel Polos ke DataTables & Alert ke SweetAlert2

### Insight Industri

- **DataTables:** Memberikan fitur _live search_, _sorting_ kolom, dan _pagination_ secara instan di sisi klien tanpa perlu menulis _query_ pencarian manual.
- **SweetAlert2:** Menggantikan kotak dialog bawaan browser/alert statis menjadi _popup_ modern yang lebih responsif.

### Langkah Coding (`resources/views/products/index.blade.php`)

#### 1. Tambahkan CSS DataTables di dalam tag `<head>`

```html
<link
    href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css"
    rel="stylesheet"
/>
```

#### 2. Hapus blok alert Bootstrap lama

```html
<!-- Hapus bagian ini -->
@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
```

#### 3. Tambahkan CDN jQuery, DataTables, dan SweetAlert2 sebelum penutup `</body>`

```html
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        $('#productTable').DataTable();
    });

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: @json(session('success')),
            timer: 2500,
            showConfirmButton: false
        });
    @endif
</script>
```

---

## To-Do 3: Hidupkan Tombol EDIT (Fitur Update + `@method('PUT')`)

### Insight Industri

- **Resource Routing & Implicit Route Model Binding:** Laravel otomatis mencocokkan parameter UUID pada URL (`/products/{product}/edit`) dengan data di MySQL tanpa perlu menulis `Product::findOrFail($id)`.
- **HTTP Method Spoofing:** Tag `<form>` HTML standar hanya mendukung metode `GET` dan `POST`, sehingga Laravel menggunakan `@method('PUT')` untuk memetakan permintaan pembaruan data (_Update_).

### Langkah Coding

#### 1. Ubah `routes/web.php` menggunakan `Route::resource`

```php
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('products.index'));

Route::resource('products', ProductController::class)->except(['create', 'show']);
```

#### 2. Tambahkan method `edit` dan `update` di `app/Http/Controllers/ProductController.php`

```php
public function edit(Product $product)
{
    return view('products.edit', compact('product'));
}

public function update(Request $request, Product $product)
{
    $validated = $request->validate([
        'name'  => 'required|string|min:3|max:255',
        'price' => 'required|integer|min:0',
        'stock' => 'required|integer|min:0',
    ]);

    $product->update($validated);

    return redirect()->route('products.index')
        ->with('success', "Produk {$product->sku} berhasil diperbarui!");
}
```

#### 3. Buat file baru `resources/views/products/edit.blade.php`

```html
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Edit Produk - {{ $product->sku }}</title>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-warning fw-bold">
                            Edit Produk: {{ $product->sku }}
                        </div>
                        <div class="card-body">
                            <form
                                action="{{ route('products.update', $product->id) }}"
                                method="POST"
                            >
                                @csrf @method('PUT')

                                <div class="mb-3">
                                    <label class="form-label text-muted small"
                                        >UUID Produk</label
                                    >
                                    <input
                                        type="text"
                                        class="form-control bg-light"
                                        value="{{ $product->id }}"
                                        readonly
                                    />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label"
                                        >Nama Produk</label
                                    >
                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        value="{{ old('name', $product->name) }}"
                                        required
                                    />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Harga (Rp)</label>
                                    <input
                                        type="number"
                                        name="price"
                                        class="form-control"
                                        min="0"
                                        value="{{ old('price', $product->price) }}"
                                        required
                                    />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Stok</label>
                                    <input
                                        type="number"
                                        name="stock"
                                        class="form-control"
                                        min="0"
                                        value="{{ old('stock', $product->stock) }}"
                                        required
                                    />
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a
                                        href="{{ route('products.index') }}"
                                        class="btn btn-secondary"
                                        >Kembali</a
                                    >
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
```

#### 4. Aktifkan link tombol Edit di `resources/views/products/index.blade.php`

```html
<a
    href="{{ route('products.edit', $product->id) }}"
    class="btn btn-sm btn-warning"
    >Edit</a
>
```

---

## To-Do 4: Hidupkan Tombol HAPUS (Fitur Delete + SweetAlert2 Konfirmasi)

### Insight Industri

- **Mencegah Accidental Delete:** Menggunakan tombol `type="button"` agar form tidak langsung terkirim sebelum pengguna mengonfirmasi lewat modal SweetAlert2.
- **Event Delegation (`$(document).on`):** Wajib digunakan saat menggabungkan tombol aksi dengan DataTables agar tombol di halaman ke-2, ke-3, dan seterusnya tetap merespons klik.

### Langkah

#### 1. Tambahkan method `destroy` di `app/Http/Controllers/ProductController.php`

```php
public function destroy(Product $product)
{
    $sku = $product->sku;
    $product->delete();

    return redirect()->route('products.index')
        ->with('success', "Produk {$sku} berhasil dihapus!");
}
```

#### 2. Bungkus tombol Hapus dengan form `DELETE` di `resources/views/products/index.blade.php`

```html
<form
    action="{{ route('products.destroy', $product->id) }}"
    method="POST"
    class="d-inline delete-form"
>
    @csrf @method('DELETE')
    <button
        type="button"
        class="btn btn-sm btn-danger btn-delete"
        data-name="{{ $product->name }}"
        data-sku="{{ $product->sku }}"
    >
        Hapus
    </button>
</form>
```

#### 3. Tambahkan script konfirmasi SweetAlert2 di dalam tag `<script>` pada `index.blade.php`

```javascript
$(document).on("click", ".btn-delete", function () {
    const form = $(this).closest(".delete-form");
    const name = $(this).data("name");
    const sku = $(this).data("sku");

    Swal.fire({
        title: "Hapus Produk?",
        html: `Produk <b>${name}</b> (${sku}) akan dihapus dari katalog aktif.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal",
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
});
```

---

## Pengecekan Akhir di MySQL (phpMyAdmin / DBeaver)

Setelah menghapus salah satu produk melalui aplikasi:

1. Buka tabel `products` di database `crud_produk`.
2. Perhatikan bahwa kolom `id` bertipe `char(36)` berisi string UUID acak.
3. Baris produk yang baru saja dihapus **masih ada di dalam tabel**, namun kolom `deleted_at` kini terisi stempel waktu penghapusan sehingga tidak lagi ditampilkan di halaman utama.
