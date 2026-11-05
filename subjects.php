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
            $message = "Subject added successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "Subject updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "Subject deleted successfully!";
            $message_type = "success";
            break;
        case 'duplicate':
            $message = "A subject with this name or code already exists.";
            $message_type = "danger";
            break;
        case 'in_use':
            $message = "Cannot delete: grades are recorded for this subject.";
            $message_type = "danger";
            break;
        case 'error':
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
            break;
    }
}

// =====================================
// GET SUBJECT FOR EDITING (MUST RUN FIRST)
// =====================================

$edit_subject = null;

if (isset($_GET['edit'])) {
    $subject_id = (int) $_GET['edit'];

    $sql = "SELECT * FROM subjects WHERE subject_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_subject = mysqli_fetch_assoc($result);
}

// =====================================
// ADD SUBJECT
// =====================================

if (isset($_POST['add_subject'])) {

    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);

    if ($subject_name === '' || $subject_code === '') {
        $message = "Subject name and code are required.";
        $message_type = "danger";
    } else {
        $sql = "INSERT INTO subjects (subject_name, subject_code) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $subject_name, $subject_code);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: subjects.php?msg=added");
            exit();
        } else {
            header("Location: subjects.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// UPDATE SUBJECT
// =====================================

if (isset($_POST['update_subject'])) {

    $subject_id   = (int) $_POST['subject_id'];
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);

    if ($subject_name === '' || $subject_code === '') {
        $message = "Subject name and code are required.";
        $message_type = "danger";
    } else {
        $sql = "UPDATE subjects SET subject_name = ?, subject_code = ? WHERE subject_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $subject_name, $subject_code, $subject_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: subjects.php?msg=updated");
            exit();
        } else {
            header("Location: subjects.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// DELETE SUBJECT (with safety check)
// =====================================

if (isset($_GET['delete'])) {

    $subject_id = (int) $_GET['delete'];

    // Check if any grades reference this subject
    $check_sql = "SELECT COUNT(*) AS total FROM grades WHERE subject_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $check_res = mysqli_stmt_get_result($stmt);
    $check = mysqli_fetch_assoc($check_res);

    if ($check['total'] > 0) {
        header("Location: subjects.php?msg=in_use");
        exit();
    }

    $sql = "DELETE FROM subjects WHERE subject_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: subjects.php?msg=deleted");
        exit();
    } else {
        header("Location: subjects.php?msg=error");
        exit();
    }
}

// =====================================
// SEARCH
// =====================================

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// =====================================
// GET SUBJECTS (with grade counts)
// =====================================

$sql = "SELECT sub.subject_id,
               sub.subject_name,
               sub.subject_code,
               COUNT(g.grade_id) AS grade_count
        FROM subjects sub
        LEFT JOIN grades g ON g.subject_id = sub.subject_id";

$params = [];
$types  = "";

if ($search !== '') {
    $sql .= " WHERE sub.subject_name LIKE ? OR sub.subject_code LIKE ?";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

$sql .= " GROUP BY sub.subject_id, sub.subject_name, sub.subject_code
          ORDER BY sub.subject_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$subject_result = mysqli_stmt_get_result($stmt);

// Buffer results
$all_subjects = [];
while ($s = mysqli_fetch_assoc($subject_result)) {
    $all_subjects[] = $s;
}

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">Subjects Management</h2>
    <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#subjectModal">
        + Add New Subject
    </button>
</div>

<!-- FLASH MESSAGE -->
<?php if ($message != "") { ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<!-- SEARCH CARD -->
<div class="card shadow border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Search Subjects</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search by Name or Code</label>
                <input type="text" name="search" class="form-control"
                       placeholder="e.g. Mathematics or MATH101"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="subjects.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- SUBJECT MODAL -->
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <?php echo $edit_subject ? "Update Subject" : "Add New Subject"; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST">

                    <?php if ($edit_subject) { ?>
                        <input type="hidden" name="subject_id"
                               value="<?php echo $edit_subject['subject_id']; ?>">
                    <?php } ?>

                    <!-- Subject Name -->
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control"
                               placeholder="e.g. Mathematics"
                               value="<?php echo htmlspecialchars($edit_subject['subject_name'] ?? ''); ?>"
                               required>
                    </div>

                    <!-- Subject Code -->
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control"
                               placeholder="e.g. MATH101"
                               value="<?php echo htmlspecialchars($edit_subject['subject_code'] ?? ''); ?>"
                               required>
                    </div>

                    <?php if ($edit_subject) { ?>
                        <button type="submit" name="update_subject" class="btn btn-success">
                            Update Subject
                        </button>
                        <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                    <?php } else { ?>
                        <button type="submit" name="add_subject" class="btn btn-primary">
                            Save Subject
                        </button>
                    <?php } ?>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- SUBJECTS TABLE -->
<div class="card shadow border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Registered Subjects</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Grade Records</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_subjects) > 0) { ?>
                        <?php foreach ($all_subjects as $s) { ?>
                        <tr>
                            <td><?php echo $s['subject_id']; ?></td>
                            <td><?php echo htmlspecialchars($s['subject_name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($s['subject_code']); ?></span></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?php echo $s['grade_count']; ?> grade(s)
                                </span>
                            </td>
                            <td>
                                <a href="subjects.php?edit=<?php echo $s['subject_id']; ?>"
                                   class="btn btn-warning btn-sm">Edit</a>

                                <?php if ($s['grade_count'] > 0) { ?>
                                    <button class="btn btn-danger btn-sm" disabled
                                            title="Cannot delete: grades exist for this subject">
                                        Delete
                                    </button>
                                <?php } else { ?>
                                    <a href="subjects.php?delete=<?php echo $s['subject_id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this subject?');">
                                        Delete
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="5" class="text-center">No subjects registered yet.</td>
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
<?php if ($edit_subject) { ?>
<script>
    var subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
    subjectModal.show();
</script>
<?php } ?>