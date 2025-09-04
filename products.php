<?php
$page_title = "Products Management";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $min_stock = intval($_POST['min_stock']);
                $category_id = intval($_POST['category_id']);
                $supplier_id = intval($_POST['supplier_id']);
                
                if (!empty($name) && $price > 0 && $category_id > 0) {
                    $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, min_stock, category_id, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $stock, $min_stock, $category_id, $supplier_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Product added successfully!";
                    } else {
                        $message = "Error adding product!";
                        $message_type = 'danger';
                    }
                } else {
                    $message = "Please fill all required fields!";
                    $message_type = 'danger';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $min_stock = intval($_POST['min_stock']);
                $category_id = intval($_POST['category_id']);
                $supplier_id = intval($_POST['supplier_id']);
                $status = $_POST['status'];
                
                if (!empty($name) && $price > 0 && $category_id > 0 && $id > 0) {
                    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, min_stock=?, category_id=?, supplier_id=?, status=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$name, $description, $price, $stock, $min_stock, $category_id, $supplier_id, $status, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Product updated successfully!";
                    } else {
                        $message = "Error updating product!";
                        $message_type = 'danger';
                    }
                } else {
                    $message = "Invalid product data!";
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Product deleted successfully!";
        } else {
            $message = "Error deleting product!";
            $message_type = 'danger';
        }
    }
}

// Get edit data if editing
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Get all products with category and supplier info
$products = $conn->query("SELECT p.*, c.name as category_name, s.name as supplier_name
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         LEFT JOIN suppliers s ON p.supplier_id = s.id
                         ORDER BY p.name");

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box me-2"></i>Products Management</h2>
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="fas fa-plus me-2"></i>Add New Product
        </button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Min Stock</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->rowCount() > 0): ?>
                        <?php $no = 1; while ($product = $products->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                <?php if ($product['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($product['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                            <td>
                                <strong class="text-success">
                                    Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge bg-<?= $product['stock'] <= $product['min_stock'] ? 'warning' : 'success' ?>">
                                    <?= $product['stock'] ?>
                                </span>
                            </td>
                            <td><?= $product['min_stock'] ?></td>
                            <td><?= htmlspecialchars($product['supplier_name'] ?: '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $product['status'] == 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($product['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $product['id'] ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?= $product['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this product?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No products found</p>
                                <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#productModal">
                                    <i class="fas fa-plus me-2"></i>Add First Product
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-box me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Add New' ?> Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php while ($category = $categories->fetch()): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= (isset($edit_data) && $edit_data['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= isset($edit_data) ? htmlspecialchars($edit_data['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price *</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?= isset($edit_data) ? $edit_data['price'] : '' ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Stock</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       value="<?= isset($edit_data) ? $edit_data['stock'] : '0' ?>" 
                                       min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_stock" class="form-label">Min Stock</label>
                                <input type="number" class="form-control" id="min_stock" name="min_stock" 
                                       value="<?= isset($edit_data) ? $edit_data['min_stock'] : '0' ?>" 
                                       min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="supplier_id" class="form-label">Supplier</label>
                                <select class="form-control" id="supplier_id" name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php while ($supplier = $suppliers->fetch()): ?>
                                    <option value="<?= $supplier['id'] ?>" 
                                            <?= (isset($edit_data) && $edit_data['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <?php if (isset($edit_data)): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" <?= $edit_data['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $edit_data['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-coffee">
                        <i class="fas fa-save me-2"></i><?= isset($edit_data) ? 'Update' : 'Save' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($edit_data)): ?>
<script>
// Auto-open modal for editing
document.addEventListener('DOMContentLoaded', function() {
    var productModal = new bootstrap.Modal(document.getElementById('productModal'));
    productModal.show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
