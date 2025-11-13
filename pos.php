<?php
// Start output buffering to prevent any output before redirect
ob_start();

$page_title = "Point of Sale";
require_once 'config.php';
requireKasir();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'process_sale') {
        try {
            $conn->beginTransaction();
            
            // Get form data
            $customer_name = sanitizeInput($_POST['customer_name'] ?? '');
            $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            // Calculate totals
            $total_amount = 0;
            $sale_items = [];
            
            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                foreach ($_POST['product_id'] as $index => $product_id) {
                    $quantity = intval($_POST['quantity'][$index] ?? 0);
                    $price = floatval($_POST['price'][$index] ?? 0);
                    
                    if ($quantity > 0 && $price > 0) {
                        // Validate stock before processing
                        $stock_stmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
                        $stock_stmt->execute([$product_id]);
                        $product = $stock_stmt->fetch();
                        
                        if (!$product) {
                            throw new Exception("Produk tidak ditemukan");
                        }
                        
                        if ($product['stock'] < $quantity) {
                            throw new Exception("Stok {$product['name']} tidak mencukupi. Tersedia: {$product['stock']}");
                        }
                        
                        $subtotal = $quantity * $price;
                        $total_amount += $subtotal;
                        
                        $sale_items[] = [
                            'product_id' => $product_id,
                            'product_name' => sanitizeInput($_POST['product_name'][$index] ?? ''),
                            'quantity' => $quantity,
                            'price' => $price,
                            'subtotal' => $subtotal
                        ];
                    }
                }
            }
            
            if (empty($sale_items)) {
                throw new Exception('Tidak ada item di keranjang');
            }
            
            // Ensure discount and tax are valid
            $discount_amount = max(0, floatval($discount_amount));
            $tax_amount = max(0, floatval($tax_amount));
            
            // Ensure discount doesn't exceed total
            $discount_amount = min($discount_amount, $total_amount);
            
            // Calculate final amount
            $final_amount = $total_amount - $discount_amount + $tax_amount;
            
            // Validate payment amount
            if ($payment_amount < $final_amount) {
                throw new Exception('Jumlah pembayaran tidak mencukupi');
            }
            
            $change_amount = max(0, $payment_amount - $final_amount);
            
            // Generate unique invoice number
            $invoice_number = generateInvoiceNumber();
            // Check if invoice number already exists (retry if needed)
            $check_stmt = $conn->prepare("SELECT id FROM sales WHERE invoice_number = ?");
            $check_stmt->execute([$invoice_number]);
            $attempts = 0;
            while ($check_stmt->fetch() && $attempts < 10) {
                $invoice_number = generateInvoiceNumber();
                $check_stmt->closeCursor(); // Close previous cursor
                $check_stmt = $conn->prepare("SELECT id FROM sales WHERE invoice_number = ?");
                $check_stmt->execute([$invoice_number]);
                $attempts++;
            }
            $check_stmt->closeCursor(); // Close cursor after loop
            
            // Insert sale
            $stmt = $conn->prepare("INSERT INTO sales (invoice_number, user_id, customer_name, total_amount, 
                                   discount_amount, tax_amount, final_amount, payment_amount, change_amount, 
                                   payment_method, status, notes, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())");
            
            $stmt->execute([
                $invoice_number,
                $current_user['id'],
                $customer_name,
                $total_amount,
                $discount_amount,
                $tax_amount,
                $final_amount,
                $payment_amount,
                $change_amount,
                $payment_method,
                $notes
            ]);
            
            $sale_id = $conn->lastInsertId();
            
            // Insert sale items and update stock
            foreach ($sale_items as $item) {
                // Insert sale item
                $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, price, 
                                       quantity, subtotal, created_at, updated_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $sale_id,
                    $item['product_id'],
                    $item['product_name'],
                    $item['price'],
                    $item['quantity'],
                    $item['subtotal']
                ]);
                
                // Update product stock
                $stmt = $conn->prepare("UPDATE products SET stock = stock - ?, updated_at = NOW() 
                                       WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
                
                // Insert stock movement
                $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, 
                                       reference_id, notes, user_id, created_at, updated_at) 
                                       VALUES (?, 'out', ?, 'sale', ?, 'Sale transaction', ?, NOW(), NOW())");
                $stmt->execute([
                    $item['product_id'],
                    $item['quantity'],
                    $sale_id,
                    $current_user['id']
                ]);
            }
            
            $conn->commit();
            
            // Store success message in session
            $_SESSION['pos_success'] = "Penjualan berhasil diproses! Invoice: $invoice_number";
            $_SESSION['pos_invoice'] = $invoice_number;
            
            // Clear ALL output buffers before redirect
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Force redirect - no output allowed
            if (!headers_sent()) {
                header("Location: pos.php?success=1&invoice=" . urlencode($invoice_number));
                header("Connection: close");
                flush();
                exit();
            } else {
                echo '<script>window.location.href="pos.php?success=1&invoice=' . urlencode($invoice_number) . '";</script>';
                exit();
            }
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            
            // Store error message in session
            $_SESSION['pos_error'] = "Error memproses penjualan: " . $e->getMessage();
            error_log("POS Error: " . $e->getMessage());
            error_log("POST Data: " . print_r($_POST, true));
            
            // Clear ALL output buffers before redirect
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Force redirect - no output allowed
            if (!headers_sent()) {
                header("Location: pos.php?error=1");
                header("Connection: close");
                flush();
                exit();
            } else {
                echo '<script>window.location.href="pos.php?error=1";</script>';
                exit();
            }
        }
    }
}

