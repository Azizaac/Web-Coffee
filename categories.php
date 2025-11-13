<?php
// Start output buffering
ob_start();

$page_title = "Manajemen Kategori";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle POST requests (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate CSRF token
    $csrf_valid = true;
    if (isset($_POST['csrf_token'])) {
        $csrf_valid = validateCSRFToken($_POST['csrf_token']);
    }
    
    if (!$csrf_valid) {
        $message = "Invalid CSRF token!";
        $message_type = 'danger';
    } elseif (empty($name)) {
            $message = "Nama kategori wajib diisi!";
        $message_type = 'danger';
    } else {
        try {
            if ($action == 'add') {
                // Add new category
                $stmt = $conn->prepare("INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $result = $stmt->execute([$name, $description]);
                
                if ($result) {
                    $new_id = $conn->lastInsertId();
                    if ($new_id > 0) {
                        $message = "Kategori berhasil ditambahkan!";
                    } else {
                        $message = "Gagal menambahkan kategori!";
                        $message_type = 'danger';
                    }
                } else {
                    $message = "Gagal menambahkan kategori!";
                    $message_type = 'danger';
                }
            } elseif ($action == 'edit') {
                // Edit category
                $id = intval($_POST['id'] ?? 0);
                
                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Kategori berhasil diperbarui!";
                    } else {
                        $message = "Tidak ada perubahan!";
                        $message_type = 'warning';
                    }
                } else {
                    $message = "ID kategori tidak valid!";
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Category Error: " . $e->getMessage());
        }
    }
    
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: categories.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        // If headers already sent, use JavaScript redirect
        echo '<script>window.location.href="categories.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Handle GET requests (Delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id > 0) {
        try {
            // Check if category is used by products
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $check_stmt->execute([$id]);
            $check_data = $check_stmt->fetch();
            
            if ($check_data['count'] > 0) {
                $message = "Tidak dapat menghapus kategori! Sedang digunakan oleh " . $check_data['count'] . " produk.";
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Kategori berhasil dihapus!";
                } else {
                    $message = "Kategori tidak ditemukan!";
                    $message_type = 'warning';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Delete Category Error: " . $e->getMessage());
        }
        
        // Clear ALL output buffers and redirect IMMEDIATELY
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Force redirect - no output allowed
        if (!headers_sent()) {
            header("Location: categories.php?msg=" . urlencode($message) . "&type=" . $message_type);
            header("Connection: close");
            flush();
            exit();
        } else {
            echo '<script>window.location.href="categories.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
            exit();
        }
    }
}

// Get message from URL (after redirect)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

// Get edit data if editing
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if ($edit_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Get all categories
$categories = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                          FROM categories c 
                          LEFT JOIN products p ON c.id = p.category_id 
                          GROUP BY c.id 
                          ORDER BY c.name");

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
                <h2><i class="fas fa-tags me-2"></i>Manajemen Kategori</h2>
                <p class="mb-0">Atur produk Anda ke dalam kategori</p>
            </div>
            <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="fas fa-plus me-2"></i>Tambah Kategori Baru
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
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Produk</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories->rowCount() > 0): ?>
                        <?php $no = 1; while ($category = $categories->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                            <td><?= htmlspecialchars($category['description'] ?: '-') ?></td>
                            <td><span class="badge bg-info"><?= $category['product_count'] ?> produk</span></td>
                            <td><small><?= date('M j, Y', strtotime($category['created_at'])) ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $category['id'] ?>" class="btn btn-action btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($category['product_count'] == 0): ?>
                                    <a href="?delete=<?= $category['id'] ?>" 
                                       class="btn btn-action btn-delete"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-tags"></i>
                        <h4>Tidak Ada Kategori</h4>
                        <p>Buat kategori untuk mengatur produk Anda</p>
                                    <button class="btn btn-coffee mt-3" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="fas fa-plus me-2"></i>Tambah Kategori Pertama
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

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="categories.php" id="categoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Tambah' ?> Kategori
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kategori *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= isset($edit_data) ? htmlspecialchars($edit_data['description']) : '' ?></textarea>
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

<?php if (isset($edit_data)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    categoryModal.show();
});
</script>
<?php endif; ?>

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

// Form submit handler - ensure form actually submits
document.addEventListener('DOMContentLoaded', function() {
    const categoryForm = document.getElementById('categoryForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (categoryForm && submitBtn) {
        categoryForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate required fields
            const requiredFields = categoryForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate name length
            const nameField = categoryForm.querySelector('input[name="name"]');
            if (nameField && nameField.value.trim().length < 2) {
                nameField.classList.add('is-invalid');
                isValid = false;
                showNotification('Nama kategori minimal 2 karakter!', 'warning');
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = categoryForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                if (!nameField || nameField.value.trim().length >= 2) {
                    showNotification('Silakan isi semua field yang wajib!', 'warning');
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
            window.categoryFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.categoryFormTimeout) {
                    clearTimeout(window.categoryFormTimeout);
                }
            });
            
            // Allow form to submit - the redirect will happen on server side
            return true;
        });
        
        // Reset button state on page load (in case of error or page reload)
        submitBtn.disabled = false;
        const isEdit = categoryForm.querySelector('input[name="id"]') !== null;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>' + (isEdit ? 'Perbarui' : 'Simpan');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
