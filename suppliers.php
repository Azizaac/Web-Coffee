<?php
// Start output buffering
ob_start();

$page_title = "Manajemen Supplier";
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
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate CSRF token
    $csrf_valid = true;
    if (isset($_POST['csrf_token'])) {
        $csrf_valid = validateCSRFToken($_POST['csrf_token']);
    }
    
    if (!$csrf_valid) {
        $message = "Invalid CSRF token!";
        $message_type = 'danger';
    } elseif (empty($name)) {
            $message = "Nama supplier wajib diisi!";
        $message_type = 'danger';
    } else {
        try {
            if ($action == 'add') {
                // Add new supplier
                $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $result = $stmt->execute([$name, $contact_person, $phone, $email, $address, $status]);
                
                if ($result) {
                    $new_id = $conn->lastInsertId();
                    if ($new_id > 0) {
                        $message = "Supplier berhasil ditambahkan!";
                    } else {
                        $message = "Gagal menambahkan supplier!";
                        $message_type = 'danger';
                    }
                } else {
                    $message = "Gagal menambahkan supplier!";
                    $message_type = 'danger';
                }
            } elseif ($action == 'edit') {
                // Edit supplier
                $id = intval($_POST['id'] ?? 0);
                
                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $contact_person, $phone, $email, $address, $status, $id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Supplier berhasil diperbarui!";
                    } else {
                        $message = "Tidak ada perubahan!";
                        $message_type = 'warning';
                    }
                } else {
                    $message = "ID supplier tidak valid!";
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Supplier Error: " . $e->getMessage());
        }
    }
    
    // Clear ALL output buffers and redirect IMMEDIATELY
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Force redirect - no output allowed
    if (!headers_sent()) {
        header("Location: suppliers.php?msg=" . urlencode($message) . "&type=" . $message_type);
        header("Connection: close");
        flush();
        exit();
    } else {
        echo '<script>window.location.href="suppliers.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
        exit();
    }
}