// Handle success/error messages
$success_message = '';
$error_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_SESSION['pos_success'])) {
        $success_message = $_SESSION['pos_success'];
        unset($_SESSION['pos_success']);
    } else {
        $success_message = isset($_GET['invoice']) ? "Penjualan berhasil! Invoice: " . htmlspecialchars($_GET['invoice']) : "Penjualan berhasil diproses!";
    }
}
if (isset($_GET['error']) && $_GET['error'] == '1') {
    if (isset($_SESSION['pos_error'])) {
        $error_message = $_SESSION['pos_error'];
        unset($_SESSION['pos_error']);
    } elseif (isset($_SESSION['message'])) {
        $error_message = $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
    } else {
        $error_message = "Terjadi kesalahan saat memproses penjualan. Silakan coba lagi.";
    }
}

// Get products for POS
$products = $conn->query("SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.status = 'active' AND p.stock > 0 
                          ORDER BY c.name, p.name");

// Get categories for filtering
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// End output buffering and start output (only if not redirecting)
if (!headers_sent()) {
    ob_end_flush();
}

include 'includes/header.php';
?>

<div class="pos-container">
    <div class="pos-header no-print">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="fas fa-cash-register me-2"></i>Point of Sale</h2>
                <p class="mb-0 opacity-75">Selamat datang, <?= htmlspecialchars($current_user['name']) ?>!</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="clearPOS()">
                    <i class="fas fa-trash me-2"></i>Hapus
                </button>
                <button class="btn btn-light btn-sm" onclick="printReceipt()">
                    <i class="fas fa-print me-2"></i>Cetak
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Product Selection -->
        <div class="col-md-8 no-print">
            <div class="pos-product-section">
                <h4 class="mb-4">
                    <i class="fas fa-coffee me-2 text-coffee"></i>Pilih Produk
                </h4>
                
                <!-- Category Filter -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-2">
                        <select class="form-control pos-filter-select" id="category-filter" onchange="filterProducts()">
                            <option value="">☕ Semua Kategori</option>
                            <?php 
                            $categories->execute(); // Reset cursor
                            while ($category = $categories->fetch()): 
                            ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control pos-search-box border-start-0" id="product-search" 
                                   placeholder="Cari produk..." onkeyup="searchProducts()">
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="row g-3" id="products-grid">
                    <?php while ($product = $products->fetch()): ?>
                    <div class="col-md-3 col-lg-2 col-xl-2 product-item" 
                         data-category="<?= $product['category_id'] ?>" 
                         data-name="<?= strtolower($product['name']) ?>">
                        <div class="pos-product-card" onclick="addToCart(<?= htmlspecialchars(json_encode($product)) ?>)">
                            <div class="pos-product-image">
                                <?php if (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                                <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                     class="img-fluid">
                                <?php else: ?>
                                <div class="pos-product-placeholder">
                                    <i class="fas fa-coffee"></i>
                                </div>
                                <?php endif; ?>
                                <div class="pos-product-overlay">
                                    <i class="fas fa-plus-circle fa-2x"></i>
                                </div>
                            </div>
                            <div class="pos-product-info">
                                <h6 class="pos-product-name"><?= htmlspecialchars($product['name']) ?></h6>
                                <p class="pos-product-category"><?= htmlspecialchars($product['category_name']) ?></p>
                                <div class="pos-product-price"><?= formatCurrency($product['price']) ?></div>
                                <small class="pos-product-stock">
                                    <i class="fas fa-box"></i> <?= $product['stock'] ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Cart & Checkout -->
        <div class="col-md-4">
            <div class="pos-cart-section">
                <h4 class="mb-4">
                    <i class="fas fa-shopping-cart me-2 text-coffee"></i>Keranjang Belanja
                </h4>
                
                <form method="POST" action="pos.php" id="pos-form">
                    <input type="hidden" name="action" value="process_sale">
                    
                    <!-- Cart Items -->
                    <div class="table-responsive mb-3" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Produk</th>
                                    <th style="width: 80px;">Jml</th>
                                    <th style="width: 100px;" class="text-end">Total</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items">
                                <tr id="empty-cart">
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <div class="empty-cart-icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        <p class="mt-3 mb-1">Keranjang kosong</p>
                                        <small>Tambahkan produk untuk memulai</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="mb-3">
                        <label class="form-label">Nama Pelanggan (Opsional)</label>
                        <input type="text" class="form-control" name="customer_name" id="customer-name">
                    </div>
                    
                    <!-- Totals -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal" class="fw-bold">Rp 0</span>
                        </div>
                        
                        <!-- Discount Section -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Diskon:</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="discount_type" id="discount-fixed" value="fixed" checked onchange="calculateTotal()">
                                    <label class="btn btn-outline-secondary" for="discount-fixed">Rp</label>
                                    <input type="radio" class="btn-check" name="discount_type" id="discount-percent" value="percent" onchange="calculateTotal()">
                                    <label class="btn btn-outline-secondary" for="discount-percent">%</label>
                                </div>
                            </div>
                            <input type="number" class="form-control form-control-sm" name="discount_value" 
                                   id="discount-value" value="0" min="0" step="0.01" 
                                   oninput="calculateTotal()" placeholder="0">
                            <input type="hidden" name="discount_amount" id="discount-amount" value="0">
                            <small class="text-muted" id="discount-display">Rp 0</small>
                        </div>
                        
                        <!-- Tax Section -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Pajak:</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="tax_type" id="tax-fixed" value="fixed" onchange="calculateTotal()">
                                    <label class="btn btn-outline-secondary" for="tax-fixed">Rp</label>
                                    <input type="radio" class="btn-check" name="tax_type" id="tax-percent" value="percent" checked onchange="calculateTotal()">
                                    <label class="btn btn-outline-secondary" for="tax-percent">%</label>
                                </div>
                            </div>
                            <input type="number" class="form-control form-control-sm" name="tax_value" 
                                   id="tax-value" value="0" min="0" step="0.01" 
                                   oninput="calculateTotal()" placeholder="0">
                            <input type="hidden" name="tax_amount" id="tax-amount" value="0">
                            <small class="text-muted" id="tax-display">Rp 0</small>
                        </div>
                        
                        <hr class="my-3">
                        <div class="pos-total-box">
                            <div class="pos-total-label">Total Pembayaran</div>
                            <div class="pos-total-amount" id="total-amount">Rp 0</div>
                        </div>
                    </div>
                    
                    <!-- Payment -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-money-bill-wave me-2"></i>Metode Pembayaran
                        </label>
                        <select class="form-control pos-filter-select" name="payment_method" id="payment-method">
                            <option value="cash">💵 Tunai</option>
                            <option value="card">💳 Kartu</option>
                            <option value="digital">📱 Pembayaran Digital</option>
                            <option value="transfer">🏦 Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-wallet me-2"></i>Jumlah Pembayaran
                        </label>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="text" class="form-control pos-search-box" name="payment_amount_display" id="payment-amount-display" 
                                   placeholder="0" oninput="formatPaymentInput(this)" onblur="validatePaymentInput()">
                            <input type="hidden" name="payment_amount" id="payment-amount" value="0">
                        </div>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(0.5)">
                                50%
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(1)">
                                100%
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPaymentAmount(1.1)">
                                +10%
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">
                                <i class="fas fa-coins me-2"></i>Kembalian:
                            </span>
                            <span id="change-amount" class="fw-bold fs-4 text-success">Rp 0</span>
                        </div>
                        <div id="change-warning" class="text-danger small mt-2" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> Pembayaran tidak mencukupi!
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-sticky-note me-2"></i>Catatan (Opsional)
                        </label>
                        <textarea class="form-control pos-search-box" name="notes" rows="2" placeholder="Tambahkan catatan di sini..."></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn pos-btn-primary w-100 py-3" id="checkout-btn" disabled>
                        <i class="fas fa-check-circle me-2"></i>Proses Penjualan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart(product) {
    // Validate product stock
    if (product.stock <= 0) {
        showNotification(`Produk <strong>${product.name}</strong> sedang tidak tersedia (stok habis)!`, 'warning', 4000);
        return;
    }
    
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        if (existingItem.quantity >= existingItem.stock) {
            showNotification(`Stok <strong>${product.name}</strong> tidak mencukupi! Tersedia: ${existingItem.stock}`, 'warning', 4000);
            return;
        }
        existingItem.quantity++;
        showNotification(`<strong>${product.name}</strong> ditambahkan ke keranjang! (${existingItem.quantity}x)`, 'success', 2000);
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.price),
            quantity: 1,
            stock: parseInt(product.stock),
            image: product.image || null
        });
        showNotification(`<strong>${product.name}</strong> ditambahkan ke keranjang!`, 'success', 2000);
    }
    
    updateCartDisplay();
}

