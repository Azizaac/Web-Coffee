<?php
$page_title = "Categories";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ✅ Validasi CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid CSRF token!";
        $message_type = 'danger';
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);

                    if (!empty($name)) {
                        try {
                            $stmt = $conn->prepare("INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                            $stmt->execute([$name, $description]);

                            if ($stmt->rowCount() > 0) {
                                $message = "Category added successfully!";
                            } else {
                                $message = "Error adding category!";
                                $message_type = 'danger';
                            }
                        } catch (PDOException $e) {
                            $message = "DB Error: " . $e->getMessage();
                            $message_type = 'danger';
                        }
                    } else {
                        $message = "Category name is required!";
                        $message_type = 'danger';
                    }
                    break;

                case 'edit':
                    $id = (int)$_POST['id'];
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);

                    if (!empty($name) && $id > 0) {
                        try {
                            $stmt = $conn->prepare("UPDATE categories SET name=?, description=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$name, $description, $id]);

                            if ($stmt->rowCount() > 0) {
                                $message = "Category updated successfully!";
                            } else {
                                $message = "No changes made!";
                                $message_type = 'warning';
                            }
                        } catch (PDOException $e) {
                            $message = "DB Error: " . $e->getMessage();
                            $message_type = 'danger';
                        }
                    } else {
                        $message = "Invalid category data!";
                        $message_type = 'danger';
                    }
                    break;
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $check_stmt->execute([$id]);
        $check_data = $check_stmt->fetch();

        if ($check_data['count'] > 0) {
            $message = "Cannot delete category! It is being used by " . $check_data['count'] . " product(s).";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                $message = "Category deleted successfully!";
            } else {
                $message = "Error deleting category!";
                $message_type = 'danger';
            }
        }
    }
}

// Get edit data
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Get all categories
$categories = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                          FROM categories c 
                          LEFT JOIN products p ON c.id = p.category_id 
                          GROUP BY c.id 
                          ORDER BY c.name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tags me-2"></i>Categories Management</h2>
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="fas fa-plus me-2"></i>Add New Category
        </button>
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
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories->rowCount() > 0): ?>
                        <?php $no = 1; while ($category = $categories->fetch()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                            <td><?= htmlspecialchars($category['description'] ?: '-') ?></td>
                            <td><span class="badge bg-info"><?= $category['product_count'] ?> products</span></td>
                            <td><small><?= date('M j, Y', strtotime($category['created_at'])) ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $category['id'] ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($category['product_count'] == 0): ?>
                                    <a href="?delete=<?= $category['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No categories found</p>
                                <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                    <i class="fas fa-plus me-2"></i>Add First Category
                                </button>
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
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Add New' ?> Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <!-- ✅ CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= isset($edit_data) ? htmlspecialchars($edit_data['name']) : '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= isset($edit_data) ? htmlspecialchars($edit_data['description']) : '' ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-coffee">
                        <i class="fas fa-save me-2"></i><?= isset($edit_data) ? 'Update' : 'Save' ?>
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

<?php include 'includes/footer.php'; ?>
