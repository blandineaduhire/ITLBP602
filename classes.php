<?php
include "config/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Message variables
$message = "";
$message_type = "";

// =====================================
// FLASH MESSAGES
// =====================================

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = "Class added successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "Class updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "Class deleted successfully!";
            $message_type = "success";
            break;
        case 'duplicate':
            $message = "A class with this name already exists for that academic year.";
            $message_type = "danger";
            break;
        case 'in_use':
            $message = "Cannot delete: students are still assigned to this class.";
            $message_type = "danger";
            break;
        case 'error':
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
            break;
    }
}

// =====================================
// GET CLASS FOR EDITING (MUST RUN FIRST)
// =====================================

$edit_class = null;

if (isset($_GET['edit'])) {
    $class_id = (int) $_GET['edit'];

    $sql = "SELECT * FROM classes WHERE class_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_class = mysqli_fetch_assoc($result);
}

// =====================================
// ADD CLASS
// =====================================

if (isset($_POST['add_class'])) {

    $class_name    = trim($_POST['class_name']);
    $academic_year = trim($_POST['academic_year']);

    if ($class_name === '' || $academic_year === '') {
        $message = "Class name and academic year are required.";
        $message_type = "danger";
    } else {
        $sql = "INSERT INTO classes (class_name, academic_year) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $class_name, $academic_year);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: classes.php?msg=added");
            exit();
        } else {
            header("Location: classes.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// UPDATE CLASS
// =====================================

if (isset($_POST['update_class'])) {

    $class_id      = (int) $_POST['class_id'];
    $class_name    = trim($_POST['class_name']);
    $academic_year = trim($_POST['academic_year']);

    if ($class_name === '' || $academic_year === '') {
        $message = "Class name and academic year are required.";
        $message_type = "danger";
    } else {
        $sql = "UPDATE classes SET class_name = ?, academic_year = ? WHERE class_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $class_name, $academic_year, $class_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: classes.php?msg=updated");
            exit();
        } else {
            header("Location: classes.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// DELETE CLASS (with safety check)
// =====================================

if (isset($_GET['delete'])) {

    $class_id = (int) $_GET['delete'];

    // Check if any students are assigned to this class
    $check_sql = "SELECT COUNT(*) AS total FROM students WHERE class_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $check_res = mysqli_stmt_get_result($stmt);
    $check = mysqli_fetch_assoc($check_res);

    if ($check['total'] > 0) {
        header("Location: classes.php?msg=in_use");
        exit();
    }

    $sql = "DELETE FROM classes WHERE class_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $class_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: classes.php?msg=deleted");
        exit();
    } else {
        header("Location: classes.php?msg=error");
        exit();
    }
}

// =====================================
// FILTER
// =====================================

$filter_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';

// =====================================
// GET DISTINCT ACADEMIC YEARS (for filter dropdown)
// =====================================

$years = [];
$res = mysqli_query($conn, "SELECT DISTINCT academic_year FROM classes ORDER BY academic_year DESC");
while ($y = mysqli_fetch_assoc($res)) $years[] = $y['academic_year'];

// =====================================
// GET CLASSES (with student counts)
// =====================================

$sql = "SELECT c.class_id,
               c.class_name,
               c.academic_year,
               COUNT(s.student_id) AS student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.class_id";

$params = [];
$types  = "";

if ($filter_year !== '') {
    $sql .= " WHERE c.academic_year = ?";
    $params[] = $filter_year;
    $types   .= "s";
}

$sql .= " GROUP BY c.class_id, c.class_name, c.academic_year
          ORDER BY c.academic_year DESC, c.class_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$class_result = mysqli_stmt_get_result($stmt);

// Buffer results so we can iterate
$all_classes = [];
while ($c = mysqli_fetch_assoc($class_result)) {
    $all_classes[] = $c;
}

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">Classes Management</h2>
    <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#classModal">
        + Add New Class
    </button>
</div>

<!-- FLASH MESSAGE -->
<?php if ($message != "") { ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<!-- FILTER CARD -->
<div class="card shadow border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Filter Classes</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Academic Year</label>
                <select name="academic_year" class="form-select">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y) { ?>
                        <option value="<?php echo htmlspecialchars($y); ?>"
                            <?php if ($filter_year === $y) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($y); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="classes.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- CLASS MODAL -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <?php echo $edit_class ? "Update Class" : "Add New Class"; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST">

                    <?php if ($edit_class) { ?>
                        <input type="hidden" name="class_id"
                               value="<?php echo $edit_class['class_id']; ?>">
                    <?php } ?>

                    <!-- Class Name -->
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="class_name" class="form-control"
                               placeholder="e.g. Form 1A, Grade 5"
                               value="<?php echo htmlspecialchars($edit_class['class_name'] ?? ''); ?>"
                               required>
                    </div>

                    <!-- Academic Year -->
                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control"
                               placeholder="e.g. 2025"
                               value="<?php echo htmlspecialchars($edit_class['academic_year'] ?? date('Y')); ?>"
                               required>
                    </div>

                    <?php if ($edit_class) { ?>
                        <button type="submit" name="update_class" class="btn btn-success">
                            Update Class
                        </button>
                        <a href="classes.php" class="btn btn-secondary">Cancel</a>
                    <?php } else { ?>
                        <button type="submit" name="add_class" class="btn btn-primary">
                            Save Class
                        </button>
                    <?php } ?>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- CLASSES TABLE -->
<div class="card shadow border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Registered Classes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Class Name</th>
                        <th>Academic Year</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_classes) > 0) { ?>
                        <?php foreach ($all_classes as $c) { ?>
                        <tr>
                            <td><?php echo $c['class_id']; ?></td>
                            <td><?php echo htmlspecialchars($c['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['academic_year']); ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?php echo $c['student_count']; ?> student(s)
                                </span>
                            </td>
                            <td>
                                <a href="classes.php?edit=<?php echo $c['class_id']; ?>"
                                   class="btn btn-warning btn-sm">Edit</a>

                                <?php if ($c['student_count'] > 0) { ?>
                                    <button class="btn btn-danger btn-sm" disabled
                                            title="Cannot delete: students are assigned to this class">
                                        Delete
                                    </button>
                                <?php } else { ?>
                                    <a href="classes.php?delete=<?php echo $c['class_id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this class?');">
                                        Delete
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="5" class="text-center">No classes registered yet.</td>
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
<?php if ($edit_class) { ?>
<script>
    var classModal = new bootstrap.Modal(document.getElementById('classModal'));
    classModal.show();
</script>
<?php } ?>