function removeFromCart(productId) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        cart = cart.filter(item => item.id !== productId);
        updateCartDisplay();
        showNotification(`<strong>${item.name}</strong> dihapus dari keranjang!`, 'info', 2000);
    }
}

function updateQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (!item) return;
    
    const qty = parseInt(quantity) || 0;
    
    if (qty <= 0) {
        removeFromCart(productId);
        showNotification(`<strong>${item.name}</strong> dihapus dari keranjang!`, 'info', 2000);
        return;
    }
    
    if (qty > item.stock) {
        showNotification(`Stok <strong>${item.name}</strong> tidak mencukupi! Maksimal: ${item.stock}`, 'warning', 4000);
        // Reset to max stock
        const quantityInput = document.querySelector(`input[onchange*="${productId}"]`);
        if (quantityInput) {
            quantityInput.value = item.stock;
        }
        item.quantity = item.stock;
        updateCartDisplay();
        return;
    }
    
    if (qty !== item.quantity) {
        item.quantity = qty;
        updateCartDisplay();
    }
}

function updateCartDisplay() {
    const tbody = document.getElementById('cart-items');
    const checkoutBtn = document.getElementById('checkout-btn');
    
    if (cart.length === 0) {
        tbody.innerHTML = '<tr id="empty-cart"><td colspan="5" class="text-center text-muted py-5"><div class="empty-cart-icon"><i class="fas fa-shopping-cart"></i></div><p class="mt-3 mb-0">Keranjang Kosong</p><small>Tambahkan produk untuk memulai</small></td></tr>';
        checkoutBtn.disabled = true;
        return;
    }
    
    let html = '';
    cart.forEach(item => {
        const imageUrl = item.image ? `uploads/products/${item.image}` : '';
        html += `
            <tr class="cart-item-row">
                <td>
                    <div class="d-flex align-items-center">
                        ${imageUrl ? `<img src="${imageUrl}" alt="${item.name}" class="cart-item-image me-2">` : `<div class="cart-item-placeholder me-2"><i class="fas fa-coffee"></i></div>`}
                        <div>
                            <strong>${item.name}</strong>
                            <br><small class="text-muted">${formatCurrency(item.price)} each</small>
                        </div>
                    </div>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm quantity-input" 
                           value="${item.quantity}" min="1" max="${item.stock}" 
                           onchange="updateQuantity(${item.id}, this.value)" style="width: 70px;">
                </td>
                <td class="text-end">
                    <strong class="text-success">${formatCurrency(item.price * item.quantity)}</strong>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    checkoutBtn.disabled = false;
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    // Calculate discount
    const discountType = document.querySelector('input[name="discount_type"]:checked')?.value || 'fixed';
    const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
    let discountAmount = 0;
    
    if (discountType === 'percent') {
        discountAmount = (subtotal * discountValue) / 100;
    } else {
        discountAmount = discountValue;
    }
    
    // Ensure discount doesn't exceed subtotal
    discountAmount = Math.min(discountAmount, subtotal);
    
    // Calculate tax (on subtotal after discount)
    const taxType = document.querySelector('input[name="tax_type"]:checked')?.value || 'percent';
    const taxValue = parseFloat(document.getElementById('tax-value').value) || 0;
    let taxAmount = 0;
    
    const amountAfterDiscount = subtotal - discountAmount;
    
    if (taxType === 'percent') {
        taxAmount = (amountAfterDiscount * taxValue) / 100;
    } else {
        taxAmount = taxValue;
    }
    
    // Calculate final total
    const total = amountAfterDiscount + taxAmount;
    
    // Update display
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('discount-amount').value = discountAmount.toFixed(2);
    document.getElementById('tax-amount').value = taxAmount.toFixed(2);
    document.getElementById('discount-display').textContent = formatCurrency(discountAmount);
    document.getElementById('tax-display').textContent = formatCurrency(taxAmount);
    document.getElementById('total-amount').textContent = formatCurrency(total);
    
    // Auto-fill payment amount if empty
    const paymentDisplayInput = document.getElementById('payment-amount-display');
    const paymentHiddenInput = document.getElementById('payment-amount');
    const currentPayment = parseFloat(paymentHiddenInput.value) || 0;
    
    if (paymentDisplayInput.value === '' || currentPayment === 0) {
        const roundedTotal = Math.ceil(total);
        paymentHiddenInput.value = roundedTotal;
        paymentDisplayInput.value = roundedTotal.toLocaleString('id-ID');
        calculateChange();
    }
    
    calculateChange();
}

function calculateChange() {
    // Get total from the displayed text
    const totalText = document.getElementById('total-amount').textContent;
    const total = parseFloat(totalText.replace(/[^\d]/g, '')) || 0;
    
    // Get payment amount from hidden input (numeric value)
    const payment = parseFloat(document.getElementById('payment-amount').value) || 0;
    
    // Calculate change
    const change = payment - total;
    const changeAmount = Math.max(0, change);
    
    // Validate payment in real-time
    const changeElement = document.getElementById('change-amount');
    const paymentDisplayInput = document.getElementById('payment-amount-display');
    
    if (payment > 0 && payment < total) {
        paymentDisplayInput.classList.add('is-invalid');
        if (changeElement) {
            changeElement.textContent = formatCurrency(0);
            changeElement.classList.add('text-danger');
        }
    } else {
        paymentDisplayInput.classList.remove('is-invalid');
        if (changeElement) {
            changeElement.classList.remove('text-danger');
        }
    }
    
    // Update change display
    const warningElement = document.getElementById('change-warning');
    
    if (change < 0) {
        changeElement.textContent = formatCurrency(0);
        changeElement.className = 'text-danger fs-5';
        warningElement.style.display = 'block';
    } else {
        changeElement.textContent = formatCurrency(changeAmount);
        changeElement.className = 'text-success fs-5';
        warningElement.style.display = 'none';
    }
    
    // Enable/disable checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    if (cart.length > 0 && payment >= total) {
        checkoutBtn.disabled = false;
    } else if (cart.length === 0) {
        checkoutBtn.disabled = true;
    }
}

function formatPaymentInput(input) {
    // Remove all non-digit characters
    let value = input.value.replace(/[^\d]/g, '');
    
    // Update hidden input with numeric value
    document.getElementById('payment-amount').value = value;
    
    // Format with thousand separators
    if (value) {
        const numValue = parseInt(value);
        input.value = numValue.toLocaleString('id-ID');
    } else {
        input.value = '';
    }
    
    // Calculate change in real-time
    calculateChange();
}

function validatePaymentInput() {
    const displayInput = document.getElementById('payment-amount-display');
    const hiddenInput = document.getElementById('payment-amount');
    let value = displayInput.value.replace(/[^\d]/g, '');
    
    if (value) {
        const numValue = parseInt(value);
        hiddenInput.value = numValue;
        displayInput.value = numValue.toLocaleString('id-ID');
    } else {
        displayInput.value = '';
        hiddenInput.value = '0';
    }
    
    calculateChange();
}

function setPaymentAmount(multiplier) {
    const totalText = document.getElementById('total-amount').textContent;
    const total = parseFloat(totalText.replace(/[^\d]/g, '')) || 0;
    const paymentAmount = Math.ceil(total * multiplier);
    
    // Update both display and hidden input
    const displayInput = document.getElementById('payment-amount-display');
    const hiddenInput = document.getElementById('payment-amount');
    
    hiddenInput.value = paymentAmount;
    displayInput.value = paymentAmount.toLocaleString('id-ID');
    
    calculateChange();
}

function clearPOS() {
    if (confirm('Apakah Anda yakin ingin menghapus semua item?')) {
        cart = [];
        updateCartDisplay();
        document.getElementById('customer-name').value = '';
        document.getElementById('discount-value').value = '0';
        document.getElementById('tax-value').value = '0';
        document.getElementById('payment-amount-display').value = '';
        document.getElementById('payment-amount').value = '0';
        document.getElementById('payment-method').value = 'cash';
        document.getElementById('discount-fixed').checked = true;
        document.getElementById('tax-percent').checked = true;
        calculateTotal();
    }
}

function filterProducts() {
    const categoryId = document.getElementById('category-filter').value;
    const productItems = document.querySelectorAll('.product-item');
    
    productItems.forEach(item => {
        if (!categoryId || item.dataset.category === categoryId) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function searchProducts() {
    const searchTerm = document.getElementById('product-search').value.toLowerCase();
    const productItems = document.querySelectorAll('.product-item');
    
    productItems.forEach(item => {
        const productName = item.dataset.name;
        if (productName.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Notification function (if not exists)
function showNotification(message, type = 'info', duration = 4000) {
    // Remove existing notifications
    const existing = document.querySelectorAll('.pos-notification');
    existing.forEach(n => n.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed pos-notification`;
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

document.getElementById('pos-form').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('checkout-btn');
    const originalBtnText = submitBtn.innerHTML;
    
    // Validate cart
    if (cart.length === 0) {
        e.preventDefault();
        showNotification('Mohon tambahkan produk ke keranjang terlebih dahulu!', 'warning');
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return false;
    }
    
    // Validate payment - ensure payment input is properly formatted
    validatePaymentInput();
    
    const totalText = document.getElementById('total-amount').textContent;
    const total = parseFloat(totalText.replace(/[^\d]/g, '')) || 0;
    const payment = parseFloat(document.getElementById('payment-amount').value) || 0;
    
    if (payment <= 0) {
        e.preventDefault();
        showNotification('Mohon masukkan jumlah pembayaran!', 'warning');
        document.getElementById('payment-amount-display').focus();
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return false;
    }
    
    if (payment < total) {
        e.preventDefault();
        const kurang = (total - payment).toLocaleString('id-ID');
        showNotification(`Jumlah pembayaran tidak mencukupi! Kurang: Rp ${kurang}`, 'danger');
        document.getElementById('payment-amount-display').focus();
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return false;
    }
    
    // Validate stock
    let stockErrors = [];
    cart.forEach(item => {
        if (item.quantity > item.stock) {
            stockErrors.push(`${item.name} (tersedia: ${item.stock})`);
        }
    });
    
    if (stockErrors.length > 0) {
        e.preventDefault();
        const errorMsg = 'Stok tidak mencukupi untuk produk berikut:<br>' + 
                        stockErrors.map(e => `• ${e}`).join('<br>');
        showNotification(errorMsg, 'danger', 6000);
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return false;
    }
    
    // Validate quantities
    let quantityErrors = [];
    cart.forEach(item => {
        if (item.quantity <= 0) {
            quantityErrors.push(item.name);
        }
    });
    
    if (quantityErrors.length > 0) {
        e.preventDefault();
        showNotification('Jumlah produk harus lebih dari 0!', 'warning');
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return false;
    }
    
    // Add cart items to form
    cart.forEach((item, index) => {
        const productIdInput = document.createElement('input');
        productIdInput.type = 'hidden';
        productIdInput.name = 'product_id[]';
        productIdInput.value = item.id;
        
        const productNameInput = document.createElement('input');
        productNameInput.type = 'hidden';
        productNameInput.name = 'product_name[]';
        productNameInput.value = item.name;
        
        const quantityInput = document.createElement('input');
        quantityInput.type = 'hidden';
        quantityInput.name = 'quantity[]';
        quantityInput.value = item.quantity;
        
        const priceInput = document.createElement('input');
        priceInput.type = 'hidden';
        priceInput.name = 'price[]';
        priceInput.value = item.price;
        
        this.appendChild(productIdInput);
        this.appendChild(productNameInput);
        this.appendChild(quantityInput);
        this.appendChild(priceInput);
    });
    
    // Ensure discount and tax amounts are set
    const discountAmount = parseFloat(document.getElementById('discount-amount').value) || 0;
    const taxAmount = parseFloat(document.getElementById('tax-amount').value) || 0;
    
    // Set button to processing state
    submitBtn.innerHTML = '<span class="loading"></span> Memproses...';
    submitBtn.disabled = true;
    
    // Set timeout to reset button if form doesn't submit (failsafe)
    let resetTimeout = setTimeout(() => {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        showNotification('Waktu proses terlalu lama. Silakan coba lagi atau refresh halaman.', 'warning', 5000);
    }, 30000); // 30 seconds timeout
    
    // Store timeout ID for cleanup
    window.posResetTimeout = resetTimeout;
    
    // Clear timeout when page unloads (form submitted successfully)
    window.addEventListener('beforeunload', function() {
        if (window.posResetTimeout) {
            clearTimeout(window.posResetTimeout);
        }
    });
    
    // Add error handler for form submission
    this.addEventListener('error', function(e) {
        if (window.posResetTimeout) {
            clearTimeout(window.posResetTimeout);
        }
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        console.error('Form submission error:', e);
    });
    
    // Allow form to submit - the redirect will happen on server side
    return true;
});

document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
    
    // Reset button state on page load (in case of error or page reload)
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.disabled = false;
        checkoutBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Proses Penjualan';
    }
    
    // Handle success/error messages from URL parameters
    <?php if (!empty($success_message)): ?>
    showNotification('<?= addslashes($success_message) ?>', 'success');
    // Clear cart after successful sale
    cart = [];
    updateCartDisplay();
    document.getElementById('customer-name').value = '';
    document.getElementById('discount-value').value = '0';
    document.getElementById('tax-value').value = '0';
    document.getElementById('payment-amount-display').value = '';
    document.getElementById('payment-amount').value = '0';
    document.getElementById('payment-method').value = 'cash';
    document.getElementById('discount-fixed').checked = true;
    document.getElementById('tax-percent').checked = true;
    calculateTotal();
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    showNotification('<?= addslashes($error_message) ?>', 'danger', 6000);
    <?php endif; ?>
    
    // Add input event listeners for real-time calculation
    const discountValue = document.getElementById('discount-value');
    const taxValue = document.getElementById('tax-value');
    const paymentAmountDisplay = document.getElementById('payment-amount-display');
    
    if (discountValue) {
        discountValue.addEventListener('input', calculateTotal);
    }
    if (taxValue) {
        taxValue.addEventListener('input', calculateTotal);
    }
    if (paymentAmountDisplay) {
        // Formatting is handled by formatPaymentInput function
        paymentAmountDisplay.addEventListener('keypress', function(e) {
            // Allow only numbers and backspace/delete
            const char = String.fromCharCode(e.which);
            if (!/[0-9]/.test(char) && e.which !== 8 && e.which !== 0) {
                e.preventDefault();
            }
        });
    }
    
    // Add change listeners for discount and tax type
    document.querySelectorAll('input[name="discount_type"]').forEach(radio => {
        radio.addEventListener('change', calculateTotal);
    });
    document.querySelectorAll('input[name="tax_type"]').forEach(radio => {
        radio.addEventListener('change', calculateTotal);
    });
});

