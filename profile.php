<?php
// Start output buffering
ob_start();

$page_title = "Profil Saya";
require_once 'config.php';
requireLogin();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();

$message = '';
$message_type = 'success';

// Handle POST requests (Update Profile)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($name) || empty($email)) {
        $message = "Nama dan email wajib diisi!";
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid!";
        $message_type = 'danger';
    } elseif (!empty($password) && $password !== $password_confirm) {
        $message = "Konfirmasi password tidak cocok!";
        $message_type = 'danger';
    } elseif (!empty($password) && strlen($password) < 6) {
        $message = "Password minimal 6 karakter!";
        $message_type = 'danger';
    } else {
        try {
            // Check if email already exists (excluding current user)
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$email, $current_user['id']]);
            
            if ($check_stmt->rowCount() > 0) {
                $message = "Email sudah terdaftar!";
                $message_type = 'danger';
            } else {
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $address, $hashed_password, $current_user['id']]);
                } else {
                    // Update without password
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $address, $current_user['id']]);
                }
                
                if ($stmt->rowCount() > 0) {
                    // Update session
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    $message = "Profil berhasil diperbarui!";
                } else {
                    $message = "Tidak ada perubahan!";
                    $message_type = 'warning';
                }
            }
        } catch (PDOException $e) {
            $message = "Error database: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Profile Update Error: " . $e->getMessage());
        }
    }
    
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: profile.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        echo '<script>window.location.href="profile.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Get message from URL (after redirect)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

// Get current user data
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$current_user['id']]);
$user_data = $user_stmt->fetch();

// Get user statistics
$stats = [];
$stats_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales WHERE user_id = ? AND status = 'completed'");
$stats_stmt->execute([$current_user['id']]);
$stats['total_sales'] = $stats_stmt->fetch()['total'] ?? 0;

$stats_stmt = $conn->prepare("SELECT COALESCE(SUM(final_amount), 0) as total FROM sales WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()");
$stats_stmt->execute([$current_user['id']]);
$stats['today_revenue'] = $stats_stmt->fetch()['total'] ?? 0;

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
                <h2><i class="fas fa-user-circle me-2"></i>Profil Saya</h2>
                <p class="mb-0">Kelola informasi akun dan pengaturan Anda</p>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Information -->
        <div class="col-md-8">
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-user me-2"></i>Informasi Profil
                    </h5>
                </div>
                
                <form method="POST" action="profile.php" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Lengkap *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($user_data['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Alamat Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($user_data['email']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>"
                                       placeholder="081234567890">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Peran</label>
                                <input type="text" class="form-control" id="role" 
                                       value="<?= ucfirst($user_data['role']) ?>" readonly>
                                <small class="text-muted">Peran tidak dapat diubah</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="3" 
                                  placeholder="Alamat Anda..."><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Ubah Password</h6>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Biarkan field password kosong jika Anda tidak ingin mengubah password.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password Baru</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Kosongkan untuk tetap menggunakan password saat ini"
                                       minlength="6">
                                <small class="text-muted">Minimal 6 karakter</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                       placeholder="Konfirmasi password baru">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Batal
                        </a>
                        <button type="submit" class="btn btn-coffee" id="submitBtn">
                            <i class="fas fa-save me-2"></i>Perbarui Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics & Info -->
        <div class="col-md-4">
            <!-- Account Info -->
            <div class="table-container mb-4">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-info-circle me-2"></i>Informasi Akun
                    </h5>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between">
                        <span><strong>Anggota Sejak:</strong></span>
                        <span><?= date('M j, Y', strtotime($user_data['created_at'])) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><strong>Terakhir Diperbarui:</strong></span>
                        <span><?= date('M j, Y H:i', strtotime($user_data['updated_at'])) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><strong>Status:</strong></span>
                        <span class="badge bg-<?= $user_data['status'] == 'active' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($user_data['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <?php if ($current_user['role'] == 'kasir' || $current_user['role'] == 'admin'): ?>
            <div class="table-container">
                <div class="section-header">
                    <h5>
                        <i class="fas fa-chart-bar me-2"></i>Statistik Saya
                    </h5>
                </div>
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-receipt fa-3x mb-3 text-primary"></i>
                        <h4><?= number_format($stats['total_sales']) ?></h4>
                        <p class="text-muted mb-0">Total Penjualan</p>
                    </div>
                    <div class="mb-4">
                        <i class="fas fa-money-bill-wave fa-3x mb-3 text-success"></i>
                        <h4><?= formatCurrency($stats['today_revenue']) ?></h4>
                        <p class="text-muted mb-0">Pendapatan Hari Ini</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Form submit handler
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (profileForm && submitBtn) {
        profileForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate required fields
            const requiredFields = profileForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate email format
            const emailField = profileForm.querySelector('input[name="email"]');
            if (emailField && !emailField.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                emailField.classList.add('is-invalid');
                isValid = false;
            }
            
            // Validate password if provided
            const passwordField = profileForm.querySelector('input[name="password"]');
            const passwordConfirmField = profileForm.querySelector('input[name="password_confirm"]');
            
            if (passwordField && passwordField.value) {
                if (passwordField.value.length < 6) {
                    passwordField.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (passwordField.value !== passwordConfirmField.value) {
                    passwordConfirmField.classList.add('is-invalid');
                    isValid = false;
                    alert('Konfirmasi password tidak cocok!');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Silakan isi semua field yang wajib dengan benar!');
                return false;
            }
            
            // Set button to processing state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            submitBtn.disabled = true;
            
            // Set timeout to reset button if form doesn't submit (failsafe)
            let resetTimeout = setTimeout(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                alert('Pengiriman form terlalu lama. Silakan coba lagi atau refresh halaman.');
            }, 10000);
            
            // Store timeout ID for cleanup
            window.profileFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.profileFormTimeout) {
                    clearTimeout(window.profileFormTimeout);
                }
            });
            
            // Allow form to submit
            return true;
        });
        
        // Reset button state on page load
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Perbarui Profil';
    }
});
</script>

<?php include 'includes/footer.php'; ?>

