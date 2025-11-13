# ☕ SIM Coffee Shop - Sistem Informasi Manajemen Coffee Shop

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Sistem Informasi Manajemen (SIM) untuk Coffee Shop yang dibangun dengan **PHP Native**, **MySQL**, dan **Bootstrap 5**. Aplikasi ini dirancang untuk membantu mengelola operasional coffee shop secara efisien, mulai dari transaksi penjualan hingga manajemen stok dan laporan.

## ✨ Fitur Utama

### 🔐 Autentikasi & Keamanan
- ✅ Login/Logout dengan session management
- ✅ Role-based access control (Admin & Kasir)
- ✅ Login attempt limit (maksimal 5 percobaan)
- ✅ Account lockout mechanism (15 menit)
- ✅ Password hashing dengan bcrypt
- ✅ CSRF protection

### 📊 Dashboard
- ✅ Statistik real-time (penjualan hari ini, produk terjual, total pendapatan)
- ✅ Grafik interaktif dengan Chart.js
- ✅ Aktivitas terbaru
- ✅ Notifikasi stok menipis

### 💰 Point of Sale (POS)
- ✅ Transaksi cepat dan mudah
- ✅ Tampilan produk dengan gambar
- ✅ Validasi stok real-time
- ✅ Multiple payment methods (Cash, E-Wallet, Transfer)
- ✅ Cetak struk otomatis
- ✅ Kalkulasi otomatis (subtotal, diskon, total)

### 📦 Manajemen Produk
- ✅ CRUD lengkap untuk produk
- ✅ Upload dan manajemen gambar produk
- ✅ Kategori produk
- ✅ Manajemen supplier
- ✅ Detail produk dengan informasi lengkap
- ✅ Status produk (Aktif/Nonaktif)

### 📋 Manajemen Kategori
- ✅ CRUD kategori produk
- ✅ Validasi form yang robust
- ✅ Notifikasi real-time

### 👥 Manajemen User
- ✅ CRUD user (Admin & Kasir)
- ✅ Profile management
- ✅ Update password
- ✅ Validasi email dan data

### 📊 Manajemen Stok
- ✅ Update stok produk
- ✅ Peringatan stok menipis
- ✅ History pergerakan stok
- ✅ Cetak laporan stok

### 📈 Laporan Penjualan
- ✅ Filter berdasarkan tanggal, produk, dan metode pembayaran
- ✅ Grafik penjualan harian (Line Chart)
- ✅ Distribusi metode pembayaran (Doughnut Chart)
- ✅ Top produk terlaris (Bar Chart)
- ✅ Export ke CSV
- ✅ Detail produk yang terjual
- ✅ Cetak laporan profesional

### 🏢 Manajemen Supplier
- ✅ CRUD supplier lengkap
- ✅ Integrasi Google Maps (Keyless API)
- ✅ Autocomplete alamat
- ✅ Tampilan peta interaktif
- ✅ Link ke Google Maps

### 👤 Profile Management
- ✅ Update profil user
- ✅ Ganti password
- ✅ Update informasi kontak

## 🛠️ Teknologi yang Digunakan

### Backend
- **PHP 7.4+** - Native PHP (tanpa framework)
- **PDO** - Database abstraction layer
- **MySQL 5.7+** - Database management system
- **Session Management** - User authentication

### Frontend
- **HTML5** - Markup language
- **CSS3** - Styling dengan custom coffee shop theme
- **Bootstrap 5.3** - Responsive framework
- **JavaScript (ES6+)** - Interaktivitas
- **Font Awesome 6** - Icons
- **Chart.js 4.4** - Data visualization
- **Google Maps API (Keyless)** - Maps integration

### Security
- **Password Hashing** - bcrypt
- **CSRF Protection** - Token-based
- **Input Validation** - Client & server-side
- **SQL Injection Prevention** - Prepared statements
- **XSS Protection** - Output escaping

## 📋 Requirements

- **PHP**: 7.4 atau lebih tinggi
- **MySQL**: 5.7 atau lebih tinggi
- **Web Server**: Apache/Nginx (atau Laragon/XAMPP)
- **Extension PHP**: PDO, PDO_MySQL, GD (untuk image processing)