function printReceipt() {
    // Get cart content for printing
    const cartSection = document.querySelector('.pos-cart-section');
    if (!cartSection) {
        showNotification('Konten keranjang tidak ditemukan!', 'warning');
        return;
    }
    
    // Create new window for printing receipt
    const printWindow = window.open('', '_blank', 'width=300,height=600');
    
    // Get customer name and totals
    const customerName = document.getElementById('customer-name')?.value || 'Walk-in Customer';
    const totalAmount = document.getElementById('total-amount')?.textContent || 'Rp 0';
    const paymentAmount = document.getElementById('payment-amount-display')?.value || 'Rp 0';
    const changeAmount = document.getElementById('change-amount')?.textContent || 'Rp 0';
    const paymentMethod = document.getElementById('payment-method')?.value || 'cash';
    
    // Get cart items
    const cartItems = document.querySelectorAll('.cart-item-row');
    let itemsHTML = '';
    cartItems.forEach(item => {
        const productName = item.querySelector('strong')?.textContent || '';
        const quantity = item.querySelector('.quantity-input')?.value || '0';
        const price = item.querySelector('.text-success')?.textContent || 'Rp 0';
        itemsHTML += `
            <tr>
                <td>${productName}</td>
                <td class="text-center">${quantity}</td>
                <td class="text-right">${price}</td>
            </tr>
        `;
    });
    
    // Build receipt HTML
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt</title>
            <style>
                @page {
                    size: 80mm auto;
                    margin: 5mm;
                }
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    margin: 0;
                    padding: 10px;
                    background: white;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 15px;
                    border-bottom: 1px dashed #333;
                    padding-bottom: 10px;
                }
                .receipt-header h2 {
                    margin: 0;
                    font-size: 18px;
                    font-weight: bold;
                }
                .receipt-header p {
                    margin: 3px 0;
                    font-size: 10px;
                }
                .receipt-info {
                    margin: 10px 0;
                    font-size: 11px;
                }
                .receipt-info p {
                    margin: 3px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }
                th, td {
                    padding: 5px;
                    text-align: left;
                    border-bottom: 1px dashed #ddd;
                }
                th {
                    font-weight: bold;
                    border-bottom: 1px solid #333;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .receipt-total {
                    margin-top: 15px;
                    padding-top: 10px;
                    border-top: 2px solid #333;
                }
                .receipt-total p {
                    margin: 5px 0;
                    font-weight: bold;
                }
                .receipt-footer {
                    margin-top: 20px;
                    padding-top: 10px;
                    border-top: 1px dashed #333;
                    text-align: center;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            <div class="receipt-header">
                <h2>SIM COFFEE SHOP</h2>
                <p>Jl. Coffee Street No. 123</p>
                <p>Telp: 021-12345678</p>
            </div>
            <div class="receipt-info">
                <p><strong>Tanggal:</strong> ${new Date().toLocaleString('id-ID')}</p>
                <p><strong>Pelanggan:</strong> ${customerName}</p>
                <p><strong>Pembayaran:</strong> ${paymentMethod.toUpperCase()}</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Jml</th>
                        <th class="text-right">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>
            <div class="receipt-total">
                <p class="text-right">Total: ${totalAmount}</p>
                <p class="text-right">Bayar: ${paymentAmount}</p>
                <p class="text-right">Kembalian: ${changeAmount}</p>
            </div>
            <div class="receipt-footer">
                <p>Terima kasih atas kunjungan Anda!</p>
                <p>Semoga harimu menyenangkan!</p>
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
/* Modern Coffee Shop POS Styles */
.pos-container {
    background: linear-gradient(135deg, #f8f4e8 0%, #f0e6d2 100%);
    min-height: 100vh;
    padding: 20px;
    margin: -20px -15px;
}

.pos-header {
    background: linear-gradient(45deg, #8B4513, #D2691E);
    color: white;
    padding: 20px 30px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
}

.pos-product-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(139, 69, 19, 0.1);
    margin-bottom: 20px;
}

.pos-cart-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(139, 69, 19, 0.1);
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

.pos-product-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.pos-product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(139, 69, 19, 0.2);
    border-color: #8B4513;
}

.pos-product-image {
    position: relative;
    width: 100%;
    height: 140px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5e9da 0%, #e6d3b3 100%);
}

.pos-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.pos-product-card:hover .pos-product-image img {
    transform: scale(1.1);
}

.pos-product-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8B4513;
    font-size: 3rem;
    opacity: 0.3;
}

