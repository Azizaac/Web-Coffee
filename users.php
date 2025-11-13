<?php
// Start output buffering
ob_start();

$page_title = "Manajemen Pengguna";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();

$message = '';
$message_type = 'success';

// Handle POST requests (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validation
        if (empty($name) || empty($email) || empty($role)) {
            $message = "Silakan isi semua field yang wajib!";
            $message_type = 'danger';
        } elseif ($action == 'add' && empty($password)) {
            $message = "Password wajib untuk pengguna baru!";
            $message_type = 'danger';
        } else {
            try {
                if ($action == 'add') {
                    // Check if email already exists
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Email sudah terdaftar!";
                        $message_type = 'danger';
                    } else {
                        // Add new user
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                        $result = $stmt->execute([$name, $email, $hashed_password, $role]);
                        
                        if ($result) {
                            $new_id = $conn->lastInsertId();
                            if ($new_id > 0) {
                                $message = "Pengguna berhasil ditambahkan!";
                            } else {
                                $message = "Gagal menambahkan pengguna!";
                                $message_type = 'danger';
                            }
                        } else {
                            $message = "Gagal menambahkan pengguna!";
                            $message_type = 'danger';
                        }
                    }
                } elseif ($action == 'edit') {
                    // Edit user
                    $id = intval($_POST['id'] ?? 0);
                    
                    if ($id > 0) {
                        // Check if email already exists (excluding current user)
                        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $check_stmt->execute([$email, $id]);
                        
                        if ($check_stmt->rowCount() > 0) {
                            $message = "Email sudah terdaftar!";
                            $message_type = 'danger';
                        } else {
                            if (!empty($password)) {
                                // Update with new password
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$name, $email, $hashed_password, $role, $id]);
                            } else {
                                // Update without password
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$name, $email, $role, $id]);
                            }
                            
                            if ($stmt->rowCount() > 0) {
                                $message = "Pengguna berhasil diperbarui!";
                            } else {
                                $message = "Tidak ada perubahan!";
                                $message_type = 'warning';
                            }
                        }
                    } else {
                        $message = "ID pengguna tidak valid!";
                        $message_type = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = 'danger';
                error_log("User Error: " . $e->getMessage());
            }
        }
    }
    
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: users.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        echo '<script>window.location.href="users.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Handle GET requests (Delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id > 0 && $id != $current_user['id']) {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Pengguna berhasil dihapus!";
            } else {
                $message = "Pengguna tidak ditemukan!";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Delete User Error: " . $e->getMessage());
        }
        
        // Clear ALL output buffers and redirect IMMEDIATELY
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Force redirect - no output allowed
        if (!headers_sent()) {
            header("Location: users.php?msg=" . urlencode($message) . "&type=" . $message_type);
            header("Connection: close");
            flush();
            exit();
        } else {
            echo '<script>window.location.href="users.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
            exit();
        }
    } else {
        $message = "Tidak dapat menghapus akun Anda sendiri!";
        $message_type = 'danger';
        
        // Clear ALL output buffers and redirect IMMEDIATELY
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Force redirect - no output allowed
        if (!headers_sent()) {
            header("Location: users.php?msg=" . urlencode($message) . "&type=" . $message_type);
            header("Connection: close");
            flush();
            exit();
        } else {
            echo '<script>window.location.href="users.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
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
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY name");

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
                <h2><i class="fas fa-users me-2"></i>Manajemen Pengguna</h2>
                <p class="mb-0">Kelola pengguna sistem dan izin akses</p>
            </div>
            <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="fas fa-plus me-2"></i>Tambah Pengguna Baru
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
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->rowCount() > 0): ?>
                        <?php $no = 1; while ($user = $users->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                <?php if ($user['id'] == $current_user['id']): ?>
                                <span class="badge bg-primary ms-2">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'info' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $user['id'] ?>" class="btn btn-action btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $current_user['id']): ?>
                                    <a href="?delete=<?= $user['id'] ?>" 
                                       class="btn btn-action btn-delete"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')"
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
                                    <i class="fas fa-users"></i>
                                    <h4>Tidak Ada Pengguna</h4>
                                    <p>Tambahkan pengguna untuk mengelola coffee shop Anda</p>
                                    <button class="btn btn-coffee mt-3" data-bs-toggle="modal" data-bs-target="#userModal">
                                        <i class="fas fa-plus me-2"></i>Tambah Pengguna Pertama
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

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="users.php" id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Tambah' ?> Pengguna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= isset($edit_data) ? htmlspecialchars($edit_data['email']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password <?= isset($edit_data) ? '(kosongkan untuk tetap menggunakan yang sekarang)' : '*' ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               <?= !isset($edit_data) ? 'required' : '' ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Peran *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Pilih Peran</option>
                            <option value="admin" <?= (isset($edit_data) && $edit_data['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="kasir" <?= (isset($edit_data) && $edit_data['role'] == 'kasir') ? 'selected' : '' ?>>Kasir</option>
                        </select>
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
    var userModal = new bootstrap.Modal(document.getElementById('userModal'));
    userModal.show();
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
    const userForm = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (userForm && submitBtn) {
        userForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate required fields
            const requiredFields = userForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check password requirement for new users
            const action = userForm.querySelector('input[name="action"]').value;
            const passwordField = userForm.querySelector('input[name="password"]');
            if (action === 'add' && (!passwordField || !passwordField.value.trim())) {
                passwordField.classList.add('is-invalid');
                isValid = false;
                showNotification('Password wajib diisi untuk pengguna baru!', 'warning');
            }
            
            // Validate email format
            const emailField = userForm.querySelector('input[name="email"]');
            if (emailField && emailField.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value.trim())) {
                    emailField.classList.add('is-invalid');
                    isValid = false;
                    showNotification('Format email tidak valid!', 'danger');
                }
            }
            
            // Validate password length if provided
            if (passwordField && passwordField.value.trim() && passwordField.value.trim().length < 6) {
                passwordField.classList.add('is-invalid');
                isValid = false;
                showNotification('Password minimal 6 karakter!', 'warning');
            }
            
            // Validate name length
            const nameField = userForm.querySelector('input[name="name"]');
            if (nameField && nameField.value.trim().length < 2) {
                nameField.classList.add('is-invalid');
                isValid = false;
                showNotification('Nama minimal 2 karakter!', 'warning');
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = userForm.querySelector('.is-invalid');
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
                showNotification('Pengiriman form terlalu lama. Silakan coba lagi atau refresh halaman.', 'warning', 5000);
            }, 10000); // 10 seconds timeout
            
            // Store timeout ID for cleanup
            window.userFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.userFormTimeout) {
                    clearTimeout(window.userFormTimeout);
                }
            });
            
            // Allow form to submit - the redirect will happen on server side
            return true;
        });
        
        // Reset button state on page load (in case of error or page reload)
        submitBtn.disabled = false;
        const isEdit = userForm.querySelector('input[name="id"]') !== null;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>' + (isEdit ? 'Perbarui' : 'Simpan');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
