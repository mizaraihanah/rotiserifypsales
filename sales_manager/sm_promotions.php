<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager (formerly supervisor)
require_once '../config/db_connection.php';

// Initialize variables
$message = '';
$error = '';

// Enable error logging for debugging
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
error_log("Processing promotions management");

// Handle toggle status action via GET
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $new_status = $_GET['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        error_log("Toggling status for promotion ID: $id to $new_status");
        
        // Use a direct SQL update for status changes only
        $stmt = $conn->prepare("UPDATE promotions SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        
        if ($stmt->execute()) {
            $message = "Status changed successfully to " . ucfirst($new_status);
            error_log("Status updated successfully to: $new_status");
        } else {
            $error = "Error updating status: " . $stmt->error;
            error_log("Error updating status: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
        error_log("Exception: " . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $discount_type = $_POST['discount_type'];
                $discount_value = floatval($_POST['discount_value']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];

                // Validate inputs
                if (empty($code) || empty($description) || $discount_value <= 0) {
                    $error = "All fields are required and discount value must be greater than 0";
                } else {
                    // Check for existing promotion code
                    $stmt = $conn->prepare("SELECT id FROM promotions WHERE code = ?");
                    $stmt->bind_param("s", $code);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = "Promotion code already exists";
                    } else {
                        // Create new promotion
                        $stmt = $conn->prepare("INSERT INTO promotions (code, description, discount_type, discount_value, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                        $stmt->bind_param("sssdss", $code, $description, $discount_type, $discount_value, $start_date, $end_date);
                        if ($stmt->execute()) {
                            $message = "Promotion created successfully";
                        } else {
                            $error = "Error creating promotion: " . $stmt->error;
                        }
                    }
                }
                break;

            case 'update':
                $id = (int)$_POST['promotion_id'];
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $discount_type = $_POST['discount_type'];
                $discount_value = floatval($_POST['discount_value']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                // Status is purposely omitted here since we're handling it separately
                
                // Validate inputs
                if (empty($code) || empty($description) || $discount_value <= 0) {
                    $error = "All fields are required and discount value must be greater than 0";
                } else {
                    // Check for existing promotion code excluding current promotion
                    $stmt = $conn->prepare("SELECT id FROM promotions WHERE code = ? AND id != ?");
                    $stmt->bind_param("si", $code, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = "Promotion code already exists";
                    } else {
                        // Update promotion without changing status
                        $stmt = $conn->prepare("
                            UPDATE promotions 
                            SET code = ?, 
                                description = ?, 
                                discount_type = ?, 
                                discount_value = ?, 
                                start_date = ?, 
                                end_date = ?
                            WHERE id = ?
                        ");
                        $stmt->bind_param("sssdssi", $code, $description, $discount_type, $discount_value, $start_date, $end_date, $id);
                        
                        if ($stmt->execute()) {
                            $message = "Promotion updated successfully";
                        } else {
                            $error = "Error updating promotion: " . $stmt->error;
                            error_log("Error updating promotion: " . $stmt->error);
                        }
                    }
                }
                break;

            case 'delete':
                $id = $_POST['promotion_id'];
                $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "Promotion deleted successfully";
                } else {
                    $error = "Error deleting promotion";
                }
                break;
        }
    }
}

// Fetch all promotions
$stmt = $conn->prepare("SELECT * FROM promotions ORDER BY start_date DESC");
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "Promotions Management";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Promotions Management</h1>
                <p class="mb-0 text-muted">Create and manage promotional offers</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPromotionModal">
                <i class="bi bi-plus-circle me-2"></i>New Promotion
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Promotions List -->
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Discount</th>
                                <th>Valid Period</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promotions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No promotions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($promotions as $promo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($promo['code']); ?></td>
                                        <td><?php echo htmlspecialchars($promo['description']); ?></td>
                                        <td>
                                            <?php if ($promo['discount_type'] === 'percentage'): ?>
                                                <?php echo number_format($promo['discount_value'], 0); ?>%
                                            <?php else: ?>
                                                RM<?php echo number_format($promo['discount_value'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo date('M d, Y', strtotime($promo['start_date'])) . ' - ' . 
                                                 date('M d, Y', strtotime($promo['end_date']));
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?action=toggle_status&id=<?php echo $promo['id']; ?>&status=<?php echo $promo['status']; ?>" 
                                               class="badge bg-<?php echo $promo['status'] === 'active' ? 'success' : 'secondary'; ?> text-decoration-none">
                                                <?php echo ucfirst($promo['status']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                    onclick="editPromotion(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deletePromotion(<?php echo $promo['id']; ?>, '<?php echo htmlspecialchars($promo['code']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Promotion Modal -->
<div class="modal fade" id="createPromotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Promotion Code</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Value</label>
                            <input type="number" name="discount_value" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Promotion Modal -->
<div class="modal fade" id="editPromotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="promotion_id" id="edit_promotion_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Promotion Code</label>
                        <input type="text" name="code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" id="edit_discount_type" class="form-select" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Value</label>
                            <input type="number" name="discount_value" id="edit_discount_value" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            To change status, click on the status badge in the promotions list.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePromotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the promotion code <strong id="delete_promotion_code"></strong>?</p>
                <p class="text-danger mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="promotion_id" id="delete_promotion_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Promotion</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// Edit promotion
function editPromotion(promotion) {
    console.log("Editing promotion:", promotion);
    document.getElementById('edit_promotion_id').value = promotion.id;
    document.getElementById('edit_code').value = promotion.code;
    document.getElementById('edit_description').value = promotion.description;
    document.getElementById('edit_discount_type').value = promotion.discount_type;
    document.getElementById('edit_discount_value').value = promotion.discount_value;
    document.getElementById('edit_start_date').value = promotion.start_date;
    document.getElementById('edit_end_date').value = promotion.end_date;
    
    // We're no longer using the status field in the edit form
    // The status is changed separately by clicking on the status badge
    
    new bootstrap.Modal(document.getElementById('editPromotionModal')).show();
}

// Delete promotion
function deletePromotion(id, code) {
    document.getElementById('delete_promotion_id').value = id;
    document.getElementById('delete_promotion_code').textContent = code;
    
    new bootstrap.Modal(document.getElementById('deletePromotionModal')).show();
}
</script>

<style>
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
.badge {
    font-size: 0.85em;
    padding: 0.4em 0.8em;
}

/* Make status badges look clickable */
a.badge {
    cursor: pointer;
    transition: all 0.3s ease;
}

a.badge:hover {
    opacity: 0.8;
    transform: scale(1.1);
}
</style>

<?php include '../includes/footer.php'; ?>