.pos-product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(139, 69, 19, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
}

.pos-product-card:hover .pos-product-overlay {
    opacity: 1;
}

.pos-product-info {
    padding: 15px;
    text-align: center;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.pos-product-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #654321;
    margin: 0 0 5px 0;
    line-height: 1.3;
}

.pos-product-category {
    font-size: 0.75rem;
    color: #8B4513;
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.pos-product-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2d5016;
    margin: 5px 0;
}

.pos-product-stock {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 5px;
}

/* Cart Styles */
.cart-item-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e6d3b3;
}

.cart-item-placeholder {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #f5e9da 0%, #e6d3b3 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8B4513;
    font-size: 1.2rem;
}

.cart-item-row {
    transition: background 0.2s ease;
}

.cart-item-row:hover {
    background: rgba(139, 69, 19, 0.05);
}

.empty-cart-icon {
    font-size: 4rem;
    color: #d2b48c;
    margin-bottom: 1rem;
}

/* Search & Filter */
.pos-search-box {
    background: white;
    border: 2px solid #e6d3b3;
    border-radius: 12px;
    padding: 12px 20px;
    transition: all 0.3s ease;
}

.pos-search-box:focus {
    border-color: #8B4513;
    box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
    outline: none;
}

.pos-filter-select {
    background: white;
    border: 2px solid #e6d3b3;
    border-radius: 12px;
    padding: 12px 20px;
    transition: all 0.3s ease;
}

