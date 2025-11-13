<?php
// Start output buffering
ob_start();

$page_title = "Manajemen Stok";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle POST requests (Update Stock)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $new_stock = intval($_POST['new_stock'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if ($product_id > 0 && $new_stock >= 0) {
        try {
            // Get current stock
            $stmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
            $message = "Produk tidak ditemukan!";
            $message_type = 'danger';
            } else {
                $current_stock = $product['stock'];
                $difference = $new_stock - $current_stock;
                
                // Update stock
                $update_stmt = $conn->prepare("UPDATE products SET stock = ?, updated_at = NOW() WHERE id = ?");
                $result = $update_stmt->execute([$new_stock, $product_id]);
                
                if ($result) {
                    // Record stock movement if there's a difference
                    if ($difference != 0) {
                        try {
                            $movement_type = $difference > 0 ? 'in' : 'out';
                            $movement_quantity = abs($difference);
                            
                            $movement_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id, created_at, updated_at) VALUES (?, ?, ?, 'adjustment', ?, ?, ?, NOW(), NOW())");
                            $movement_stmt->execute([$product_id, $movement_type, $movement_quantity, $product_id, $notes ?: 'Stock adjustment', $user_id]);
                        } catch (PDOException $e) {
                            error_log("Stock Movement Error: " . $e->getMessage());
                            // Don't fail the update if movement recording fails
                        }
                    }
                    
                    $message = "Stok berhasil diperbarui!";
                } else {
                    $message = "Gagal memperbarui stok!";
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Stock Update Error: " . $e->getMessage());
        }
    } else {
        $message = "Data stok tidak valid!";
        $message_type = 'danger';
    }
    
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: stock.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        echo '<script>window.location.href="stock.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Get message from URL (after redirect)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

// Get low stock products
$low_stock_products = $conn->query("SELECT * FROM products WHERE stock <= min_stock AND status = 'active' ORDER BY (stock - min_stock) ASC");

// Get all products with category info
$products = $conn->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.status = 'active'
                         ORDER BY p.name");

// Get recent stock movements
$stock_movements = $conn->query("SELECT sm.*, p.name as product_name, u.name as user_name
                                FROM stock_movements sm
                                LEFT JOIN products p ON sm.product_id = p.id
                                LEFT JOIN users u ON sm.user_id = u.id
                                ORDER BY sm.created_at DESC
                                LIMIT 10");

// End output buffering before output
if (!headers_sent()) {
    ob_end_flush();
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header no-print">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-warehouse me-2"></i>Manajemen Stok</h2>
                <p class="mb-0">Pantau dan perbarui tingkat inventori produk</p>
            </div>
            <button class="btn btn-outline-coffee" onclick="printStockReport()">
                <i class="fas fa-print me-2"></i>Cetak Laporan
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Low Stock Alert -->
    <?php if ($low_stock_products->rowCount() > 0): ?>
    <div class="alert alert-warning mb-4 no-print">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Peringatan Stok Menipis</h5>
        <p class="mb-2">Produk berikut stoknya menipis:</p>
        <ul class="mb-0">
            <?php while ($product = $low_stock_products->fetch()): ?>
            <li>
                <strong><?= htmlspecialchars($product['name']) ?></strong> 
                (Tersisa: <?= $product['stock'] ?>, minimum: <?= $product['min_stock'] ?>)
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Products Stock Table -->
        <div class="col-md-8">
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-boxes me-2"></i>Tingkat Stok Produk
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th>Stok Saat Ini</th>
                                <th>Stok Min</th>
                                <th>Status</th>
                                <th class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products->fetch()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $product['stock'] <= $product['min_stock'] ? 'warning' : 'success' ?> fs-6">
                                        <?= $product['stock'] ?>
                                    </span>
                                </td>
                                <td><?= $product['min_stock'] ?></td>
                                <td>
                                    <?php if ($product['stock'] <= $product['min_stock']): ?>
                                        <span class="badge bg-danger">Stok Menipis</span>
                                    <?php elseif ($product['stock'] <= $product['min_stock'] * 2): ?>
                                        <span class="badge bg-warning">Peringatan</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Baik</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="updateStock(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>', <?= $product['stock'] ?>)">
                                        <i class="fas fa-edit"></i> Perbarui
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Stock Movements -->
        <div class="col-md-4">
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-history me-2"></i>Pergerakan Stok Terbaru
                    </h5>
                </div>
                
                <?php if ($stock_movements->rowCount() > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($movement = $stock_movements->fetch()): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($movement['product_name']) ?></h6>
                                    <p class="mb-1">
                                        <span class="badge bg-<?= $movement['type'] == 'in' ? 'success' : 'danger' ?>">
                                            <?= $movement['type'] == 'in' ? '+' : '-' ?><?= $movement['quantity'] ?>
                                        </span>
                                        <?= htmlspecialchars($movement['notes']) ?>
                                    </p>
                                    <small class="text-muted">
                                        <?= date('M j, H:i', strtotime($movement['created_at'])) ?> • 
                                        <?= htmlspecialchars($movement['user_name']) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h4>Tidak Ada Pergerakan Stok</h4>
                        <p>Perubahan stok akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="stock.php" id="stockForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-warehouse me-2"></i>Perbarui Stok
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" id="update_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Produk</label>
                        <input type="text" class="form-control" id="update_product_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_stock" class="form-label">Level Stok Baru *</label>
                        <input type="number" class="form-control" id="new_stock" name="new_stock" 
                               min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Alasan perubahan stok..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Perbarui Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Notification function
function showNotification(message, type = 'info', duration = 4000) {
    const existing = document.querySelectorAll('.crud-notification');
    existing.forEach(n => n.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed crud-notification`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'danger' ? 'exclamation-circle' : 
                 type === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
    alertDiv.innerHTML = `
        <i class="fas fa-${icon} me-2"></i>
        <strong>${type === 'danger' ? 'Error!' : type === 'warning' ? 'Peringatan!' : type === 'success' ? 'Berhasil!' : 'Info!'}</strong><br>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            bsAlert.close();
        }
    }, duration);
}

function updateStock(productId, productName, currentStock) {
    document.getElementById('update_product_id').value = productId;
    document.getElementById('update_product_name').value = productName;
    document.getElementById('new_stock').value = currentStock;
    
    var modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const stockForm = document.getElementById('stockForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (stockForm && submitBtn) {
        stockForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate required fields
            const requiredFields = stockForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate stock value
            const newStockInput = stockForm.querySelector('input[name="new_stock"]');
            const newStock = parseInt(newStockInput.value);
            if (isNaN(newStock) || newStock < 0) {
                newStockInput.classList.add('is-invalid');
                isValid = false;
                showNotification('Stok harus berupa angka positif!', 'warning');
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = stockForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                if (!newStockInput.classList.contains('is-invalid')) {
                    showNotification('Silakan isi semua field yang wajib dengan nilai yang valid!', 'warning');
                }
                return false;
            }
            
            // Set button to processing state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            submitBtn.disabled = true;
            
            // Set timeout to reset button if form doesn't submit (failsafe)
            let resetTimeout = setTimeout(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                showNotification('Pengiriman form terlalu lama. Silakan coba lagi atau refresh halaman.', 'warning', 5000);
            }, 10000); // 10 seconds timeout
            
            // Store timeout ID for cleanup
            window.stockFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.stockFormTimeout) {
                    clearTimeout(window.stockFormTimeout);
                }
            });
            
            // Allow form to submit - the redirect will happen on server side
            return true;
        });
        
        // Reset button state on page load (in case of error or page reload)
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Perbarui Stok';
    }
});

function printStockReport() {
    // Get report content - get the products table container
    const reportContent = document.querySelector('.table-container');
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
    const pageTitle = document.querySelector('.page-header h2')?.textContent || 'Laporan Stok';
    
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
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>${pageTitle}</h1>
                <p>Dicetak pada: ${new Date().toLocaleString('id-ID')}</p>
            </div>
            ${contentClone.innerHTML}
            <div class="print-footer">
                <p>SIM Coffee Shop - Laporan Stok | Dibuat pada ${new Date().toLocaleString('id-ID')}</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for content to load, then print
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
    }, 500);
}
</script>

<style>
.no-print {
    /* Elements with this class won't print */
}

@media print {
    .navbar, .page-header, .btn-group, .no-print, .alert {
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
    
    table {
        page-break-inside: avoid;
    }
    
    tr {
        page-break-inside: avoid;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
