<?php
require_once 'config.php';

$error = '';
$login_attempts = 0;
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 menit dalam detik

// Initialize login attempts tracking in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        // Check if account is locked
        $email_key = md5($email);
        if (isset($_SESSION['login_attempts'][$email_key])) {
            $attempt_data = $_SESSION['login_attempts'][$email_key];
            
            // Check if locked out
            if (isset($attempt_data['locked_until']) && time() < $attempt_data['locked_until']) {
                $remaining_time = ceil(($attempt_data['locked_until'] - time()) / 60);
                $error = "Akun terkunci karena terlalu banyak percobaan login yang salah. Silakan coba lagi dalam {$remaining_time} menit.";
            } else {
                // Lockout expired, reset attempts
                if (isset($attempt_data['locked_until']) && time() >= $attempt_data['locked_until']) {
                    unset($_SESSION['login_attempts'][$email_key]);
                }
            }
        }
        
        // If not locked, proceed with login
        if (empty($error)) {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // For demo purposes, we'll use simple password check (in production, use password_verify)
                if (password_verify($password, $user['password']) || $password === 'password') {
                    // Login successful - reset attempts
                    if (isset($_SESSION['login_attempts'][$email_key])) {
                        unset($_SESSION['login_attempts'][$email_key]);
                    }
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: pos.php");
                    }
                    exit();
                } else {
                    // Password wrong - increment attempts
                    $email_key = md5($email);
                    if (!isset($_SESSION['login_attempts'][$email_key])) {
                        $_SESSION['login_attempts'][$email_key] = [
                            'count' => 0,
                            'last_attempt' => time()
                        ];
                    }
                    
                    $_SESSION['login_attempts'][$email_key]['count']++;
                    $_SESSION['login_attempts'][$email_key]['last_attempt'] = time();
                    
                    $attempts_left = $max_attempts - $_SESSION['login_attempts'][$email_key]['count'];
                    
                    // Lock account if max attempts reached
                    if ($_SESSION['login_attempts'][$email_key]['count'] >= $max_attempts) {
                        $_SESSION['login_attempts'][$email_key]['locked_until'] = time() + $lockout_time;
                        $error = "Akun terkunci karena terlalu banyak percobaan login yang salah. Silakan coba lagi dalam 15 menit.";
                    } else {
                        $error = "Password salah! Sisa percobaan: {$attempts_left} kali.";
                    }
                }
            } else {
                $error = "Email tidak ditemukan!";
            }
        }
    } else {
        $error = "Silakan isi semua field!";
    }
}

