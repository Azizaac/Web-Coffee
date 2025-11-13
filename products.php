<?php
// Start output buffering
ob_start();

$page_title = "Manajemen Produk";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle AJAX request for product details
if (isset($_GET['action']) && $_GET['action'] === 'get_product_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $product_id = intval($_GET['id']);
    
    try {
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               LEFT JOIN suppliers s ON p.supplier_id = s.id
                               WHERE p.id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            echo json_encode([
                'success' => true,
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'category_name' => $product['category_name'],
                    'supplier_name' => $product['supplier_name'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'min_stock' => $product['min_stock'],
                    'status' => $product['status'],
                    'image' => $product['image'],
                    'created_at' => date('d M Y H:i', strtotime($product['created_at'])),
                    'updated_at' => date('d M Y H:i', strtotime($product['updated_at']))
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle POST requests (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $name = trim($_POST['name'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        // Fix price - handle Indonesian format (100.000 -> 100000)
        $price_input = $_POST['price'] ?? '0';
        $price = floatval(str_replace('.', '', str_replace(',', '.', $price_input)));
        $stock = intval($_POST['stock'] ?? 0);
        $min_stock = intval($_POST['min_stock'] ?? 0);
        $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
        $status = $_POST['status'] ?? 'active';
        $image = null;
        
        // Validation
        if (empty($name)) {
            $message = "Nama produk wajib diisi!";
            $message_type = 'danger';
        } elseif ($category_id <= 0) {
            $message = "Silakan pilih kategori!";
            $message_type = 'danger';
        } elseif ($price <= 0) {
            $message = "Harga harus lebih besar dari 0!";
            $message_type = 'danger';
        } else {
            try {
                // Handle image upload
                $id = ($action == 'edit') ? intval($_POST['id'] ?? 0) : 0;
                $old_image = null;
                
                if ($action == 'edit' && $id > 0) {
                    // Get existing image first
                    $old_stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                    $old_stmt->execute([$id]);
                    $old_data = $old_stmt->fetch();
                    $old_image = $old_data['image'] ?? null;
                }
                
                // Handle image logic
                if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                    // Remove image - user explicitly wants to remove
                    $image = null;
                    if ($old_image && file_exists('uploads/products/' . $old_image)) {
                        @unlink('uploads/products/' . $old_image);
                    }
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Upload new image - user uploaded a new file
                    $uploadResult = uploadFile($_FILES['image'], 'uploads/products/');
                    if ($uploadResult['success']) {
                        $image = $uploadResult['filename'];
                        // Delete old image if exists
                        if ($old_image && file_exists('uploads/products/' . $old_image)) {
                            @unlink('uploads/products/' . $old_image);
                        }
                    } else {
                        $message = "Upload gambar gagal: " . $uploadResult['message'];
                        $message_type = 'danger';
                    }
                } elseif ($action == 'edit') {
                    // Edit mode: keep existing image if no new upload and not removed
                    $image = $old_image;
                }
                // For add action, $image remains null if no file uploaded
                
                if ($message_type != 'danger') {
                    if ($action == 'add') {
                        // Add new product
                        $stmt = $conn->prepare("INSERT INTO products (name, category_id, description, price, stock, min_stock, supplier_id, status, image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        $result = $stmt->execute([$name, $category_id, $description, $price, $stock, $min_stock, $supplier_id, $status, $image]);
                    
                    if ($result) {
                        $new_id = $conn->lastInsertId();
                        if ($new_id > 0) {
                            $message = "Produk berhasil ditambahkan!";
                        } else {
                            $message = "Gagal menambahkan produk!";
                            $message_type = 'danger';
                        }
                    } else {
                        $message = "Gagal menambahkan produk!";
                        $message_type = 'danger';
                    }
                    } elseif ($action == 'edit') {
                        // Edit product
                        if ($id > 0) {
                            // Prepare update query - handle image properly
                            if ($image !== null && $image !== '') {
                                // Update with image (new or existing)
                                $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, description = ?, price = ?, stock = ?, min_stock = ?, supplier_id = ?, status = ?, image = ?, updated_at = NOW() WHERE id = ?");
                                $result = $stmt->execute([$name, $category_id, $description, $price, $stock, $min_stock, $supplier_id, $status, $image, $id]);
                            } else {
                                // Update without image (set to NULL)
                                $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, description = ?, price = ?, stock = ?, min_stock = ?, supplier_id = ?, status = ?, image = NULL, updated_at = NOW() WHERE id = ?");
                                $result = $stmt->execute([$name, $category_id, $description, $price, $stock, $min_stock, $supplier_id, $status, $id]);
                            }
                            
                            if ($result) {
                                if ($stmt->rowCount() > 0) {
                                    $message = "Produk berhasil diperbarui!";
                                } else {
                                    // Check if data actually changed
                                    $check_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
                                    $check_stmt->execute([$id]);
                                    $current = $check_stmt->fetch();
                                    
                                    if ($current && 
                                        $current['name'] == $name && 
                                        $current['category_id'] == $category_id &&
                                        $current['price'] == $price &&
                                        $current['stock'] == $stock &&
                                        $current['min_stock'] == $min_stock) {
                                        $message = "Tidak ada perubahan data!";
                                        $message_type = 'warning';
                                    } else {
                                        $message = "Produk berhasil diperbarui!";
                                    }
                                }
                            } else {
                                $message = "Gagal memperbarui produk!";
                                $message_type = 'danger';
                            }
                        } else {
                            $message = "ID produk tidak valid!";
                            $message_type = 'danger';
                        }
                    }
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = 'danger';
                error_log("Product Error: " . $e->getMessage());
            }
        }
    }
    
    // Always redirect after POST to prevent resubmission
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: products.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        echo '<script>window.location.href="products.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Handle GET requests (Delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Produk berhasil dihapus!";
            } else {
                $message = "Produk tidak ditemukan!";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Delete Product Error: " . $e->getMessage());
        }
        
        // Clear ALL output buffers and redirect IMMEDIATELY
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Force redirect - no output allowed
        if (!headers_sent()) {
            header("Location: products.php?msg=" . urlencode($message) . "&type=" . $message_type);
            header("Connection: close");
            flush();
            exit();
        } else {
            echo '<script>window.location.href="products.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
            exit();
        }
    }
}

// Get message from URL (after redirect)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

// Get edit data if editing
$edit_data = null;
$edit_id = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if ($edit_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$edit_id]);
            $edit_data = $stmt->fetch();
            if (!$edit_data) {
                $message = "Produk tidak ditemukan!";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Error fetching edit data: " . $e->getMessage());
            $message = "Error memuat data produk!";
            $message_type = 'danger';
        }
    }
}

// Get all products with category and supplier info
try {
    $products = $conn->query("SELECT p.*, c.name as category_name, s.name as supplier_name
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             LEFT JOIN suppliers s ON p.supplier_id = s.id
                             ORDER BY p.name");
} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = null;
}

// Get categories for dropdown
try {
    $categories_stmt = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $categories_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get suppliers for dropdown
try {
    $suppliers_stmt = $conn->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name");
    $suppliers = $suppliers_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    $suppliers = [];
}

// End output buffering before output
if (!headers_sent()) {
    ob_end_flush();
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-box me-2"></i>Manajemen Produk</h2>
                <p class="mb-0">Kelola produk dan inventori coffee shop Anda</p>
            </div>
            <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="fas fa-plus me-2"></i>Tambah Produk Baru
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

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Stok Min</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products && $products->rowCount() > 0): ?>
                        <?php $no = 1; while ($product = $products->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                                    <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         class="img-thumbnail me-2" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center me-2" style="width: 50px; height: 50px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <?php if ($product['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($product['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                                    <button type="button" class="btn btn-action btn-info" 
                                            onclick="showProductDetail(<?= $product['id'] ?>)" 
                                            title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="products.php?edit=<?= $product['id'] ?>" 
                                       class="btn btn-action btn-edit" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="products.php?delete=<?= $product['id'] ?>" 
                                       class="btn btn-action btn-delete"
                                       onclick="return confirmDelete('Apakah Anda yakin ingin menghapus produk ini?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-box"></i>
                        <h4>Tidak Ada Produk</h4>
                        <p>Mulai dengan menambahkan produk pertama Anda</p>
                                    <button class="btn btn-coffee mt-3" data-bs-toggle="modal" data-bs-target="#productModal">
                                        <i class="fas fa-plus me-2"></i>Tambah Produk Pertama
                                    </button>
                                </div>
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
            <form method="POST" action="products.php" id="productForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-box me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Tambah' ?> Produk
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
                                <label for="name" class="form-label">Nama Produk *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Kategori *</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= (isset($edit_data) && $edit_data['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= isset($edit_data) ? htmlspecialchars($edit_data['description']) : '' ?></textarea>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label for="image" class="form-label">Gambar Produk</label>
                        <?php if (isset($edit_data) && !empty($edit_data['image'])): ?>
                        <div class="mb-2">
                            <img src="uploads/products/<?= htmlspecialchars($edit_data['image']) ?>" 
                                 alt="Current Image" 
                                 class="img-thumbnail" 
                                 style="max-width: 200px; max-height: 200px;">
                            <br>
                            <small class="text-muted">Gambar saat ini</small>
                            <input type="hidden" name="keep_image" value="1" id="keep_image">
                        </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif"
                               onchange="previewImage(this)">
                        <small class="text-muted">Maks 2MB. Format: JPG, PNG, GIF</small>
                        <?php if (isset($edit_data) && !empty($edit_data['image'])): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1" onchange="toggleKeepImage(this)">
                                    <label class="form-check-label" for="remove_image">
                                        Hapus gambar saat ini
                                    </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Harga *</label>
                                <input type="text" class="form-control" id="price" name="price" 
                                       value="<?= isset($edit_data) ? number_format($edit_data['price'], 0, ',', '.') : '' ?>" 
                                       placeholder="100.000" required
                                       oninput="formatPriceInput(this)">
                                <input type="hidden" id="price_hidden" name="price_hidden">
                                <small class="text-muted">Format: 100.000 (akan otomatis diformat)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       value="<?= isset($edit_data) ? $edit_data['stock'] : '0' ?>" 
                                       min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_stock" class="form-label">Stok Minimum</label>
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
                                    <option value="">Pilih Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" 
                                            <?= (isset($edit_data) && $edit_data['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-coffee" id="submitBtn">
                        <i class="fas fa-save me-2"></i><?= isset($edit_data) ? 'Perbarui' : 'Simpan' ?>
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

<!-- Product Detail Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Detail Produk
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Memuat detail produk...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="editProductLink" class="btn btn-coffee">
                    <i class="fas fa-edit me-2"></i>Edit Produk
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($edit_data) && $edit_data): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit to ensure modal HTML is ready
    setTimeout(function() {
        var productModalElement = document.getElementById('productModal');
        if (productModalElement) {
            var productModal = new bootstrap.Modal(productModalElement);
            productModal.show();
            
            // Reset form when modal is closed
            productModalElement.addEventListener('hidden.bs.modal', function() {
                // Clear edit data from URL
                if (window.location.search.includes('edit=')) {
                    const url = new URL(window.location);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }
            });
        }
    }, 100);
});
</script>
<?php endif; ?>

<script>
// Format price input (Indonesian format: 100.000)
function formatPriceInput(input) {
    // Remove all non-digit characters
    let value = input.value.replace(/[^\d]/g, '');
    
    // Format with thousand separators
    if (value) {
        const numValue = parseInt(value);
        input.value = numValue.toLocaleString('id-ID');
    } else {
        input.value = '';
    }
}

// Preview image before upload
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Remove old preview if exists
            let preview = document.getElementById('image-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.id = 'image-preview';
                preview.className = 'img-thumbnail mt-2';
                preview.style.maxWidth = '200px';
                preview.style.maxHeight = '200px';
                input.parentNode.appendChild(preview);
            }
            preview.src = e.target.result;
            preview.style.display = 'block';
            
            // Hide keep_image checkbox if new image selected
            const keepImage = document.getElementById('keep_image');
            if (keepImage) {
                keepImage.value = '0';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle keep image checkbox
function toggleKeepImage(checkbox) {
    const keepImage = document.getElementById('keep_image');
    const imageInput = document.getElementById('image');
    if (checkbox.checked) {
        if (keepImage) keepImage.value = '0';
        if (imageInput) imageInput.value = '';
        const preview = document.getElementById('image-preview');
        if (preview) preview.style.display = 'none';
    } else {
        if (keepImage) keepImage.value = '1';
    }
}

// Form submit handler - ensure form actually submits
document.addEventListener('DOMContentLoaded', function() {
    const productForm = document.getElementById('productForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (productForm && submitBtn) {
        // Format price on page load
        const priceInput = document.getElementById('price');
        if (priceInput && priceInput.value) {
            formatPriceInput(priceInput);
        }
        
        productForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Convert price format before submit (100.000 -> 100000)
            const priceInput = document.getElementById('price');
            if (priceInput) {
                const priceValue = priceInput.value.replace(/[^\d]/g, '');
                priceInput.value = priceValue; // Set raw value for submission
            }
            
            // Handle remove image
            const removeImage = document.getElementById('remove_image');
            if (removeImage && removeImage.checked) {
                const keepImage = document.getElementById('keep_image');
                if (keepImage) keepImage.value = '0';
            }
            
            // Validate required fields
            const requiredFields = productForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate price
            if (priceInput && (!priceInput.value || parseInt(priceInput.value.replace(/[^\d]/g, '')) <= 0)) {
                priceInput.classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate image if new product
            const imageInput = productForm.querySelector('input[name="image"]');
            const isEdit = productForm.querySelector('input[name="id"]') !== null;
            if (!isEdit && imageInput && imageInput.files.length > 0) {
                const file = imageInput.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    imageInput.classList.add('is-invalid');
                    showNotification('Format gambar tidak valid! Gunakan JPG, PNG, atau GIF.', 'danger');
                    return false;
                }
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    imageInput.classList.add('is-invalid');
                    showNotification('Ukuran gambar terlalu besar! Maksimal 2MB.', 'danger');
                    return false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                // Restore price format
                if (priceInput) formatPriceInput(priceInput);
                showNotification('Silakan isi semua field yang wajib dengan benar!', 'warning');
                // Scroll to first invalid field
                const firstInvalid = productForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
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
                // Restore price format
                if (priceInput) formatPriceInput(priceInput);
                showNotification('Pengiriman form terlalu lama. Silakan coba lagi atau refresh halaman.', 'warning', 5000);
            }, 10000); // 10 seconds timeout
            
            // Store timeout ID for cleanup
            window.productFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.productFormTimeout) {
                    clearTimeout(window.productFormTimeout);
                }
            });
            
            // Allow form to submit - the redirect will happen on server side
            return true;
        });
        
        // Reset button state on page load (in case of error or page reload)
        submitBtn.disabled = false;
        const isEdit = productForm.querySelector('input[name="id"]') !== null;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>' + (isEdit ? 'Perbarui' : 'Simpan');
    }
});

// Show product detail
function showProductDetail(productId) {
    // Show loading
    document.getElementById("productDetailContent").innerHTML = 
        "<div class='text-center py-4'><div class='spinner-border text-primary'></div><p class='mt-3'>Memuat detail produk...</p></div>";
    
    var modal = new bootstrap.Modal(document.getElementById("productDetailModal"));
    modal.show();
    
    // Fetch product details via AJAX
    fetch('products.php?action=get_product_detail&id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="row">
                        <div class="col-md-5 text-center mb-4">
                            ${data.product.image ? 
                                `<img src="uploads/products/${data.product.image}" alt="${data.product.name}" class="img-fluid rounded shadow" style="max-height: 400px;">` :
                                `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 300px;">
                                    <i class="fas fa-image fa-4x text-muted"></i>
                                </div>`
                            }
                        </div>
                        <div class="col-md-7">
                            <h4 class="mb-3" style="color: #6F4E37;">${data.product.name}</h4>
                            
                            <div class="mb-3">
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong><i class="fas fa-tag me-2"></i>Kategori:</strong></td>
                                        <td><span class="badge bg-primary">${data.product.category_name || '-'}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-dollar-sign me-2"></i>Harga:</strong></td>
                                        <td><span class="text-success fw-bold">Rp ${parseInt(data.product.price).toLocaleString('id-ID')}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-box me-2"></i>Stok:</strong></td>
                                        <td>
                                            <span class="badge bg-${data.product.stock <= data.product.min_stock ? 'warning' : 'success'} fs-6">
                                                ${data.product.stock} unit
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-exclamation-triangle me-2"></i>Stok Minimum:</strong></td>
                                        <td>${data.product.min_stock} unit</td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-truck me-2"></i>Supplier:</strong></td>
                                        <td>${data.product.supplier_name || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-info-circle me-2"></i>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-${data.product.status == 'active' ? 'success' : 'secondary'}">
                                                ${data.product.status == 'active' ? 'Aktif' : 'Tidak Aktif'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-calendar me-2"></i>Dibuat:</strong></td>
                                        <td>${data.product.created_at}</td>
                                    </tr>
                                    <tr>
                                        <td><strong><i class="fas fa-edit me-2"></i>Diperbarui:</strong></td>
                                        <td>${data.product.updated_at}</td>
                                    </tr>
                                </table>
                            </div>
                            
                            ${data.product.description ? `
                            <div class="mb-3">
                                <strong><i class="fas fa-align-left me-2"></i>Deskripsi:</strong>
                                <p class="mt-2 text-muted">${data.product.description}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                document.getElementById("productDetailContent").innerHTML = html;
                document.getElementById("editProductLink").href = "products.php?edit=" + productId;
            } else {
                document.getElementById("productDetailContent").innerHTML = 
                    "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>" + (data.message || 'Gagal memuat detail produk') + "</div>";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById("productDetailContent").innerHTML = 
                "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>Error memuat detail produk. Silakan refresh halaman.</div>";
        });
}
</script>

<?php include 'includes/footer.php'; ?>