## 🚀 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/yourusername/sim-coffee-shop.git
cd sim-coffee-shop
```

### 2. Setup Database

Import file `database_clean.sql` ke MySQL:

**Via Command Line:**
```bash
mysql -u root -p < database_clean.sql
```

**Via phpMyAdmin:**
1. Buka phpMyAdmin
2. Buat database baru: `sim_kopi_2`
3. Import file `database_clean.sql`

### 3. Konfigurasi Database

Edit file `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sim_kopi_2');
define('DB_USER', 'root');        // Ganti dengan username MySQL Anda
define('DB_PASS', '');            // Ganti dengan password MySQL Anda
define('DB_CHARSET', 'utf8mb4');
```

### 4. Setup Upload Directory

Pastikan folder `uploads/products/` memiliki permission write:
```bash
chmod 755 uploads/products/
```

### 5. Akses Aplikasi

Buka browser dan akses:
```
http://localhost/Web Coffee/
```

atau jika menggunakan virtual host:
```
http://sim-coffee-shop.local/
```

## 👤 Login Default

Setelah import database, gunakan kredensial berikut:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | `admin@simkopi.com` | `password` |
| **Kasir** | `kasir@simkopi.com` | `password` |

> ⚠️ **PENTING**: Ganti password default setelah instalasi pertama!

## 📁 Struktur File

```
Web Coffee/
├── config.php              # Konfigurasi database & aplikasi
├── index.php               # Redirect ke dashboard
├── login.php               # Halaman login
├── logout.php              # Logout handler
├── dashboard.php           # Dashboard utama
├── pos.php                 # Point of Sale
├── products.php            # Manajemen produk
├── categories.php          # Manajemen kategori
├── users.php               # Manajemen user
├── suppliers.php           # Manajemen supplier
├── stock.php               # Manajemen stok
├── sales_report.php        # Laporan penjualan
├── profile.php             # Profile management
├── includes/
│   ├── header.php          # Header, navbar, CSS global
│   └── footer.php          # Footer, JavaScript global
├── uploads/
│   └── products/           # Folder gambar produk
├── database_clean.sql      # Database schema & seed data
└── README.md               # Dokumentasi
```

## 🔧 Database Schema

Database `sim_kopi_2` terdiri dari tabel-tabel berikut:

| Tabel | Deskripsi |
|-------|-----------|
| `users` | Data pengguna (admin/kasir) |
| `categories` | Kategori produk |
| `suppliers` | Data pemasok/supplier |
| `products` | Data produk dengan gambar |
| `sales` | Transaksi penjualan |
| `sale_items` | Detail item dalam transaksi |
| `stock_movements` | History pergerakan stok |

## 🎨 Customization

### Mengubah Tema Warna

Edit CSS di `includes/header.php`:
```css
:root {
    --coffee-primary: #6F4E37;      /* Warna utama (coklat kopi) */
    --coffee-secondary: #D2B48C;   /* Warna sekunder (krem) */
    --coffee-accent: #8B4513;       /* Warna aksen */
    --coffee-dark: #3E2723;        /* Warna gelap */
    --coffee-light: #F5E6D3;       /* Warna terang */
}
```

### Mengubah Nama Aplikasi

Edit di `config.php`:
```php
define('APP_NAME', 'Nama Aplikasi Anda');
define('APP_VERSION', '1.0.0');
```

## 🔒 Security Features

- ✅ **Prepared Statements** - Mencegah SQL Injection
- ✅ **Password Hashing** - Menggunakan bcrypt
- ✅ **CSRF Tokens** - Mencegah Cross-Site Request Forgery
- ✅ **Session Management** - Secure session handling
- ✅ **Input Validation** - Client & server-side validation
- ✅ **XSS Protection** - Output escaping
- ✅ **Login Attempt Limit** - Brute force protection

## 🐛 Troubleshooting

### Error: Database Connection Failed
- ✅ Pastikan MySQL service berjalan
- ✅ Cek konfigurasi di `config.php`
- ✅ Pastikan database `sim_kopi_2` sudah dibuat
- ✅ Verifikasi username dan password MySQL

### Error: Login Gagal
- ✅ Gunakan email dan password default
- ✅ Cek data user di tabel `users`
- ✅ Pastikan session tidak expired
- ✅ Clear browser cache dan cookies

### Error: PDO Extension Not Found
- ✅ Pastikan extension PDO dan PDO_MySQL aktif
- ✅ Edit `php.ini` dan uncomment: `extension=pdo_mysql`
- ✅ Restart web server

### Error: Upload Gambar Gagal
- ✅ Pastikan folder `uploads/products/` ada
- ✅ Set permission folder ke 755 atau 777
- ✅ Cek `upload_max_filesize` di `php.ini`
- ✅ Pastikan extension GD aktif

### Error: Google Maps Tidak Muncul
- ✅ Aplikasi menggunakan Keyless Google Maps API
- ✅ Pastikan koneksi internet aktif
- ✅ Cek console browser untuk error
- ✅ Fitur peta akan otomatis fallback ke iframe jika API gagal

## 📝 Changelog

### Version 1.0.0
- ✅ Initial release
- ✅ CRUD untuk semua modul
- ✅ POS dengan validasi real-time
- ✅ Laporan penjualan dengan grafik
- ✅ Manajemen supplier dengan Google Maps
- ✅ Profile management
- ✅ Login attempt limit
- ✅ UI/UX modern dengan tema coffee shop

## 🤝 Contributing

Kontribusi sangat diterima! Silakan:

1. Fork repository ini
2. Buat branch baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buka Pull Request

## 📄 License

Project ini menggunakan lisensi **MIT License**. Lihat file `LICENSE` untuk detail lebih lanjut.

## 👨‍💻 Author

Dibuat dengan ❤️ untuk memudahkan manajemen coffee shop.

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/) - CSS Framework
- [Font Awesome](https://fontawesome.com/) - Icons
- [Chart.js](https://www.chartjs.org/) - Charts library
- [Keyless Google Maps API](https://github.com/somanchiu/Keyless-Google-Maps-API) - Maps integration

## 📞 Support

Jika ada pertanyaan atau masalah, silakan:
- Buat [Issue](https://github.com/yourusername/sim-coffee-shop/issues) di GitHub
- Atau hubungi melalui email

---

**Selamat menggunakan SIM Coffee Shop! ☕**

*Made with ☕ and ❤️*