.pos-filter-select:focus {
    border-color: #8B4513;
    box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
    outline: none;
}

/* Total Box */
.pos-total-box {
    background: linear-gradient(135deg, #f5e9da 0%, #e6d3b3 100%);
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
    border: 2px solid #d2b48c;
}

.pos-total-label {
    font-size: 0.9rem;
    color: #654321;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.pos-total-amount {
    font-size: 2rem;
    font-weight: 700;
    color: #2d5016;
    margin-top: 5px;
}

/* Button Styles */
.pos-btn-primary {
    background: linear-gradient(45deg, #8B4513, #D2691E);
    border: none;
    color: white;
    padding: 15px 30px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
}

.pos-btn-primary:hover {
    background: linear-gradient(45deg, #654321, #8B4513);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
    color: white;
}

.pos-btn-primary:disabled {
    background: #d2b48c;
    cursor: not-allowed;
    transform: none;
    opacity: 0.6;
}

.text-coffee {
    color: #8B4513;
}

/* Scrollbar Styling */
.pos-cart-section::-webkit-scrollbar {
    width: 6px;
}

.pos-cart-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.pos-cart-section::-webkit-scrollbar-thumb {
    background: #8B4513;
    border-radius: 10px;
}

.pos-cart-section::-webkit-scrollbar-thumb:hover {
    background: #654321;
}

/* Responsive */
@media (max-width: 768px) {
    .pos-container {
        padding: 10px;
        margin: -10px -10px;
    }
    
    .pos-product-card {
        margin-bottom: 15px;
    }
    
    .pos-product-image {
        height: 120px;
    }
    
    .pos-cart-section {
        position: relative;
        max-height: none;
        margin-top: 20px;
    }
    
    .pos-header {
        padding: 15px 20px;
    }
}

.no-print {
    /* Elements with this class won't print */
}

@media print {
    .navbar, .btn-group, .col-md-8, .pos-header, .no-print {
        display: none !important;
    }
    
    .col-md-4 {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    body {
        background: white !important;
    }
    
    .pos-product-section, .pos-cart-section {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
