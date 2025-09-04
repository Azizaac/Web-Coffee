<?php
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
                throw new Exception('No items in cart');
            }
            
            $final_amount = $total_amount - $discount_amount + $tax_amount;
            $change_amount = max(0, $payment_amount - $final_amount);
            
            // Generate invoice number
            $invoice_number = generateInvoiceNumber();
            
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
            
            // Redirect to receipt or success page
            setMessage("Sale completed successfully! Invoice: $invoice_number", 'success');
            redirect("pos.php?success=1&invoice=$invoice_number");
            
        } catch (Exception $e) {
            $conn->rollback();
            setMessage("Error processing sale: " . $e->getMessage(), 'error');
        }
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

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cash-register me-2"></i>Point of Sale</h2>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="clearPOS()">
                <i class="fas fa-trash me-2"></i>Clear All
            </button>
            <button class="btn btn-outline-warning" onclick="resetAllButtons()">
                <i class="fas fa-refresh me-2"></i>Reset Buttons
            </button>
            <button class="btn btn-coffee" onclick="printReceipt()">
                <i class="fas fa-print me-2"></i>Print Receipt
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Product Selection -->
        <div class="col-md-8">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-box me-2"></i>Select Products
                </h5>
                
                <!-- Category Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select class="form-control" id="category-filter" onchange="filterProducts()">
                            <option value="">All Categories</option>
                            <?php while ($category = $categories->fetch()): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="product-search" 
                               placeholder="Search products..." onkeyup="searchProducts()">
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="row" id="products-grid">
                    <?php while ($product = $products->fetch()): ?>
                    <div class="col-md-4 col-lg-3 mb-3 product-item" 
                         data-category="<?= $product['category_id'] ?>" 
                         data-name="<?= strtolower($product['name']) ?>">
                        <div class="card h-100 product-card" onclick="addToCart(<?= htmlspecialchars(json_encode($product)) ?>)">
                            <div class="card-body text-center">
                                <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                <p class="card-text text-muted small"><?= htmlspecialchars($product['category_name']) ?></p>
                                <h5 class="text-success"><?= formatCurrency($product['price']) ?></h5>
                                <small class="text-muted">Stock: <?= $product['stock'] ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Cart & Checkout -->
        <div class="col-md-4">
            <div class="table-container">
                <h5 class="mb-3">
                    <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                </h5>
                
                <form method="POST" action="" id="pos-form">
                    <input type="hidden" name="action" value="process_sale">
                    
                    <!-- Cart Items -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items">
                                <tr id="empty-cart">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="fas fa-shopping-cart fa-2x mb-2"></i><br>
                                        No items in cart
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="mb-3">
                        <label class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" name="customer_name" id="customer-name">
                    </div>
                    
                    <!-- Totals -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span id="subtotal">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Discount:</span>
                            <input type="number" class="form-control form-control-sm" name="discount_amount" 
                                   id="discount-amount" value="0" min="0" onchange="calculateTotal()" style="width: 100px; display: inline-block;">
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tax:</span>
                            <input type="number" class="form-control form-control-sm" name="tax_amount" 
                                   id="tax-amount" value="0" min="0" onchange="calculateTotal()" style="width: 100px; display: inline-block;">
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total:</span>
                            <span id="total-amount">Rp 0</span>
                        </div>
                    </div>
                    
                    <!-- Payment -->
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method" id="payment-method">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="digital">Digital Payment</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Amount</label>
                        <input type="number" class="form-control" name="payment_amount" id="payment-amount" 
                               min="0" step="100" onchange="calculateChange()">
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Change:</span>
                            <span id="change-amount" class="text-success">Rp 0</span>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-coffee w-100" id="checkout-btn" disabled>
                        <i class="fas fa-credit-card me-2"></i>Process Sale
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.price),
            quantity: 1,
            stock: parseInt(product.stock)
        });
    }
    
    updateCartDisplay();
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    updateCartDisplay();
}

function updateQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        if (quantity <= 0) {
            removeFromCart(productId);
        } else {
            item.quantity = Math.min(quantity, item.stock);
            updateCartDisplay();
        }
    }
}

function updateCartDisplay() {
    const tbody = document.getElementById('cart-items');
    const checkoutBtn = document.getElementById('checkout-btn');
    
    if (cart.length === 0) {
        tbody.innerHTML = '<tr id="empty-cart"><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-shopping-cart fa-2x mb-2"></i><br>No items in cart</td></tr>';
        checkoutBtn.disabled = true;
        return;
    }
    
    let html = '';
    cart.forEach(item => {
        html += `
            <tr>
                <td>${item.name}</td>
                <td>
                    <input type="number" class="form-control form-control-sm quantity-input" 
                           value="${item.quantity}" min="1" max="${item.stock}" 
                           onchange="updateQuantity(${item.id}, this.value)" style="width: 60px;">
                </td>
                <td>${formatCurrency(item.price)}</td>
                <td>${formatCurrency(item.price * item.quantity)}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="removeFromCart(${item.id})">
                        <i class="fas fa-trash"></i>
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
    
    const discount = parseFloat(document.getElementById('discount-amount').value) || 0;
    const tax = parseFloat(document.getElementById('tax-amount').value) || 0;
    const total = subtotal - discount + tax;
    
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('total-amount').textContent = formatCurrency(total);
    
    const paymentInput = document.getElementById('payment-amount');
    if (!paymentInput.value) {
        paymentInput.value = total;
    }
    
    calculateChange();
}

function calculateChange() {
    const total = parseFloat(document.getElementById('total-amount').textContent.replace(/[^\d]/g, '')) || 0;
    const payment = parseFloat(document.getElementById('payment-amount').value) || 0;
    const change = payment - total;
    
    document.getElementById('change-amount').textContent = formatCurrency(Math.max(0, change));
}

function clearPOS() {
    if (confirm('Are you sure you want to clear all items?')) {
        cart = [];
        updateCartDisplay();
        document.getElementById('customer-name').value = '';
        document.getElementById('discount-amount').value = '0';
        document.getElementById('tax-amount').value = '0';
        document.getElementById('payment-amount').value = '';
        document.getElementById('payment-method').value = 'cash';
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

document.getElementById('pos-form').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('checkout-btn');
    const originalBtnText = submitBtn.innerHTML;
    
    if (cart.length === 0) {
        e.preventDefault();
        alert('Please add items to cart first');
        // Reset button state
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        return;
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
    
    // Set button to processing state
    submitBtn.innerHTML = '<span class="loading"></span> Processing...';
    submitBtn.disabled = true;
    
    // Add error handling for form submission
    this.addEventListener('error', function() {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });
});

document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
});

function printReceipt() {
    // Hide navigation and other elements for printing
    const navbar = document.querySelector('.navbar');
    const buttons = document.querySelector('.btn-group');
    const productSelection = document.querySelector('.col-md-8');
    
    if (navbar) navbar.style.display = 'none';
    if (buttons) buttons.style.display = 'none';
    if (productSelection) productSelection.style.display = 'none';
    
    // Print the page
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        if (navbar) navbar.style.display = 'block';
        if (buttons) buttons.style.display = 'block';
        if (productSelection) productSelection.style.display = 'block';
    }, 1000);
}
</script>

<style>
@media print {
    .navbar, .btn-group, .col-md-8 {
        display: none !important;
    }
    
    .col-md-4 {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    body {
        background: white !important;
    }
    
    .table-container {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
