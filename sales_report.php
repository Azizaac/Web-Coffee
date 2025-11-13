<?php
$page_title = "Laporan Penjualan";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$start_date = $_GET['start_date'] ?? '2025-01-01'; // Start from January 2025
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$cashier_filter = $_GET['cashier'] ?? '';
$export = $_GET['export'] ?? '';

// Handle AJAX request for sale details
if (isset($_GET['action']) && $_GET['action'] === 'get_sale_details' && isset($_GET['sale_id'])) {
    header('Content-Type: application/json');
    $sale_id = intval($_GET['sale_id']);
    
    try {
        // Get sale info
        $sale_stmt = $conn->prepare("SELECT s.*, u.name as cashier_name 
                                    FROM sales s 
                                    LEFT JOIN users u ON s.user_id = u.id 
                                    WHERE s.id = ?");
        $sale_stmt->execute([$sale_id]);
        $sale = $sale_stmt->fetch();
        
        if ($sale) {
            // Get sale items
            $items_stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
            $items_stmt->execute([$sale_id]);
            $items = $items_stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'sale' => [
                    'invoice_number' => $sale['invoice_number'],
                    'date' => date('d M Y H:i', strtotime($sale['created_at'])),
                    'customer_name' => $sale['customer_name'] ?: '-',
                    'cashier_name' => $sale['cashier_name'],
                    'payment_method' => ucfirst($sale['payment_method']),
                    'total_amount' => 'Rp ' . number_format($sale['total_amount'], 0, ',', '.'),
                    'discount_amount' => $sale['discount_amount'] > 0 ? 'Rp ' . number_format($sale['discount_amount'], 0, ',', '.') : 'Rp 0',
                    'tax_amount' => $sale['tax_amount'] > 0 ? 'Rp ' . number_format($sale['tax_amount'], 0, ',', '.') : 'Rp 0',
                    'final_amount' => 'Rp ' . number_format($sale['final_amount'], 0, ',', '.'),
                    'payment_amount' => 'Rp ' . number_format($sale['payment_amount'], 0, ',', '.'),
                    'change_amount' => 'Rp ' . number_format($sale['change_amount'], 0, ',', '.'),
                    'notes' => $sale['notes']
                ],
                'items' => array_map(function($item) {
                    return [
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'price' => 'Rp ' . number_format($item['price'], 0, ',', '.'),
                        'subtotal' => 'Rp ' . number_format($item['subtotal'], 0, ',', '.')
                    ];
                }, $items)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sale not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get cashiers for filter
$cashiers = $conn->query("SELECT id, name FROM users WHERE role IN ('admin', 'kasir') ORDER BY name");

// Build query conditions
$where_conditions = ["s.status = 'completed'"];
$params = [];
$types = '';

if (!empty($start_date)) {
    $where_conditions[] = "DATE(s.created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $where_conditions[] = "DATE(s.created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($cashier_filter)) {
    $where_conditions[] = "s.user_id = ?";
    $params[] = (int)$cashier_filter;
    $types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get sales data
$sales_query = "SELECT s.*, u.name as cashier_name,
                COUNT(si.id) as total_items,
                SUM(si.quantity) as total_quantity
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN sale_items si ON s.id = si.sale_id
                {$where_clause}
                GROUP BY s.id
                ORDER BY s.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sales_query);
    $stmt->execute($params);
    $sales = $stmt;
} else {
    $sales = $conn->query($sales_query);
}

// Get summary statistics
$summary_query = "SELECT 
                    COUNT(s.id) as total_transactions,
                    SUM(s.total_amount) as total_sales,
                    SUM(s.final_amount) as total_final,
                    AVG(s.final_amount) as avg_transaction,
                    SUM(s.discount_amount) as total_discount
                  FROM sales s
                  {$where_clause}";

if (!empty($params)) {
    $stmt = $conn->prepare($summary_query);
    $stmt->execute($params);
    $summary = $stmt->fetch();
} else {
    $summary = $conn->query($summary_query)->fetch();
}

// Get top products in this period
$top_products_query = "SELECT p.name as product_name,
                        c.name as category_name,
                        SUM(si.quantity) as total_sold,
                        SUM(si.subtotal) as total_revenue
                       FROM sale_items si
                       JOIN products p ON si.product_id = p.id
                       JOIN categories c ON p.category_id = c.id
                       JOIN sales s ON si.sale_id = s.id
                       {$where_clause}
                       GROUP BY p.id, p.name, c.name
                       ORDER BY total_sold DESC
                       LIMIT 10";

if (!empty($params)) {
    $stmt = $conn->prepare($top_products_query);
    $stmt->execute($params);
    $top_products = $stmt;
} else {
    $top_products = $conn->query($top_products_query);
}

// Get daily sales data for chart
$daily_sales_query = "SELECT DATE(s.created_at) as sale_date,
                       COUNT(s.id) as transactions,
                       SUM(s.final_amount) as revenue
                       FROM sales s
                       {$where_clause}
                       GROUP BY DATE(s.created_at)
                       ORDER BY sale_date ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($daily_sales_query);
    $stmt->execute($params);
    $daily_sales = $stmt->fetchAll();
} else {
    $daily_sales = $conn->query($daily_sales_query)->fetchAll();
}

// Get payment method data for chart
$payment_method_query = "SELECT s.payment_method,
                         COUNT(s.id) as count,
                         SUM(s.final_amount) as total
                         FROM sales s
                         {$where_clause}
                         GROUP BY s.payment_method
                         ORDER BY total DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($payment_method_query);
    $stmt->execute($params);
    $payment_methods = $stmt->fetchAll();
} else {
    $payment_methods = $conn->query($payment_method_query)->fetchAll();
}

// Export to CSV if requested
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // CSV Header dengan detail produk
    fputcsv($output, ['Invoice', 'Tanggal', 'Pelanggan', 'Kasir', 'Nama Produk', 'Jumlah', 'Harga Satuan', 'Subtotal', 'Total Pembayaran', 'Diskon', 'Metode Pembayaran', 'Jumlah Bayar', 'Kembalian']);
    
    // Get sales with items detail - need to rebuild query with same conditions
    $sales_detail_query = "SELECT s.*, u.name as cashier_name
                          FROM sales s
                          LEFT JOIN users u ON s.user_id = u.id
                          {$where_clause}
                          ORDER BY s.created_at DESC";
    
    // Re-execute with same params
    if (!empty($params)) {
        $stmt = $conn->prepare($sales_detail_query);
        $stmt->execute($params);
        $sales_detail = $stmt;
    } else {
        $sales_detail = $conn->query($sales_detail_query);
    }
    
    // Loop through each sale and its items
    while ($sale = $sales_detail->fetch()) {
        // Get sale items for this sale
        $items_stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
        $items_stmt->execute([$sale['id']]);
        $items = $items_stmt->fetchAll();
        
        if (count($items) > 0) {
            // Write each item as a separate row
            foreach ($items as $index => $item) {
                fputcsv($output, [
                    $sale['invoice_number'],
                    date('Y-m-d H:i:s', strtotime($sale['created_at'])),
                    $sale['customer_name'] ?: '-',
                    $sale['cashier_name'],
                    $item['product_name'],
                    $item['quantity'],
                    number_format($item['price'], 0, ',', '.'),
                    number_format($item['subtotal'], 0, ',', '.'),
                    // Total amount, discount, payment method, payment amount, change hanya di row pertama
                    $index === 0 ? number_format($sale['final_amount'], 0, ',', '.') : '',
                    $index === 0 ? number_format($sale['discount_amount'], 0, ',', '.') : '',
                    $index === 0 ? ucfirst($sale['payment_method']) : '',
                    $index === 0 ? number_format($sale['payment_amount'], 0, ',', '.') : '',
                    $index === 0 ? number_format($sale['change_amount'], 0, ',', '.') : ''
                ]);
            }
        } else {
            // If no items, still write the sale row
            fputcsv($output, [
                $sale['invoice_number'],
                date('Y-m-d H:i:s', strtotime($sale['created_at'])),
                $sale['customer_name'] ?: '-',
                $sale['cashier_name'],
                'Tidak ada item',
                '',
                '',
                '',
                number_format($sale['final_amount'], 0, ',', '.'),
                number_format($sale['discount_amount'], 0, ',', '.'),
                ucfirst($sale['payment_method']),
                number_format($sale['payment_amount'], 0, ',', '.'),
                number_format($sale['change_amount'], 0, ',', '.')
            ]);
        }
    }
    
    fclose($output);
    exit();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="page-header no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-chart-line me-2"></i>Laporan Penjualan</h2>
                <p class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Periode: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>
                </p>
            </div>
            <div class="btn-group no-print">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-coffee">
                    <i class="fas fa-download me-1"></i>Ekspor CSV
                </a>
                <button class="btn btn-coffee" onclick="printSalesReport()">
                    <i class="fas fa-print me-1"></i>Cetak
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="table-container mb-4 no-print">
        <div class="section-header">
            <h5><i class="fas fa-filter me-2"></i>Filter Laporan</h5>
        </div>
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal Mulai</label>
                    <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal Akhir</label>
                    <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Kasir</label>
                    <select name="cashier" class="form-select" id="cashier">
                        <option value="">Semua Kasir</option>
                        <?php 
                        // Reset cursor untuk cashiers
                        $cashiers->execute();
                        while ($cashier = $cashiers->fetch()): 
                        ?>
                        <option value="<?= $cashier['id'] ?>" <?= $cashier_filter == $cashier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cashier['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-coffee flex-grow-1" id="filterBtn">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="sales_report.php" class="btn btn-outline-secondary flex-grow-1">
                        <i class="fas fa-refresh"></i>
                    </a>
                </div>
            </form>
    </div>

    <!-- Report Content (for print) -->
    <div id="report-content">
        <!-- Summary Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-receipt fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold"><?= number_format($summary['total_transactions'] ?? 0) ?></h4>
                        <p class="mb-0 text-muted">Total Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['total_final'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Total Pendapatan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-calculator fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['avg_transaction'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Rata-rata Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['total_discount'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Total Diskon</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4 mb-4 no-print">
            <!-- Daily Sales Chart -->
            <div class="col-md-8">
                <div class="table-container">
                    <div class="section-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Grafik Penjualan Harian</h5>
                    </div>
                    <canvas id="dailySalesChart" height="80"></canvas>
                </div>
            </div>
            
            <!-- Payment Method Chart -->
            <div class="col-md-4">
                <div class="table-container">
                    <div class="section-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Metode Pembayaran</h5>
                    </div>
                    <canvas id="paymentMethodChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Products Chart -->
        <div class="row g-4 mb-4 no-print">
            <div class="col-12">
                <div class="table-container">
                    <div class="section-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Produk Terlaris</h5>
                    </div>
                    <canvas id="topProductsChart" height="60"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sales Table -->
            <div class="col-md-8">
                <div class="table-container">
                    <div class="section-header">
                        <h5>
                            <i class="fas fa-list me-2"></i>Transaksi Penjualan
                        </h5>
                    </div>
                        <?php if ($sales->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Pelanggan</th>
                                        <th>Kasir</th>
                                        <th>Item</th>
                                        <th>Total</th>
                                        <th>Pembayaran</th>
                                        <th>Tanggal</th>
                                        <th class="no-print">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $sales->fetch()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($sale['invoice_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $sale['total_items'] ?> item (<?= $sale['total_quantity'] ?> qty)
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                Rp <?= number_format($sale['final_amount'], 0, ',', '.') ?>
                                            </strong>
                                            <?php if ($sale['discount_amount'] > 0): ?>
                                            <br><small class="text-muted">
                                                Disc: Rp <?= number_format($sale['discount_amount'], 0, ',', '.') ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= ucfirst($sale['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= date('d M Y H:i', strtotime($sale['created_at'])) ?></small>
                                        </td>
                                        <td class="no-print">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="viewSaleDetails(<?= $sale['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h4>Tidak Ada Penjualan</h4>
                            <p>Tidak ada penjualan untuk periode yang dipilih</p>
                        </div>
                        <?php endif; ?>
                </div>
            </div>
            <!-- Top Products -->
            <div class="col-md-4">
                <div class="table-container mb-4">
                    <div class="section-header">
                        <h5>
                            <i class="fas fa-trophy me-2"></i>Produk Terlaris
                        </h5>
                    </div>
                        <?php if ($top_products->rowCount() > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php $rank = 1; while ($product = $top_products->fetch()): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">
                                        <span class="badge bg-primary me-2"><?= $rank ?></span>
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($product['category_name']) ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success">
                                        Rp <?= number_format($product['total_revenue'], 0, ',', '.') ?>
                                    </div>
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
            </div>
        </div>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Penjualan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Chart data from PHP
const dailySalesData = <?= json_encode($daily_sales) ?>;
const paymentMethodsData = <?= json_encode($payment_methods) ?>;
const topProductsData = <?php 
    // Re-execute query for chart data
    if (!empty($params)) {
        $stmt = $conn->prepare($top_products_query);
        $stmt->execute($params);
        $top_products_chart = $stmt;
    } else {
        $top_products_chart = $conn->query($top_products_query);
    }
    $top_products_array = [];
    while ($product = $top_products_chart->fetch()) {
        $top_products_array[] = $product;
    }
    echo json_encode($top_products_array);
?>;

// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Daily Sales Chart (Line Chart)
    const dailySalesCtx = document.getElementById('dailySalesChart');
    if (dailySalesCtx && dailySalesData.length > 0) {
        const labels = dailySalesData.map(item => {
            const date = new Date(item.sale_date);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        });
        const revenueData = dailySalesData.map(item => parseFloat(item.revenue || 0));
        const transactionsData = dailySalesData.map(item => parseInt(item.transactions || 0));

        new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: revenueData,
                    borderColor: '#6F4E37',
                    backgroundColor: 'rgba(111, 78, 55, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Jumlah Transaksi',
                    data: transactionsData,
                    borderColor: '#A67C52',
                    backgroundColor: 'rgba(166, 124, 82, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                } else {
                                    return 'Transaksi: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Transaksi'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    // Payment Method Chart (Pie Chart)
    const paymentMethodCtx = document.getElementById('paymentMethodChart');
    if (paymentMethodCtx && paymentMethodsData.length > 0) {
        const paymentLabels = paymentMethodsData.map(item => {
            const method = item.payment_method || 'Unknown';
            return method.charAt(0).toUpperCase() + method.slice(1);
        });
        const paymentValues = paymentMethodsData.map(item => parseFloat(item.total || 0));
        const paymentColors = ['#6F4E37', '#A67C52', '#D2B48C', '#CD853F', '#4A2C1A'];

        new Chart(paymentMethodCtx, {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentValues,
                    backgroundColor: paymentColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': Rp ' + value.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Products Chart (Bar Chart)
    const topProductsCtx = document.getElementById('topProductsChart');
    if (topProductsCtx && topProductsData.length > 0) {
        const productLabels = topProductsData.map(item => item.product_name);
        const productSold = topProductsData.map(item => parseInt(item.total_sold || 0));
        const productRevenue = topProductsData.map(item => parseFloat(item.total_revenue || 0));

        new Chart(topProductsCtx, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Jumlah Terjual',
                    data: productSold,
                    backgroundColor: 'rgba(111, 78, 55, 0.8)',
                    borderColor: '#6F4E37',
                    borderWidth: 2,
                    yAxisID: 'y'
                }, {
                    label: 'Pendapatan (Rp)',
                    data: productRevenue,
                    backgroundColor: 'rgba(166, 124, 82, 0.8)',
                    borderColor: '#A67C52',
                    borderWidth: 2,
                    type: 'line',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Terjual: ' + context.parsed.y + ' unit';
                                } else {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Jumlah Terjual'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    // Filter form handler - skip generic handler
    const filterForm = document.getElementById('filterForm');
    const filterBtn = document.getElementById('filterBtn');
    
    if (filterForm && filterBtn) {
        filterForm.addEventListener('submit', function(e) {
            // Validasi tanggal
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (!startDate || !endDate) {
                e.preventDefault();
                alert('Silakan pilih tanggal mulai dan tanggal akhir!');
                return false;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
                return false;
            }
            
            // Set button to processing state
            const originalBtnText = filterBtn.innerHTML;
            filterBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Filtering...';
            filterBtn.disabled = true;
            
            // Set timeout to reset button if form doesn't submit (failsafe)
            let resetTimeout = setTimeout(() => {
                filterBtn.innerHTML = originalBtnText;
                filterBtn.disabled = false;
                alert('Filter terlalu lama. Silakan coba lagi atau refresh halaman.');
            }, 10000);
            
            // Store timeout ID for cleanup
            window.filterFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.filterFormTimeout) {
                    clearTimeout(window.filterFormTimeout);
                }
            });
            
            // Allow form to submit
            return true;
        });
        
        // Reset button state on page load
        filterBtn.disabled = false;
        filterBtn.innerHTML = '<i class="fas fa-filter me-1"></i>Filter';
    }
});

function printSalesReport() {
    // Create print window with only report content
    const reportContent = document.getElementById('report-content');
    if (!reportContent) {
        showNotification('Konten laporan tidak ditemukan!', 'warning');
        return;
    }
    
    // Clone content to avoid modifying original
    const contentClone = reportContent.cloneNode(true);
    
    // Remove all no-print elements from clone
    const noPrintElements = contentClone.querySelectorAll('.no-print, [class*="no-print"]');
    noPrintElements.forEach(el => el.remove());
    
    // Remove all buttons and button groups
    const buttons = contentClone.querySelectorAll('button, .btn, .btn-group, a.btn');
    buttons.forEach(el => el.remove());
    
    // Remove action column from tables
    const tables = contentClone.querySelectorAll('table');
    tables.forEach(table => {
        // Remove action header
        const actionHeaders = table.querySelectorAll('th');
        actionHeaders.forEach((th, index) => {
            if (th.textContent.trim() === 'Aksi' || th.classList.contains('no-print')) {
                th.remove();
            }
        });
        
        // Remove action cells
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((td, index) => {
                if (td.classList.contains('no-print') || td.querySelector('button')) {
                    td.remove();
                }
            });
        });
    });
    
    // Create new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Get page title
    const pageTitle = document.querySelector('.page-header h2')?.textContent || 'Laporan Penjualan';
    const period = document.querySelector('.page-header p')?.textContent || '';
    
    // Build print HTML
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${pageTitle}</title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 1cm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    margin: 0;
                    padding: 20px;
                    background: white;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 10px;
                }
                .print-header h1 {
                    margin: 0;
                    font-size: 24px;
                    color: #333;
                }
                .print-header p {
                    margin: 5px 0;
                    color: #666;
                }
                .stats-row {
                    display: flex;
                    justify-content: space-around;
                    margin: 20px 0;
                    flex-wrap: wrap;
                }
                .stat-box {
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin: 5px;
                    text-align: center;
                    min-width: 150px;
                    border-radius: 5px;
                }
                .stat-box h4 {
                    margin: 5px 0;
                    font-size: 18px;
                    color: #333;
                }
                .stat-box p {
                    margin: 5px 0;
                    color: #666;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .print-footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 10px;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>${pageTitle}</h1>
                <p>${period}</p>
                <p>Dicetak pada: ${new Date().toLocaleString('id-ID')}</p>
            </div>
            ${contentClone.innerHTML}
            <div class="print-footer">
                <p>SIM Coffee Shop - Laporan Penjualan | Dibuat pada ${new Date().toLocaleString('id-ID')}</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for content to load, then print
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        // Close window after printing (optional)
        // printWindow.close();
    }, 500);
}

function viewSaleDetails(saleId) {
    // Show loading
        document.getElementById("saleDetailsContent").innerHTML = 
            "<div class='text-center py-4'><div class='spinner-border text-primary'></div><p class='mt-3'>Memuat detail penjualan...</p></div>";
    
    var modal = new bootstrap.Modal(document.getElementById("saleDetailsModal"));
    modal.show();
    
    // Fetch sale details via AJAX
    fetch('sales_report.php?action=get_sale_details&sale_id=' + saleId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="mb-4">
                        <h5><i class="fas fa-receipt me-2"></i>Invoice: ${data.sale.invoice_number}</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Tanggal:</strong> ${data.sale.date}</p>
                                <p><strong>Pelanggan:</strong> ${data.sale.customer_name || '-'}</p>
                                <p><strong>Kasir:</strong> ${data.sale.cashier_name}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Metode Pembayaran:</strong> ${data.sale.payment_method}</p>
                                <p><strong>Subtotal:</strong> ${data.sale.total_amount}</p>
                                ${data.sale.discount_amount > 0 ? `<p><strong>Diskon:</strong> ${data.sale.discount_amount}</p>` : ''}
                                ${data.sale.tax_amount > 0 ? `<p><strong>Pajak:</strong> ${data.sale.tax_amount}</p>` : ''}
                                <p><strong class="text-success">Total:</strong> ${data.sale.final_amount}</p>
                                <p><strong>Bayar:</strong> ${data.sale.payment_amount}</p>
                                <p><strong>Kembalian:</strong> ${data.sale.change_amount}</p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-3"><i class="fas fa-shopping-cart me-2"></i>Item yang Dijual:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama Produk</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.items.forEach((item, index) => {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.product_name}</td>
                            <td class="text-center">${item.quantity}</td>
                            <td class="text-end">${item.price}</td>
                            <td class="text-end"><strong>${item.subtotal}</strong></td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total:</th>
                                    <th class="text-end text-success">${data.sale.final_amount}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
                
                if (data.sale.notes) {
                    html += `<div class="mt-3"><strong>Notes:</strong> ${data.sale.notes}</div>`;
                }
                
                document.getElementById("saleDetailsContent").innerHTML = html;
            } else {
                document.getElementById("saleDetailsContent").innerHTML = 
                    "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>Error memuat detail penjualan: " + (data.message || 'Error tidak diketahui') + "</div>";
            }
        })
        .catch(error => {
            document.getElementById("saleDetailsContent").innerHTML = 
                "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>Error memuat detail penjualan. Silakan coba lagi.</div>";
            console.error('Error:', error);
        });
}
</script>

<style>
.dashboard-stats {
    border-radius: 20px;
    background: linear-gradient(135deg, #ffffff 0%, var(--coffee-cream) 100%);
    border: 1px solid rgba(210, 180, 140, 0.3);
    box-shadow: 0 8px 24px rgba(111, 78, 55, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-stats:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 32px rgba(111, 78, 55, 0.15);
}

.coffee-icon {
    color: var(--coffee-primary);
}

/* Print styles - hide non-essential elements */
.no-print {
    /* Elements with this class won't print */
}

@media print {
    .navbar, .page-header, .btn-group, .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .table-container {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
    }
    
    .dashboard-stats {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    /* Ensure tables don't break across pages */
    table {
        page-break-inside: avoid;
    }
    
    tr {
        page-break-inside: avoid;
    }
}
</style>

<?php include 'includes/footer.php'; ?>