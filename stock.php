<?php
$page_title = "Stock Management";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);
    $notes = trim($_POST['notes']);
    
    if ($product_id > 0 && $new_stock >= 0) {
        // Get current stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch();
        $current_stock = $result['stock'];
        
        $difference = $new_stock - $current_stock;
        
        // Update stock
        $stmt = $conn->prepare("UPDATE products SET stock = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        if ($stmt->rowCount() > 0) {
            // Record stock movement
            $movement_type = $difference > 0 ? 'in' : 'out';
            $movement_quantity = abs($difference);
            
            if ($movement_quantity > 0) {
                $movement_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id, created_at, updated_at) VALUES (?, ?, ?, 'adjustment', ?, ?, 1, NOW(), NOW())");
                $movement_stmt->execute([$product_id, $movement_type, $movement_quantity, $product_id, $notes]);
            }
            
            $message = "Stock updated successfully!";
        } else {
            $message = "Error updating stock!";
            $message_type = 'danger';
        }
    } else {
        $message = "Invalid stock data!";
        $message_type = 'danger';
    }
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

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-warehouse me-2"></i>Stock Management</h2>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="printStockReport()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Low Stock Alert -->
    <?php if ($low_stock_products->rowCount() > 0): ?>
    <div class="alert alert-warning mb-4">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert</h5>
        <p class="mb-2">The following products are running low on stock:</p>
        <ul class="mb-0">
            <?php while ($product = $low_stock_products->fetch()): ?>
            <li>
                <strong><?= htmlspecialchars($product['name']) ?></strong> 
                (<?= $product['stock'] ?> left, minimum: <?= $product['min_stock'] ?>)
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Products Stock Table -->
        <div class="col-md-8">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-boxes me-2"></i>Product Stock Levels
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                        <span class="badge bg-danger">Low Stock</span>
                                    <?php elseif ($product['stock'] <= $product['min_stock'] * 2): ?>
                                        <span class="badge bg-warning">Warning</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Good</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="updateStock(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['stock'] ?>)">
                                        <i class="fas fa-edit"></i> Update
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
                <h5 class="mb-3">
                    <i class="fas fa-history me-2"></i>Recent Stock Movements
                </h5>
                
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
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No stock movements</p>
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
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-warehouse me-2"></i>Update Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" id="update_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="update_product_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_stock" class="form-label">New Stock Level *</label>
                        <input type="number" class="form-control" id="new_stock" name="new_stock" 
                               min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Reason for stock change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-coffee">
                        <i class="fas fa-save me-2"></i>Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStock(productId, productName, currentStock) {
    document.getElementById('update_product_id').value = productId;
    document.getElementById('update_product_name').value = productName;
    document.getElementById('new_stock').value = currentStock;
    
    var modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
    modal.show();
}

function printStockReport() {
    // Hide navigation and buttons for printing
    const navbar = document.querySelector('.navbar');
    const buttons = document.querySelector('.btn-group');
    
    if (navbar) navbar.style.display = 'none';
    if (buttons) buttons.style.display = 'none';
    
    // Print the page
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        if (navbar) navbar.style.display = 'block';
        if (buttons) buttons.style.display = 'block';
    }, 1000);
}
</script>

<style>
@media print {
    .navbar, .btn-group {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .table-container {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .alert {
        display: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
