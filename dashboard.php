<?php
$page_title = "Dashboard";
require_once 'config.php';
requireLogin();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
$stats['total_products'] = $result->fetch()['total'] ?? 0;

// Total categories
$result = $conn->query("SELECT COUNT(*) as total FROM categories WHERE status = 'active'");
$stats['total_categories'] = $result->fetch()['total'] ?? 0;

// Today's sales
$result = $conn->query("SELECT COUNT(*) as transactions, COALESCE(SUM(final_amount), 0) as revenue 
                       FROM sales 
                       WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$today_sales = $result->fetch();
$stats['today_transactions'] = $today_sales['transactions'] ?? 0;
$stats['today_revenue'] = $today_sales['revenue'] ?? 0;

// This month's sales
$result = $conn->query("SELECT COUNT(*) as transactions, COALESCE(SUM(final_amount), 0) as revenue 
                       FROM sales 
                       WHERE MONTH(created_at) = MONTH(CURDATE()) 
                       AND YEAR(created_at) = YEAR(CURDATE()) 
                       AND status = 'completed'");
$month_sales = $result->fetch();
$stats['month_transactions'] = $month_sales['transactions'] ?? 0;
$stats['month_revenue'] = $month_sales['revenue'] ?? 0;

// Low stock products
$result = $conn->query("SELECT COUNT(*) as total FROM products 
                        WHERE stock <= min_stock AND status = 'active'");
$stats['low_stock'] = $result->fetch()['total'] ?? 0;

// Recent sales (fix: pilih kolom spesifik & GROUP BY lengkap)
$recent_sales = $conn->query("SELECT s.id, s.invoice_number, s.customer_name, s.final_amount, s.created_at,
                              u.name as cashier_name, COUNT(si.id) as total_items
                              FROM sales s
                              LEFT JOIN users u ON s.user_id = u.id
                              LEFT JOIN sale_items si ON s.id = si.sale_id
                              WHERE s.status = 'completed'
                              GROUP BY s.id, s.invoice_number, s.customer_name, s.final_amount, s.created_at, u.name
                              ORDER BY s.created_at DESC
                              LIMIT 10");

// Top selling products (last 30 days) (fix: GROUP BY jelas)
$top_products = $conn->query("SELECT p.id, p.name, c.name as category_name,
                              SUM(si.quantity) as total_sold,
                              SUM(si.subtotal) as total_revenue
                              FROM sale_items si
                              JOIN products p ON si.product_id = p.id
                              JOIN categories c ON p.category_id = c.id
                              JOIN sales s ON si.sale_id = s.id
                              WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                              AND s.status = 'completed'
                              GROUP BY p.id, p.name, c.name
                              ORDER BY total_sold DESC
                              LIMIT 5");

// Sales chart data (last 7 days)
$chart_data = $conn->query("SELECT DATE(created_at) as sale_date,
                            COUNT(*) as transactions,
                            SUM(final_amount) as revenue
                            FROM sales
                            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            AND status = 'completed'
                            GROUP BY DATE(created_at)
                            ORDER BY sale_date");

include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                <p class="mb-0">Selamat datang kembali, <strong><?= htmlspecialchars($current_user['name']) ?></strong>! 👋</p>
            </div>
            <div class="text-end">
                <div class="badge bg-light text-dark px-3 py-2">
                    <i class="fas fa-calendar me-2"></i><?= date('l, F j, Y') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-box fa-3x mb-3"></i>
                    <h4><?= number_format($stats['total_products']) ?></h4>
                    <p class="mb-0">Total Produk</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-tags fa-3x mb-3"></i>
                    <h4><?= number_format($stats['total_categories']) ?></h4>
                    <p class="mb-0">Kategori</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-receipt fa-3x mb-3"></i>
                    <h4><?= number_format($stats['today_transactions']) ?></h4>
                    <p class="mb-0">Transaksi Hari Ini</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                    <h4><?= formatCurrency($stats['today_revenue']) ?></h4>
                    <p class="mb-0">Pendapatan Hari Ini</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats for Admin -->
    <?php if ($current_user['role'] == 'admin'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                    <h4><?= number_format($stats['month_transactions']) ?></h4>
                    <p class="mb-0">Transaksi Bulan Ini</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <h4><?= formatCurrency($stats['month_revenue']) ?></h4>
                    <p class="mb-0">Pendapatan Bulan Ini</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-stats">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 <?= $stats['low_stock'] > 0 ? 'text-danger' : 'text-success' ?>"></i>
                    <h4 class="<?= $stats['low_stock'] > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= number_format($stats['low_stock']) ?>
                    </h4>
                    <p class="mb-0">Stok Menipis</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Recent Sales -->
        <div class="col-md-8">
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-clock me-2"></i>Penjualan Terbaru
                    </h5>
                    <a href="sales_report.php" class="btn btn-outline-coffee btn-sm">
                        <i class="fas fa-eye me-1"></i>Lihat Semua
                    </a>
                </div>
                
                <?php if ($recent_sales->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Pelanggan</th>
                                    <th>Kasir</th>
                                    <th>Item</th>
                                    <th>Total</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sale = $recent_sales->fetch()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sale['invoice_number']) ?></strong></td>
                                    <td><?= !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '-' ?></td>
                                    <td><?= htmlspecialchars($sale['cashier_name'] ?? '-') ?></td>
                                    <td><span class="badge bg-info"><?= $sale['total_items'] ?> item</span></td>
                                    <td><strong class="text-success"><?= formatCurrency($sale['final_amount']) ?></strong></td>
                                    <td><small><?= formatDate($sale['created_at'], 'M j, H:i') ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h4>Tidak Ada Penjualan Terbaru</h4>
                        <p>Mulai melakukan penjualan untuk melihatnya di sini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Products & Quick Actions -->
        <div class="col-md-4">
            <!-- Top Products -->
            <div class="table-container mb-4">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-trophy me-2"></i>Produk Terlaris
                    </h5>
                    <small class="text-muted">30 hari</small>
                </div>
                
                <?php if ($top_products->rowCount() > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php $rank = 1; while ($product = $top_products->fetch()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <span class="badge bg-primary me-2"><?= $rank ?></span>
                                    <?= htmlspecialchars($product['name']) ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($product['category_name']) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= formatCurrency($product['total_revenue']) ?></div>
                                <small class="text-muted"><?= $product['total_sold'] ?> terjual</small>
                            </div>
                        </div>
                        <?php $rank++; endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h4>Tidak Ada Data Penjualan</h4>
                        <p>Data penjualan akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-bolt me-2"></i>Aksi Cepat
                    </h5>
                </div>
                
                <div class="d-grid gap-2">
                    <?php if (in_array($current_user['role'], ['admin', 'kasir'])): ?>
                    <a href="pos.php" class="btn btn-coffee">
                        <i class="fas fa-cash-register me-2"></i>Penjualan Baru
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($current_user['role'] == 'admin'): ?>
                    <a href="products.php" class="btn btn-outline-coffee"><i class="fas fa-box me-2"></i>Kelola Produk</a>
                    <a href="categories.php" class="btn btn-outline-coffee"><i class="fas fa-tags me-2"></i>Kelola Kategori</a>
                    <a href="suppliers.php" class="btn btn-outline-coffee"><i class="fas fa-truck me-2"></i>Kelola Supplier</a>
                    <a href="stock.php" class="btn btn-outline-coffee"><i class="fas fa-warehouse me-2"></i>Kelola Stok</a>
                    <a href="sales_report.php" class="btn btn-outline-coffee"><i class="fas fa-chart-line me-2"></i>Laporan Penjualan</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