// Handle GET requests (Delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id > 0) {
        try {
            // Check if supplier is used by products
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE supplier_id = ?");
            $check_stmt->execute([$id]);
            $check_data = $check_stmt->fetch();
            
            if ($check_data['count'] > 0) {
                $message = "Tidak dapat menghapus supplier! Sedang digunakan oleh " . $check_data['count'] . " produk.";
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Supplier berhasil dihapus!";
                } else {
                    $message = "Supplier tidak ditemukan!";
                    $message_type = 'warning';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
            error_log("Delete Supplier Error: " . $e->getMessage());
        }
        
        // Clear ALL output buffers and redirect IMMEDIATELY
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Force redirect - no output allowed
        if (!headers_sent()) {
            header("Location: suppliers.php?msg=" . urlencode($message) . "&type=" . $message_type);
            header("Connection: close");
            flush();
            exit();
        } else {
            echo '<script>window.location.href="suppliers.php?msg=' . urlencode($message) . '&type=' . $message_type . '";</script>';
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
        $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Get all suppliers with product count
$suppliers = $conn->query("SELECT s.*, COUNT(p.id) as product_count 
                          FROM suppliers s 
                          LEFT JOIN products p ON s.id = p.supplier_id 
                          GROUP BY s.id 
                          ORDER BY s.name");

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
                <h2><i class="fas fa-truck me-2"></i>Manajemen Supplier</h2>
                <p class="mb-0">Kelola supplier dan vendor produk Anda</p>
            </div>
            <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#supplierModal">
                <i class="fas fa-plus me-2"></i>Tambah Supplier Baru
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
                        <th>Nama Supplier</th>
                        <th>Kontak Person</th>
                        <th>Telepon</th>
                        <th>Email</th>
                        <th>Alamat</th>
                        <th>Produk</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($suppliers->rowCount() > 0): ?>
                        <?php $no = 1; while ($supplier = $suppliers->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($supplier['name']) ?></strong></td>
                            <td><?= htmlspecialchars($supplier['contact_person'] ?: '-') ?></td>
                            <td>
                                <?php if ($supplier['phone']): ?>
                                    <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($supplier['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($supplier['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($supplier['email']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($supplier['address']): ?>
                                    <div><?= htmlspecialchars($supplier['address']) ?></div>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                onclick="showSupplierMap('<?= htmlspecialchars($supplier['address'], ENT_QUOTES) ?>', '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-map me-1"></i>Lihat Peta
                                        </button>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($supplier['address']) ?>" 
                                           target="_blank" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-external-link-alt me-1"></i>Google Maps
                                        </a>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?= $supplier['product_count'] ?> produk</span></td>
                            <td>
                                <span class="badge bg-<?= $supplier['status'] == 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($supplier['status']) ?>
                                </span>
                            </td>
                            <td><small><?= date('M j, Y', strtotime($supplier['created_at'])) ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $supplier['id'] ?>" class="btn btn-action btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($supplier['product_count'] == 0): ?>
                                    <a href="?delete=<?= $supplier['id'] ?>" 
                                       class="btn btn-action btn-delete"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus supplier ini?')"
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
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-truck"></i>
                        <h4>Tidak Ada Supplier</h4>
                        <p>Tambahkan supplier untuk mengelola vendor produk Anda</p>
                                    <button class="btn btn-coffee mt-3" data-bs-toggle="modal" data-bs-target="#supplierModal">
                                        <i class="fas fa-plus me-2"></i>Tambah Supplier Pertama
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

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="suppliers.php" id="supplierForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-truck me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Tambah' ?> Supplier
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Supplier *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Kontak Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['contact_person']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telepon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['phone']) : '' ?>"
                                       placeholder="021-12345678">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= isset($edit_data) ? htmlspecialchars($edit_data['email']) : '' ?>"
                                       placeholder="supplier@example.com">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>Alamat
                        </label>
                        <!-- Address Autocomplete Input (hidden, for autocomplete) -->
                        <input type="text" class="form-control mb-2" id="address-autocomplete" 
                               placeholder="Ketik alamat untuk autocomplete (opsional)..."
                               style="display: none;">
                        <textarea class="form-control" id="address" name="address" rows="3" 
                                  placeholder="Masukkan alamat lengkap supplier..."><?= isset($edit_data) ? htmlspecialchars($edit_data['address']) : '' ?></textarea>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Ketik alamat lengkap supplier. Klik tombol "Lihat di Peta" untuk melihat lokasi di Google Maps.
                        </small>
                        <?php if (isset($edit_data) && !empty($edit_data['address'])): ?>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showSupplierMap('<?= htmlspecialchars($edit_data['address'], ENT_QUOTES) ?>', '<?= htmlspecialchars($edit_data['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-map me-1"></i>Lihat di Peta
                            </button>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($edit_data['address']) ?>" 
                               target="_blank" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-external-link-alt me-1"></i>Buka di Google Maps
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Map Preview (hidden, will be shown in modal) -->
                    <div id="map-preview" style="display: none;">
                        <div id="map" style="height: 300px; width: 100%; border-radius: 8px; margin-top: 10px;"></div>
                    </div>

                    <?php if (isset($edit_data)): ?>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?= $edit_data['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $edit_data['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
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

<!-- Map Modal -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapModalLabel">
                    <i class="fas fa-map-marker-alt me-2"></i>Lokasi Supplier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="supplier-map" style="height: 500px; width: 100%; border-radius: 8px;"></div>
                <div id="map-address" class="mt-3 p-3 bg-light rounded">
                    <strong><i class="fas fa-map-marker-alt me-2"></i>Alamat:</strong>
                    <span id="map-address-text"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a id="open-google-maps" href="#" target="_blank" class="btn btn-coffee">
                    <i class="fas fa-external-link-alt me-2"></i>Buka di Google Maps
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Google Maps API - Keyless Version -->
<!-- Menggunakan Keyless Google Maps API dari: https://github.com/somanchiu/Keyless-Google-Maps-API -->
<script>
// Load Google Maps API (Keyless - tidak perlu API Key)
function loadGoogleMaps() {
    if (typeof google === 'undefined' || !google.maps) {
        const script = document.createElement('script');
        // Menggunakan Keyless Google Maps API
        script.src = 'https://cdn.jsdelivr.net/gh/somanchiu/Keyless-Google-Maps-API@v7.1/mapsJavaScriptAPI.js';
        script.async = true;
        script.defer = true;
        script.onload = function() {
            console.log('Google Maps API (Keyless) loaded successfully');
            if (typeof initMap === 'function') {
                initMap();
            }
        };
        script.onerror = function() {
            console.error('Gagal memuat Google Maps API. Menggunakan fallback.');
            // Fallback: tetap bisa menggunakan link Google Maps
        };
        document.head.appendChild(script);
    } else {
        if (typeof initMap === 'function') {
            initMap();
        }
    }
}

// Load maps when page is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadGoogleMaps);
} else {
    loadGoogleMaps();
}
</script>

<?php if (isset($edit_data)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var supplierModal = new bootstrap.Modal(document.getElementById('supplierModal'));
    supplierModal.show();
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
    const supplierForm = document.getElementById('supplierForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (supplierForm && submitBtn) {
        supplierForm.addEventListener('submit', function(e) {
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate required fields
            const requiredFields = supplierForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate email format if provided
            const emailField = supplierForm.querySelector('input[name="email"]');
            if (emailField && emailField.value && !emailField.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                emailField.classList.add('is-invalid');
                isValid = false;
                showNotification('Format email tidak valid!', 'danger');
            }
            
            // Validate name length
            const nameField = supplierForm.querySelector('input[name="name"]');
            if (nameField && nameField.value.trim().length < 2) {
                nameField.classList.add('is-invalid');
                isValid = false;
                showNotification('Nama supplier minimal 2 karakter!', 'warning');
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = supplierForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                if (!emailField || emailField.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/) && (!nameField || nameField.value.trim().length >= 2)) {
                    showNotification('Silakan isi semua field yang wajib dengan benar!', 'warning');
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
            window.supplierFormTimeout = resetTimeout;
            
            // Clear timeout when page unloads (form submitted successfully)
            window.addEventListener('beforeunload', function() {
                if (window.supplierFormTimeout) {
                    clearTimeout(window.supplierFormTimeout);
                }
            });
            
            // Allow form to submit - the redirect will happen on server side
            return true;
        });
        
        // Reset button state on page load (in case of error or page reload)
        submitBtn.disabled = false;
        const isEdit = supplierForm.querySelector('input[name="id"]') !== null;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>' + (isEdit ? 'Perbarui' : 'Simpan');
    }
});

// Google Maps functionality
let map;
let geocoder;
let marker;

function initMap() {
    // Initialize geocoder
    geocoder = new google.maps.Geocoder();
    
    // This function is called when Google Maps API is loaded
    console.log('Google Maps API loaded');
    
    // Initialize autocomplete after maps loads
    setTimeout(initAddressAutocomplete, 500);
    
    // Dispatch event for other scripts
    window.dispatchEvent(new Event('google-maps-loaded'));
}

function showSupplierMap(address, supplierName) {
    if (!address || address.trim() === '') {
        showNotification('Alamat tidak tersedia!', 'warning');
        return;
    }
    
    // Show modal
    const mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
    mapModal.show();
    
    // Update modal title and address
    document.getElementById('mapModalLabel').innerHTML = `<i class="fas fa-map-marker-alt me-2"></i>Lokasi: ${supplierName}`;
    document.getElementById('map-address-text').textContent = address;
    
    // Update Google Maps link (selalu berfungsi, tidak perlu API key)
    const googleMapsLink = document.getElementById('open-google-maps');
    googleMapsLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
    
    // Check if Google Maps API is available
    if (typeof google === 'undefined' || !google.maps) {
        // If API not loaded yet, wait a bit and try again
        setTimeout(() => {
            if (typeof google !== 'undefined' && google.maps) {
                showSupplierMap(address, supplierName); // Retry
            } else {
                // Fallback: show message and use iframe
                const mapElement = document.getElementById('supplier-map');
                mapElement.innerHTML = `
                    <div style="height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; background: #f5f5f5; border-radius: 8px; padding: 20px;">
                        <i class="fas fa-map-marked-alt fa-3x mb-3" style="color: #6F4E37;"></i>
                        <p class="mb-2">Memuat peta...</p>
                        <p class="text-muted small mb-3">Jika peta tidak muncul, gunakan tombol "Buka di Google Maps" di bawah.</p>
                        <iframe 
                            width="100%" 
                            height="400" 
                            style="border:0; border-radius: 8px;" 
                            loading="lazy" 
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed">
                        </iframe>
                    </div>
                `;
            }
        }, 1000);
        return;
    }
    
    // Initialize map when modal is shown
    const mapElement = document.getElementById('supplier-map');
    
    // Wait for modal to be fully shown
    setTimeout(() => {
        // Default center (Indonesia - Jakarta)
        const defaultCenter = { lat: -6.2088, lng: 106.8456 };
        
        // Initialize map
        map = new google.maps.Map(mapElement, {
            zoom: 15,
            center: defaultCenter,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
            zoomControl: true
        });
        
        // Geocode address
        if (!geocoder) {
            geocoder = new google.maps.Geocoder();
        }
        
        geocoder.geocode({ address: address }, function(results, status) {
            if (status === 'OK' && results[0]) {
                // Set map center to geocoded location
                const location = results[0].geometry.location;
                map.setCenter(location);
                map.setZoom(16);
                
                // Add marker
                if (marker) {
                    marker.setMap(null);
                }
                marker = new google.maps.Marker({
                    map: map,
                    position: location,
                    title: supplierName,
                    animation: google.maps.Animation.DROP,
                    icon: {
                        url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
                    }
                });
                
                // Add info window
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px; min-width: 200px;">
                            <h6 style="margin: 0 0 5px 0; font-weight: bold; color: #6F4E37;">${supplierName}</h6>
                            <p style="margin: 0; font-size: 12px; color: #666;">${address}</p>
                        </div>
                    `
                });
                
                marker.addListener('click', function() {
                    infoWindow.open(map, marker);
                });
                
                // Open info window by default
                infoWindow.open(map, marker);
            } else {
                // If geocoding fails, show error and center on default location
                console.error('Geocoding failed:', status);
                showNotification('Tidak dapat menemukan lokasi. Menampilkan peta default. Gunakan tombol "Buka di Google Maps" untuk melihat lokasi yang tepat.', 'warning');
                
                // Still add a marker at default location with address text
                if (marker) {
                    marker.setMap(null);
                }
                marker = new google.maps.Marker({
                    map: map,
                    position: defaultCenter,
                    title: supplierName,
                    label: {
                        text: supplierName.substring(0, 15),
                        color: '#6F4E37',
                        fontSize: '12px',
                        fontWeight: 'bold'
                    }
                });
                
                // Show info window with address
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px;">
                            <h6 style="margin: 0 0 5px 0; font-weight: bold;">${supplierName}</h6>
                            <p style="margin: 0; font-size: 12px; color: #666;">${address}</p>
                            <p style="margin: 5px 0 0 0; font-size: 11px; color: #999;">Lokasi tidak ditemukan. Gunakan link Google Maps untuk navigasi.</p>
                        </div>
                    `
                });
                infoWindow.open(map, marker);
            }
        });
    }, 300);
}

// Initialize autocomplete for address input (optional enhancement)
function initAddressAutocomplete() {
    const addressInput = document.getElementById('address');
    const autocompleteInput = document.getElementById('address-autocomplete');
    
    if (addressInput && autocompleteInput && typeof google !== 'undefined' && google.maps && google.maps.places) {
        try {
            // Create autocomplete for the hidden input
            const autocomplete = new google.maps.places.Autocomplete(autocompleteInput, {
                componentRestrictions: { country: 'id' }, // Restrict to Indonesia
                fields: ['formatted_address', 'geometry', 'name', 'address_components'],
                types: ['address', 'establishment']
            });
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.formatted_address) {
                    // Copy formatted address to textarea
                    addressInput.value = place.formatted_address;
                    // Hide autocomplete input
                    autocompleteInput.style.display = 'none';
                    autocompleteInput.value = '';
                }
            });
            
            // Show autocomplete input when user focuses on address textarea
            addressInput.addEventListener('focus', function() {
                autocompleteInput.style.display = 'block';
            });
        } catch (e) {
            console.log('Places Autocomplete not available:', e);
            // Hide autocomplete input if not available
            if (autocompleteInput) {
                autocompleteInput.style.display = 'none';
            }
        }
    } else {
        // Hide autocomplete if Google Maps not loaded
        if (autocompleteInput) {
            autocompleteInput.style.display = 'none';
        }
    }
}

// Initialize autocomplete after Google Maps loads
// Wait a bit longer for keyless API to fully load (may not support Places API)
setTimeout(function() {
    if (typeof google !== 'undefined' && google.maps) {
        initAddressAutocomplete();
    } else {
        // Try again after another delay
        setTimeout(initAddressAutocomplete, 2000);
    }
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>