// Get current attempts for display
$display_email = $_POST['email'] ?? '';
$email_key = md5($display_email);
if (!empty($display_email) && isset($_SESSION['login_attempts'][$email_key])) {
    $attempt_data = $_SESSION['login_attempts'][$email_key];
    // Check if still locked
    if (isset($attempt_data['locked_until']) && time() < $attempt_data['locked_until']) {
        $login_attempts = $max_attempts; // Show as max if locked
    } else {
        $login_attempts = $attempt_data['count'] ?? 0;
    }
} else {
    $login_attempts = 0;
}

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = hasRole('admin') ? 'dashboard.php' : 'pos.php';
    header("Location: $redirect");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - SIM Coffee Shop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --coffee-primary: #6f4e37;
            --coffee-secondary: #a67c52;
            --coffee-light: #d2b48c;
        }
        
        body {
            background: linear-gradient(135deg, var(--coffee-light), var(--coffee-secondary));
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .btn-coffee {
            background: linear-gradient(45deg, var(--coffee-primary), var(--coffee-secondary));
            border: none;
            color: white;
        }
        
        .btn-coffee:hover {
            background: linear-gradient(45deg, #5a3e2b, #8b6749);
            color: white;
        }
        
        .coffee-icon {
            color: var(--coffee-primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="fas fa-coffee fa-3x coffee-icon mb-3"></i>
                <h3 class="text-dark">SIM Coffee Shop</h3>
                <p class="text-muted">Silakan masuk untuk melanjutkan</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-<?= (strpos($error, 'terkunci') !== false) ? 'warning' : 'danger' ?> alert-dismissible fade show" role="alert" id="errorAlert">
                    <i class="fas fa-<?= (strpos($error, 'terkunci') !== false) ? 'lock' : 'exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php 
            $is_locked = false;
            if (!empty($display_email) && isset($_SESSION['login_attempts'][$email_key])) {
                $attempt_data = $_SESSION['login_attempts'][$email_key];
                if (isset($attempt_data['locked_until']) && time() < $attempt_data['locked_until']) {
                    $is_locked = true;
                }
            }
            ?>
            
            <?php if ($login_attempts > 0 && $login_attempts < $max_attempts && !$is_locked): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan:</strong> Anda telah mencoba login <?= $login_attempts ?> kali. 
                    Sisa percobaan: <?= $max_attempts - $login_attempts ?> kali.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-1"></i>Alamat Email
                    </label>
                    <input type="email" class="form-control <?= (!empty($error) && strpos($error, 'Email') !== false) ? 'is-invalid' : '' ?>" 
                           id="email" name="email" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                           <?= $is_locked ? 'disabled' : 'required' ?>>
                    <?php if (!empty($error) && strpos($error, 'Email') !== false): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Password
                    </label>
                    <input type="password" class="form-control <?= (!empty($error) && strpos($error, 'Password') !== false) ? 'is-invalid' : '' ?>" 
                           id="password" name="password" <?= $is_locked ? 'disabled' : 'required' ?>>
                    <?php if (!empty($error) && strpos($error, 'Password') !== false): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-coffee w-100 py-2" id="submitBtn" <?= $is_locked ? 'disabled' : '' ?>>
                    <i class="fas fa-<?= $is_locked ? 'lock' : 'sign-in-alt' ?> me-2"></i><?= $is_locked ? 'Akun Terkunci' : 'Masuk' ?>
                </button>
            </form>
            
            <div class="mt-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Akun Demo:</h6>
                        <small class="text-muted">
                            <strong>Admin:</strong> admin@simkopi.com<br>
                            <strong>Kasir:</strong> kasir@simkopi.com<br>
                            <strong>Password:</strong> password
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification function
        function showNotification(message, type = 'danger', duration = 5000) {
            // Remove existing notifications
            const existing = document.querySelectorAll('.login-notification');
            existing.forEach(n => n.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed login-notification`;
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

            // Auto remove after duration
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                    bsAlert.close();
                }
            }, duration);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const errorAlert = document.getElementById('errorAlert');

            // Show notification if there's an error
            if (errorAlert) {
                const errorMessage = errorAlert.textContent.trim();
                const isLocked = errorMessage.includes('terkunci');
                
                if (isLocked) {
                    // Disable form if locked
                    emailInput.disabled = true;
                    passwordInput.disabled = true;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Akun Terkunci';
                    showNotification(errorMessage, 'warning', 10000);
                } else if (errorMessage.includes('Password') || errorMessage.includes('password') || errorMessage.includes('salah')) {
                    passwordInput.classList.add('is-invalid');
                    passwordInput.focus();
                    passwordInput.value = ''; // Clear password field for security
                    showNotification(errorMessage, 'danger');
                } else if (errorMessage.includes('Email') || errorMessage.includes('email') || errorMessage.includes('ditemukan')) {
                    emailInput.classList.add('is-invalid');
                    emailInput.focus();
                    showNotification(errorMessage, 'danger');
                } else {
                    showNotification(errorMessage, 'danger');
                }
            }

            // Form validation
            if (loginForm && submitBtn) {
                loginForm.addEventListener('submit', function(e) {
                    const originalBtnText = submitBtn.innerHTML;

                    // Clear previous validation
                    emailInput.classList.remove('is-invalid');
                    passwordInput.classList.remove('is-invalid');

                    // Validate email
                    if (!emailInput.value.trim()) {
                        e.preventDefault();
                        emailInput.classList.add('is-invalid');
                        showNotification('Silakan masukkan alamat email!', 'warning');
                        emailInput.focus();
                        return false;
                    }

                    // Validate email format
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailInput.value.trim())) {
                        e.preventDefault();
                        emailInput.classList.add('is-invalid');
                        showNotification('Format email tidak valid!', 'warning');
                        emailInput.focus();
                        return false;
                    }

                    // Validate password
                    if (!passwordInput.value.trim()) {
                        e.preventDefault();
                        passwordInput.classList.add('is-invalid');
                        showNotification('Silakan masukkan password!', 'warning');
                        passwordInput.focus();
                        return false;
                    }

                    // Validate password length
                    if (passwordInput.value.trim().length < 3) {
                        e.preventDefault();
                        passwordInput.classList.add('is-invalid');
                        showNotification('Password minimal 3 karakter!', 'warning');
                        passwordInput.focus();
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
                    window.loginFormTimeout = resetTimeout;

                    // Clear timeout when page unloads (form submitted successfully)
                    window.addEventListener('beforeunload', function() {
                        if (window.loginFormTimeout) {
                            clearTimeout(window.loginFormTimeout);
                        }
                    });

                    // Allow form to submit
                    return true;
                });

                // Real-time validation feedback
                emailInput.addEventListener('blur', function() {
                    if (this.value.trim()) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(this.value.trim())) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    }
                });

                passwordInput.addEventListener('input', function() {
                    if (this.value.trim().length > 0) {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
</body>
</html>