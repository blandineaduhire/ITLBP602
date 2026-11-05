<?php
include "config/db.php";
include "includes/auth.php";
require_role(['admin']);

// Message variables
$message = "";
$message_type = "";

// =====================================
// FLASH MESSAGES
// =====================================

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = "User created successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "User updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "User deleted successfully!";
            $message_type = "success";
            break;
        case 'duplicate':
            $message = "That username is already taken.";
            $message_type = "danger";
            break;
        case 'self_delete':
            $message = "You cannot delete your own account.";
            $message_type = "danger";
            break;
        case 'last_admin':
            $message = "Cannot delete the last admin account.";
            $message_type = "danger";
            break;
        case 'error':
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
            break;
    }
}

// =====================================
// GET USER FOR EDITING (MUST RUN FIRST)
// =====================================

$edit_user = null;

if (isset($_GET['edit'])) {
    $user_id = (int) $_GET['edit'];

    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_user = mysqli_fetch_assoc($result);
}

// =====================================
// ADD USER
// =====================================

if (isset($_POST['add_user'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // Validation
    $errors = [];
    if ($username === '') $errors[] = "Username is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if (!in_array($role, ['admin', 'teacher', 'student'], true)) $errors[] = "Invalid role.";

    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $message_type = "danger";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $hashed, $role);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: users.php?msg=added");
            exit();
        } else {
            header("Location: users.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// UPDATE USER
// =====================================

if (isset($_POST['update_user'])) {

    $user_id  = (int) $_POST['user_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];   // may be blank → keep current
    $role     = $_POST['role'];

    $errors = [];
    if ($username === '') $errors[] = "Username is required.";
    if (!in_array($role, ['admin', 'teacher', 'student'], true)) $errors[] = "Invalid role.";
    if ($password !== '' && strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // Prevent an admin from demoting themselves if they're the last admin
    if ($user_id === (int) $_SESSION['user_id'] && $role !== 'admin') {
        $chk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
        $row = mysqli_fetch_assoc($chk);
        if ((int) $row['total'] <= 1) {
            $errors[] = "You are the last admin. Cannot change your role.";
        }
    }

    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $message_type = "danger";
    } else {
        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, password = ?, role = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $username, $hashed, $role, $user_id);
        } else {
            $sql = "UPDATE users SET username = ?, role = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $username, $role, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            header("Location: users.php?msg=updated");
            exit();
        } else {
            header("Location: users.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// DELETE USER
// =====================================

if (isset($_GET['delete'])) {

    $user_id = (int) $_GET['delete'];

    // Cannot delete yourself
    if ($user_id === (int) $_SESSION['user_id']) {
        header("Location: users.php?msg=self_delete");
        exit();
    }

    // Cannot delete the last admin
    $sql = "SELECT role FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($target && $target['role'] === 'admin') {
        $chk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
        $row = mysqli_fetch_assoc($chk);
        if ((int) $row['total'] <= 1) {
            header("Location: users.php?msg=last_admin");
            exit();
        }
    }

    // Delete
    $sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: users.php?msg=deleted");
        exit();
    } else {
        header("Location: users.php?msg=error");
        exit();
    }
}

// =====================================
// FILTERS
// =====================================

$filter_role = isset($_GET['role'])   ? trim($_GET['role'])   : '';
$search      = isset($_GET['search']) ? trim($_GET['search']) : '';

// =====================================
// BUILD USER LIST QUERY
// =====================================

$sql = "SELECT u.user_id,
               u.username,
               u.role,
               s.student_id,
               s.names AS student_name
        FROM users u
        LEFT JOIN students s ON s.user_id = u.user_id";

$where  = [];
$params = [];
$types  = "";

if ($filter_role !== '' && in_array($filter_role, ['admin','teacher','student'], true)) {
    $where[]  = "u.role = ?";
    $params[] = $filter_role;
    $types   .= "s";
}

if ($search !== '') {
    $where[]  = "u.username LIKE ?";
    $params[] = "%{$search}%";
    $types   .= "s";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY
            CASE u.role
                WHEN 'admin' THEN 1
                WHEN 'teacher' THEN 2
                WHEN 'student' THEN 3
                ELSE 4
            END,
            u.username ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

$all_users = [];
while ($u = mysqli_fetch_assoc($user_result)) {
    $all_users[] = $u;
}

// =====================================
// COUNTS PER ROLE (for summary badges)
// =====================================

$role_counts = ['admin' => 0, 'teacher' => 0, 'student' => 0];
$cnt_res = mysqli_query($conn, "SELECT role, COUNT(*) AS total FROM users GROUP BY role");
while ($r = mysqli_fetch_assoc($cnt_res)) {
    if (isset($role_counts[$r['role']])) {
        $role_counts[$r['role']] = (int) $r['total'];
    }
}

// =====================================
// ROLE BADGE HELPER
// =====================================

function roleBadge($role) {
    switch ($role) {
        case 'admin':   return ['Admin', 'danger'];
        case 'teacher': return ['Teacher', 'warning text-dark'];
        case 'student': return ['Student', 'info text-dark'];
        default:        return [ucfirst($role), 'secondary'];
    }
}

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">User Accounts</h2>
    <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#userModal">
        + Add New User
    </button>
</div>

<!-- FLASH MESSAGE -->
<?php if ($message != "") { ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<!-- SUMMARY BADGES -->
<div class="mb-3">
    <span class="badge bg-danger">Admins: <?php echo $role_counts['admin']; ?></span>
    <span class="badge bg-warning text-dark">Teachers: <?php echo $role_counts['teacher']; ?></span>
    <span class="badge bg-info text-dark">Students: <?php echo $role_counts['student']; ?></span>
    <span class="badge bg-secondary">Total: <?php echo array_sum($role_counts); ?></span>
</div>

<!-- FILTER CARD -->
<div class="card shadow border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Filter Users</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="admin"   <?php if ($filter_role === 'admin')   echo 'selected'; ?>>Admin</option>
                    <option value="teacher" <?php if ($filter_role === 'teacher') echo 'selected'; ?>>Teacher</option>
                    <option value="student" <?php if ($filter_role === 'student') echo 'selected'; ?>>Student</option>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label">Search by Username</label>
                <input type="text" name="search" class="form-control"
                       placeholder="e.g. john"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="users.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- USER MODAL -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <?php echo $edit_user ? "Update User" : "Add New User"; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST">

                    <?php if ($edit_user) { ?>
                        <input type="hidden" name="user_id"
                               value="<?php echo $edit_user['user_id']; ?>">
                    <?php } ?>

                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               placeholder="e.g. john_student"
                               value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>"
                               required>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label">
                            Password
                            <?php if ($edit_user) { ?>
                                <small class="text-muted">(leave blank to keep current)</small>
                            <?php } ?>
                        </label>
                        <input type="password" name="password" class="form-control"
                               placeholder="<?php echo $edit_user ? 'New password (optional)' : 'Min 6 characters'; ?>"
                               <?php echo $edit_user ? '' : 'required'; ?>
                               minlength="6">
                    </div>

                    <!-- Role -->
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="admin"
                                <?php if (($edit_user['role'] ?? '') === 'admin') echo 'selected'; ?>>
                                Admin — full access
                            </option>
                            <option value="teacher"
                                <?php if (($edit_user['role'] ?? '') === 'teacher') echo 'selected'; ?>>
                                Teacher — manage students, attendance, grades
                            </option>
                            <option value="student"
                                <?php if (($edit_user['role'] ?? '') === 'student') echo 'selected'; ?>>
                                Student — view own data only
                            </option>
                        </select>
                    </div>

                    <?php if ($edit_user) { ?>
                        <button type="submit" name="update_user" class="btn btn-success">
                            Update User
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <?php } else { ?>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            Save User
                        </button>
                    <?php } ?>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- USERS TABLE -->
<div class="card shadow border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">All User Accounts</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Linked Student</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_users) > 0) { ?>
                        <?php foreach ($all_users as $u) {
                            list($role_label, $role_color) = roleBadge($u['role']);
                            $is_self = ((int) $u['user_id'] === (int) $_SESSION['user_id']);
                        ?>
                        <tr>
                            <td><?php echo $u['user_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                <?php if ($is_self) { ?>
                                    <span class="badge bg-primary">You</span>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $role_color; ?>">
                                    <?php echo $role_label; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($u['student_name'])) { ?>
                                    <span class="badge bg-success">
                                        <?php echo htmlspecialchars($u['student_name']); ?>
                                    </span>
                                <?php } elseif ($u['role'] === 'student') { ?>
                                    <span class="badge bg-secondary">Not linked</span>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td>
                                <a href="users.php?edit=<?php echo $u['user_id']; ?>"
                                   class="btn btn-warning btn-sm">Edit</a>

                                <?php if ($is_self) { ?>
                                    <button class="btn btn-danger btn-sm" disabled
                                            title="You cannot delete your own account">
                                        Delete
                                    </button>
                                <?php } else { ?>
                                    <a href="users.php?delete=<?php echo $u['user_id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete user \'<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>\'?');">
                                        Delete
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="5" class="text-center">No users found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include "includes/footer.php";
?>

<!-- Auto-open modal when editing -->
<?php if ($edit_user) { ?>
<script>
    var userModal = new bootstrap.Modal(document.getElementById('userModal'));
    userModal.show();
</script>
<?php } ?>