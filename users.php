<?php
$page_title = "Users Management";
require_once 'config.php';
requireAdmin();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();

$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                if (!empty($name) && !empty($email) && !empty($password) && !empty($role)) {
                    // Check if email already exists
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Email already exists!";
                        $message_type = 'danger';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $hashed_password, $role]);
                        
                        if ($stmt->rowCount() > 0) {
                            $message = "User added successfully!";
                        } else {
                            $message = "Error adding user!";
                            $message_type = 'danger';
                        }
                    }
                } else {
                    $message = "Please fill all required fields!";
                    $message_type = 'danger';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $password = $_POST['password'];
                
                if (!empty($name) && !empty($email) && !empty($role) && $id > 0) {
                    // Check if email already exists (excluding current user)
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check_stmt->execute([$email, $id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $message = "Email already exists!";
                        $message_type = 'danger';
                    } else {
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$name, $email, $hashed_password, $role, $id]);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$name, $email, $role, $id]);
                        }
                        
                        if ($stmt->rowCount() > 0) {
                            $message = "User updated successfully!";
                        } else {
                            $message = "Error updating user!";
                            $message_type = 'danger';
                        }
                    }
                } else {
                    $message = "Invalid user data!";
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0 && $id != $current_user['id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $message = "User deleted successfully!";
        } else {
            $message = "Error deleting user!";
            $message_type = 'danger';
        }
    } else {
        $message = "Cannot delete your own account!";
        $message_type = 'danger';
    }
}

// Get edit data if editing
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Users Management</h2>
        <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="fas fa-plus me-2"></i>Add New User
        </button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
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
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
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
                                <span class="badge bg-primary ms-2">You</span>
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
                                    <a href="?edit=<?= $user['id'] ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $current_user['id']): ?>
                                    <a href="?delete=<?= $user['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this user?')">
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
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users found</p>
                                <button class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#userModal">
                                    <i class="fas fa-plus me-2"></i>Add First User
                                </button>
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
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <?= isset($edit_data) ? 'Edit' : 'Add New' ?> User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= isset($edit_data) ? 'edit' : 'add' ?>">
                    <?php if (isset($edit_data)): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
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
                            Password <?= isset($edit_data) ? '(leave blank to keep current)' : '*' ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               <?= !isset($edit_data) ? 'required' : '' ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin" <?= (isset($edit_data) && $edit_data['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="kasir" <?= (isset($edit_data) && $edit_data['role'] == 'kasir') ? 'selected' : '' ?>>Kasir</option>
                        </select>
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
// Auto-open modal for editing
document.addEventListener('DOMContentLoaded', function() {
    var userModal = new bootstrap.Modal(document.getElementById('userModal'));
    userModal.show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
