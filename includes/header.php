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
            --coffee-primary: #8B4513;
            --coffee-secondary: #D2691E;
            --coffee-light: #F5DEB3;
            --coffee-dark: #654321;
            --coffee-accent: #CD853F;
        }
        
        body {
            background: linear-gradient(135deg, #f8f4e8 0%, #f0e6d2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(45deg, var(--coffee-primary), var(--coffee-secondary)) !important;
            box-shadow: 0 2px 15px rgba(139, 69, 19, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0 5px;
            position: relative;
        }
        
        .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }
        
        .btn-coffee {
            background: linear-gradient(45deg, var(--coffee-primary), var(--coffee-secondary));
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
        }
        
        .btn-coffee:hover {
            background: linear-gradient(45deg, var(--coffee-dark), var(--coffee-primary));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
        
        .btn-outline-coffee {
            border: 2px solid var(--coffee-primary);
            color: var(--coffee-primary);
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-coffee:hover {
            background: var(--coffee-primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(139, 69, 19, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(139, 69, 19, 0.1);
        }
        
        .dashboard-stats {
            background: linear-gradient(135deg, white 0%, #fefcf8 100%);
            border: none;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(139, 69, 19, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .dashboard-stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(45deg, var(--coffee-primary), var(--coffee-secondary));
        }
        
        .dashboard-stats:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(139, 69, 19, 0.2);
        }
        
        .table {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(45deg, var(--coffee-light), #f0e6d2);
            color: var(--coffee-dark);
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(139, 69, 19, 0.05);
            transform: scale(1.01);
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
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(45deg, var(--coffee-light), #f0e6d2);
            border-radius: 20px 20px 0 0;
            border-bottom: 1px solid rgba(139, 69, 19, 0.1);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--coffee-primary);
            box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
        }
        
        .badge {
            border-radius: 20px;
            font-weight: 500;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--coffee-primary), var(--coffee-dark));
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 15px rgba(139, 69, 19, 0.2);
        }
        
        .coffee-icon {
            color: var(--coffee-primary);
        }
        
        .dropdown-menu {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .dropdown-item {
            padding: 12px 20px;
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 5px;
        }
        
        .dropdown-item:hover {
            background: var(--coffee-light);
            color: var(--coffee-dark);
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
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--coffee-primary);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--coffee-dark);
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
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
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
                            <i class="fas fa-cogs me-1"></i>Management
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="products.php"><i class="fas fa-box me-2"></i>Products</a></li>
                            <li><a class="dropdown-item" href="categories.php"><i class="fas fa-tags me-2"></i>Categories</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                            <li><a class="dropdown-item" href="stock.php"><i class="fas fa-warehouse me-2"></i>Stock</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales_report.php' ? 'active' : '' ?>" 
                           href="sales_report.php">
                            <i class="fas fa-chart-line me-1"></i>Reports
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($current_user['name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
        
        // Add loading state to buttons when clicked
        document.addEventListener('DOMContentLoaded', function() {
            // Reset all buttons on page load
            resetAllButtons();
            
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.type === 'submit') {
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
                    }
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