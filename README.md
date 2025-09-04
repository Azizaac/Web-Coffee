# SIM Coffee Shop - PHP Native

Sistem Informasi Manajemen Coffee Shop yang dibangun dengan PHP Native, MySQL, dan Bootstrap 5.

## 🚀 Quick Start

### 1. Setup Database
```sql
-- Import database_clean.sql ke MySQL
mysql -u username -p < database_clean.sql
```

### 2. Konfigurasi Database
Edit file `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sim_kopi_2');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Akses Aplikasi
Buka browser: `http://localhost/Web Coffee/`

## 👤 Login Default

- **Admin**: `admin@simkopi.com` / `password`
- **Kasir**: `kasir@simkopi.com` / `password`

## 📋 Fitur Utama

- ✅ **Login/Logout** dengan session management
- ✅ **Dashboard** dengan statistik real-time
- ✅ **Point of Sale (POS)** untuk transaksi
- ✅ **Product Management** (CRUD)
- ✅ **Category Management** (CRUD)
- ✅ **User Management** (CRUD)
- ✅ **Stock Management** dengan peringatan stok rendah
- ✅ **Sales Report** dengan filter dan export CSV
- ✅ **Responsive Design** dengan Bootstrap 5

## 🛠️ Teknologi

- **Backend**: PHP 7.4+ Native dengan PDO
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Icons**: Font Awesome 6
- **Security**: Password hashing dengan bcrypt

## 📁 Struktur File

```
Web Coffee/
├── config.php              # Konfigurasi database
├── login.php               # Halaman login
├── dashboard.php           # Dashboard utama
├── pos.php                 # Point of Sale
├── products.php            # Manajemen produk
├── categories.php          # Manajemen kategori
├── users.php               # Manajemen user
├── stock.php               # Manajemen stok
├── sales_report.php        # Laporan penjualan
├── includes/
│   ├── header.php          # Header dan navbar
│   └── footer.php          # Footer dan scripts
└── database_clean.sql      # Database schema
```

## 🔧 Database Schema

Database `sim_kopi_2` berisi:
- **users**: Data pengguna (admin/kasir)
- **categories**: Kategori produk
- **products**: Data produk
- **sales**: Transaksi penjualan
- **sale_items**: Detail item penjualan
- **stock_movements**: Pergerakan stok
- **suppliers**: Data pemasok

## 🎨 Customization

Edit CSS di `includes/header.php`:
```css
:root {
    --coffee-primary: #8B4513;
    --coffee-secondary: #D2691E;
    --coffee-light: #F5DEB3;
    --coffee-dark: #654321;
}
```

## 🐛 Troubleshooting

### Error Database Connection
- Pastikan MySQL service berjalan
- Cek konfigurasi di `config.php`
- Pastikan database `sim_kopi_2` sudah dibuat

### Error Login
- Gunakan email dan password default
- Cek data user di tabel `users`

### Error PDO
- Pastikan extension PDO dan PDO_MySQL aktif
- Cek versi PHP minimal 7.4

---

**Ready to use! ☕**
