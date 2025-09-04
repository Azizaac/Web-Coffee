<?php
$page_title = "Sales Report";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$start_date = $_GET['start_date'] ?? '2025-01-01'; // Start from January 2025
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$cashier_filter = $_GET['cashier'] ?? '';
$export = $_GET['export'] ?? '';

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

// Export to CSV if requested
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice', 'Customer', 'Cashier', 'Total Amount', 'Payment Method', 'Date', 'Items Count']);
    
    // Reset pointer for PDO
    $sales->execute($params);
    while ($sale = $sales->fetch()) {
        fputcsv($output, [
            $sale['invoice_number'],
            $sale['customer_name'] ?: '-',
            $sale['cashier_name'],
            $sale['final_amount'],
            $sale['payment_method'],
            date('Y-m-d H:i:s', strtotime($sale['created_at'])),
            $sale['total_items']
        ]);
    }
    fclose($output);
    exit();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
            <span class="text-muted small">Period: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
        </div>
        <div class="btn-group">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-sm">
                <i class="fas fa-download me-1"></i>CSV
            </a>
            <button class="btn btn-info btn-sm" onclick="printSalesReport()">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Cashier</label>
                    <select name="cashier" class="form-select">
                        <option value="">All Cashiers</option>
                        <?php while ($cashier = $cashiers->fetch()): ?>
                        <option value="<?= $cashier['id'] ?>" <?= $cashier_filter == $cashier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cashier['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-coffee flex-grow-1">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="sales_report.php" class="btn btn-outline-secondary flex-grow-1">
                        <i class="fas fa-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
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
                        <p class="mb-0 text-muted">Total Transactions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['total_final'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Total Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-calculator fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['avg_transaction'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Avg Transaction</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-stats shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2 coffee-icon"></i>
                        <h4 class="fw-bold">Rp <?= number_format($summary['total_discount'] ?? 0, 0, ',', '.') ?></h4>
                        <p class="mb-0 text-muted">Total Discounts</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sales Table -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-list me-2"></i>Sales Transactions
                        </h5>
                        <?php if ($sales->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Cashier</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                        <th>Action</th>
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
                                                <?= $sale['total_items'] ?> items (<?= $sale['total_quantity'] ?> qty)
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
                                        <td>
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
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No sales found for the selected period</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Top Products -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-trophy me-2"></i>Top Selling Products
                        </h5>
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
                                    <small class="text-muted"><?= $product['total_sold'] ?> sold</small>
                                </div>
                            </div>
                            <?php $rank++; endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No sales data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
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
                <h5 class="modal-title">Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function printSalesReport() {
    // Hide navigation and filter form for printing
    const navbar = document.querySelector('.navbar');
    const filterForm = document.querySelector('.card.shadow-sm.mb-4');
    const buttons = document.querySelector('.btn-group');
    
    if (navbar) navbar.style.display = 'none';
    if (filterForm) filterForm.style.display = 'none';
    if (buttons) buttons.style.display = 'none';
    
    // Print the page
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        if (navbar) navbar.style.display = 'block';
        if (filterForm) filterForm.style.display = 'block';
        if (buttons) buttons.style.display = 'block';
    }, 1000);
}
function viewSaleDetails(saleId) {
    document.getElementById("saleDetailsContent").innerHTML =
        "<div class='text-center py-4'><div class='spinner-border'></div><p>Loading sale details for ID: " + saleId + "</p></div>";
    var modal = new bootstrap.Modal(document.getElementById("saleDetailsModal"));
    modal.show();
}
</script>

<style>
body {
    background: linear-gradient(135deg, #f5e9da, #e6d3b3);
}
.dashboard-stats {
    border-radius: 15px;
    background: #fff8f0;
    border: none;
}
.coffee-icon {
    color: #8B4513;
}
.btn-coffee {
    background: linear-gradient(45deg, #8B4513, #D2691E);
    color: #fff;
    border: none;
}
.btn-coffee:hover {
    background: linear-gradient(45deg, #654321, #a67c52);
    color: #fff;
}
.card {
    border-radius: 15px;
}
.table-container {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(139,69,19,0.07);
    padding: 1.5rem;
}
@media print {
    .navbar, .card.shadow-sm.mb-4, .btn-group {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .table-container {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .dashboard-stats {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<script>
function viewSaleDetails(saleId) {
    // This would typically make an AJAX call to get sale details
    // For now, we'll show a placeholder
    document.getElementById("saleDetailsContent").innerHTML = 
        "<p>Loading sale details for ID: " + saleId + "</p>";
    
    var modal = new bootstrap.Modal(document.getElementById("saleDetailsModal"));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>