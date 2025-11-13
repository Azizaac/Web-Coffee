<?php
$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>SIM Coffee Shop</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --coffee-primary: #6F4E37;
            --coffee-secondary: #A67C52;
            --coffee-light: #D2B48C;
            --coffee-dark: #4A2C1A;
            --coffee-accent: #CD853F;
            --coffee-cream: #F5E6D3;
            --coffee-success: #2D5016;
            --coffee-warning: #D4A574;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #faf8f3 0%, #f5ede0 50%, #f0e6d2 100%);
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            color: #3d2817;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--coffee-primary) 0%, var(--coffee-dark) 100%) !important;
            box-shadow: 0 4px 20px rgba(111, 78, 55, 0.3);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.6rem;
            color: white !important;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            margin: 0 3px;
            padding: 8px 16px !important;
            position: relative;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }
        
        .nav-link:hover::before {
            width: 80%;
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            color: white !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .nav-link.active::before {
            width: 80%;
        }
        
        .btn-coffee {
            background: linear-gradient(135deg, var(--coffee-primary) 0%, var(--coffee-secondary) 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(111, 78, 55, 0.3);
            letter-spacing: 0.3px;
        }
        
        .btn-coffee:hover {
            background: linear-gradient(135deg, var(--coffee-dark) 0%, var(--coffee-primary) 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(111, 78, 55, 0.4);
        }
        
        .btn-coffee:active {
            transform: translateY(-1px);
        }
        
        .btn-outline-coffee {
            border: 2px solid var(--coffee-primary);
            color: var(--coffee-primary);
            font-weight: 600;
            border-radius: 12px;
            padding: 10px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
        }
        
        .btn-outline-coffee:hover {
            background: linear-gradient(135deg, var(--coffee-primary) 0%, var(--coffee-secondary) 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(111, 78, 55, 0.3);
            border-color: transparent;
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(111, 78, 55, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(210, 180, 140, 0.3);
            transition: all 0.3s ease;
        }
        
        .table-container:hover {
            box-shadow: 0 12px 40px rgba(111, 78, 55, 0.12);
        }
        
        .dashboard-stats {
            background: linear-gradient(135deg, #ffffff 0%, var(--coffee-cream) 100%);
            border: none;
            border-radius: 20px;
            padding: 2rem 1.5rem;
            box-shadow: 0 8px 24px rgba(111, 78, 55, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(210, 180, 140, 0.2);
        }
        
        .dashboard-stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--coffee-primary), var(--coffee-secondary), var(--coffee-accent));
        }
        
        .dashboard-stats::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transition: all 0.5s ease;
        }
        
        .dashboard-stats:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 48px rgba(111, 78, 55, 0.2);
        }
        
        .dashboard-stats:hover::after {
            top: -30%;
            right: -30%;
        }
        
        .dashboard-stats i {
            color: var(--coffee-primary);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .dashboard-stats:hover i {
            transform: scale(1.1) rotate(5deg);
        }
        
        .dashboard-stats h4 {
            color: var(--coffee-dark);
            font-weight: 700;
            font-size: 2rem;
            margin: 0.5rem 0;
        }
        
        .dashboard-stats p {
            color: #6c757d;
            font-weight: 500;
            margin: 0;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--coffee-cream) 0%, #f0e6d2 100%);
            color: var(--coffee-dark);
            font-weight: 700;
            border: none;
            padding: 1.2rem 1rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            position: relative;
        }
        
        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--coffee-primary), transparent);
        }
        
        .table tbody tr {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid rgba(210, 180, 140, 0.2);
        }
        
        .table tbody tr:hover {
            background: linear-gradient(90deg, rgba(111, 78, 55, 0.03) 0%, rgba(166, 124, 82, 0.05) 100%);
            transform: translateX(5px);
            box-shadow: -4px 0 0 var(--coffee-primary);
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        .product-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            border: 1px solid rgba(139, 69, 19, 0.1);
            background: linear-gradient(135deg, white 0%, #fefcf8 100%);
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(139, 69, 19, 0.2);
            border-color: var(--coffee-primary);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 24px 64px rgba(111, 78, 55, 0.25);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--coffee-cream) 0%, #f0e6d2 100%);
            border-radius: 24px 24px 0 0;
            border-bottom: 2px solid rgba(111, 78, 55, 0.1);
            padding: 1.5rem 2rem;
        }
        
        .modal-title {
            font-weight: 700;
            color: var(--coffee-dark);
            font-size: 1.3rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(210, 180, 140, 0.3);
            padding: 1.5rem 2rem;
            background: #fafafa;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid rgba(210, 180, 140, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 12px 16px;
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--coffee-primary);
            box-shadow: 0 0 0 0.25rem rgba(111, 78, 55, 0.15);
            outline: none;
            background: #fffefb;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--coffee-dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .badge {
            border-radius: 20px;
            font-weight: 600;
            padding: 6px 12px;
            font-size: 0.8rem;
            letter-spacing: 0.3px;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--coffee-primary), var(--coffee-dark));
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 15px rgba(139, 69, 19, 0.2);
        }
        
        .coffee-icon {
            color: var(--coffee-primary);
        }
        
        /* Page Header Styles */
        .page-header {
            background: linear-gradient(135deg, white 0%, var(--coffee-cream) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(111, 78, 55, 0.08);
            border: 1px solid rgba(210, 180, 140, 0.3);
        }
        
        .page-header h2 {
            color: var(--coffee-dark);
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
        }
        
        .page-header p {
            color: #6c757d;
            margin: 0.5rem 0 0 0;
        }
        
        /* Alert Modern Styles */
        .alert {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        /* Card Modern Styles */
        .modern-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(111, 78, 55, 0.08);
            padding: 2rem;
            border: 1px solid rgba(210, 180, 140, 0.3);
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            box-shadow: 0 12px 40px rgba(111, 78, 55, 0.12);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--coffee-light);
            margin-bottom: 1.5rem;
        }
        
        .empty-state h4 {
            color: var(--coffee-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        
        .dropdown-menu {
            border-radius: 16px;
            border: 1px solid rgba(210, 180, 140, 0.3);
            box-shadow: 0 12px 40px rgba(111, 78, 55, 0.15);
            padding: 8px;
            margin-top: 8px;
        }
        
        .dropdown-item {
            padding: 12px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            margin: 2px 0;
            font-weight: 500;
            color: var(--coffee-dark);
        }
        
        .dropdown-item:hover {
            background: linear-gradient(90deg, var(--coffee-cream), #f0e6d2);
            color: var(--coffee-dark);
            transform: translateX(5px);
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .table-container {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .dashboard-stats {
                padding: 1rem;
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--coffee-primary), var(--coffee-secondary));
            border-radius: 10px;
            border: 2px solid #f5f5f5;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--coffee-dark), var(--coffee-primary));
        }
        
        /* Action Buttons */
        .btn-action {
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-action.btn-edit {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .btn-action.btn-edit:hover {
            background: #0d6efd;
            color: white;
            transform: scale(1.05);
        }
        
        .btn-action.btn-delete {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .btn-action.btn-delete:hover {
            background: #dc3545;
            color: white;
            transform: scale(1.05);
        }
        
        .btn-action.btn-info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }
        
        .btn-action.btn-info:hover {
            background: #17a2b8;
            color: white;
            transform: scale(1.05);
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(210, 180, 140, 0.3);
        }
        
        .section-header h2, .section-header h4, .section-header h5 {
            color: var(--coffee-dark);
            font-weight: 700;
            margin: 0;
        }
        
        .section-header h2 {
            font-size: 2rem;
        }
        
        .section-header h4 {
            font-size: 1.5rem;
        }
        
        .section-header h5 {
            font-size: 1.25rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-coffee me-2"></i>SIM Coffee Shop
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" 
                           href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dasbor
                        </a>
                    </li>
                    
                    <?php if ($current_user && in_array($current_user['role'], ['admin', 'kasir'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : '' ?>" 
                           href="pos.php">
                            <i class="fas fa-cash-register me-1"></i>POS
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($current_user && $current_user['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs me-1"></i>Manajemen
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="products.php"><i class="fas fa-box me-2"></i>Produk</a></li>
                            <li><a class="dropdown-item" href="categories.php"><i class="fas fa-tags me-2"></i>Kategori</a></li>
                            <li><a class="dropdown-item" href="suppliers.php"><i class="fas fa-truck me-2"></i>Supplier</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Pengguna</a></li>
                            <li><a class="dropdown-item" href="stock.php"><i class="fas fa-warehouse me-2"></i>Stok</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales_report.php' ? 'active' : '' ?>" 
                           href="sales_report.php">
                            <i class="fas fa-chart-line me-1"></i>Laporan
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($current_user['name'] ?? 'Pengguna') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="container-fluid py-4 fade-in">
        <?php
        // Display messages if any
        $message_data = getMessage();
        if ($message_data):
        ?>
        <div class="alert alert-<?= $message_data['type'] == 'error' ? 'danger' : $message_data['type'] ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_data['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($message_data['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Utility function to format currency
        function formatCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }
        
        // Global function to reset all buttons to normal state
        function resetAllButtons() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                if (button.disabled) {
                    button.disabled = false;
                    // Reset common button texts
                    if (button.innerHTML.includes('Processing...')) {
                        if (button.id === 'checkout-btn') {
                            button.innerHTML = '<i class="fas fa-credit-card me-2"></i>Process Sale';
                        } else if (button.innerHTML.includes('Save')) {
                            button.innerHTML = button.innerHTML.replace('Processing...', 'Save');
                        } else if (button.innerHTML.includes('Update')) {
                            button.innerHTML = button.innerHTML.replace('Processing...', 'Update');
                        } else if (button.innerHTML.includes('Add')) {
                            button.innerHTML = button.innerHTML.replace('Processing...', 'Add');
                        } else {
                            button.innerHTML = button.innerHTML.replace('<span class="loading"></span> Processing...', 'Submit');
                        }
                    }
                }
            });
        }
        
        // Add loading state to buttons when clicked - but don't interfere with form submit
        document.addEventListener('DOMContentLoaded', function() {
            // Reset all buttons on page load
            resetAllButtons();
            
            // Only handle buttons that don't have custom form handlers
            const buttons = document.querySelectorAll('.btn[type="submit"]');
            buttons.forEach(button => {
                // Skip if button is inside a form with custom handler
                const form = button.closest('form');
                if (form && (form.id && (form.id.includes('Form') || form.id.includes('form') || form.id === 'filterForm'))) {
                    return; // Skip - form has custom handler
                }
                
                button.addEventListener('click', function(e) {
                    // Don't prevent default - just change button state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="loading"></span> Processing...';
                    this.disabled = true;
                    
                    // Restore button after 10 seconds if still processing
                    setTimeout(() => {
                        if (this.disabled) {
                            this.innerHTML = originalText;
                            this.disabled = false;
                        }
                    }, 10000);
                });
            });
            
            // Add global error handler
            window.addEventListener('error', function() {
                resetAllButtons();
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);
    </script>