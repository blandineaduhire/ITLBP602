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
            $message = "Grade recorded successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "Grade updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "Grade deleted successfully!";
            $message_type = "success";
            break;
        case 'duplicate':
            $message = "A grade for this student / subject / assessment / year already exists.";
            $message_type = "danger";
            break;
        case 'error':
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
            break;
    }
}

// =====================================
// GET GRADE FOR EDITING (MUST RUN FIRST)
// =====================================

$edit_grade = null;

if (isset($_GET['edit'])) {
    $grade_id = (int) $_GET['edit'];

    $sql = "SELECT * FROM grades WHERE grade_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $grade_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_grade = mysqli_fetch_assoc($result);
}

// =====================================
// ADD GRADE
// =====================================

if (isset($_POST['add_grade'])) {

    $student_id      = (int) $_POST['student_id'];
    $subject_id      = (int) $_POST['subject_id'];
    $assessment_type = trim($_POST['assessment_type']);
    $marks           = (float) $_POST['marks'];
    $academic_year   = trim($_POST['academic_year']);

    // Basic validation
    if ($marks < 0 || $marks > 100) {
        $message = "Marks must be between 0 and 100.";
        $message_type = "danger";
    } else {
        $sql = "INSERT INTO grades
                (student_id, subject_id, assessment_type, marks, academic_year)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt, "iisds",
            $student_id, $subject_id, $assessment_type, $marks, $academic_year
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: grades.php?msg=added");
            exit();
        } else {
            // Duplicate key or other error
            header("Location: grades.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// UPDATE GRADE
// =====================================

if (isset($_POST['update_grade'])) {

    $grade_id        = (int) $_POST['grade_id'];
    $student_id      = (int) $_POST['student_id'];
    $subject_id      = (int) $_POST['subject_id'];
    $assessment_type = trim($_POST['assessment_type']);
    $marks           = (float) $_POST['marks'];
    $academic_year   = trim($_POST['academic_year']);

    if ($marks < 0 || $marks > 100) {
        $message = "Marks must be between 0 and 100.";
        $message_type = "danger";
    } else {
        $sql = "UPDATE grades SET
                student_id = ?,
                subject_id = ?,
                assessment_type = ?,
                marks = ?,
                academic_year = ?
                WHERE grade_id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        // FIXED: type string must have no spaces and exactly 6 chars
        mysqli_stmt_bind_param(
            $stmt, "iisdsi",
            $student_id, $subject_id, $assessment_type, $marks, $academic_year, $grade_id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: grades.php?msg=updated");
            exit();
        } else {
            header("Location: grades.php?msg=duplicate");
            exit();
        }
    }
}

// =====================================
// DELETE GRADE
// =====================================

if (isset($_GET['delete'])) {
    $grade_id = (int) $_GET['delete'];

    $sql = "DELETE FROM grades WHERE grade_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $grade_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: grades.php?msg=deleted");
        exit();
    } else {
        header("Location: grades.php?msg=error");
        exit();
    }
}

// =====================================
// FILTERS
// =====================================

$filter_class   = isset($_GET['class_id'])        ? (int) $_GET['class_id']        : 0;
$filter_subject = isset($_GET['subject_id'])      ? (int) $_GET['subject_id']      : 0;
$filter_type    = isset($_GET['assessment_type']) ? trim($_GET['assessment_type']) : '';
$filter_year    = isset($_GET['academic_year'])   ? trim($_GET['academic_year'])   : '';

// =====================================
// GET CLASSES
// =====================================

$classes = [];
$res = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name ASC");
while ($c = mysqli_fetch_assoc($res)) $classes[] = $c;

// =====================================
// GET SUBJECTS
// =====================================

$subjects = [];
$res = mysqli_query($conn, "SELECT * FROM subjects ORDER BY subject_name ASC");
while ($s = mysqli_fetch_assoc($res)) $subjects[] = $s;

// =====================================
// GET STUDENTS (for the add/edit modal)
// =====================================

$students = [];
$res = mysqli_query($conn,
    "SELECT s.student_id, s.admission_number, s.names, c.class_name
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.class_id
     ORDER BY s.names ASC");
while ($s = mysqli_fetch_assoc($res)) $students[] = $s;

// =====================================
// BUILD GRADE LIST QUERY (with filters)
// =====================================

$where  = [];
$params = [];
$types  = "";

if ($filter_class > 0) {
    $where[]  = "s.class_id = ?";
    $params[] = $filter_class;
    $types   .= "i";
}
if ($filter_subject > 0) {
    $where[]  = "g.subject_id = ?";
    $params[] = $filter_subject;
    $types   .= "i";
}
if ($filter_type !== '') {
    $where[]  = "g.assessment_type = ?";
    $params[] = $filter_type;
    $types   .= "s";
}
if ($filter_year !== '') {
    $where[]  = "g.academic_year = ?";
    $params[] = $filter_year;
    $types   .= "s";
}

$sql = "SELECT g.*,
               s.admission_number,
               s.names AS student_name,
               s.class_id,
               c.class_name,
               sub.subject_name
        FROM grades g
        INNER JOIN students s ON g.student_id = s.student_id
        LEFT  JOIN classes c  ON s.class_id = c.class_id
        LEFT  JOIN subjects sub ON g.subject_id = sub.subject_id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY g.grade_id DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$grade_result = mysqli_stmt_get_result($stmt);

// =====================================
// SUMMARY STATS
// =====================================

$summary = ['count' => 0, 'avg' => 0, 'highest' => 0, 'lowest' => 0];

// Buffer the result so we can use it for both summary and table
$all_grades = [];
while ($g = mysqli_fetch_assoc($grade_result)) {
    $all_grades[] = $g;
}

if (count($all_grades) > 0) {
    $marks_arr = array_column($all_grades, 'marks');
    $summary['count']   = count($marks_arr);
    $summary['avg']     = round(array_sum($marks_arr) / count($marks_arr), 1);
    $summary['highest'] = max($marks_arr);
    $summary['lowest']  = min($marks_arr);
}

// =====================================
// HELPER: compute letter grade
// =====================================

function letterGrade($marks) {
    if ($marks >= 80) return ['A', 'success'];
    if ($marks >= 70) return ['B', 'primary'];
    if ($marks >= 60) return ['C', 'info text-dark'];
    if ($marks >= 50) return ['D', 'warning text-dark'];
    return ['F', 'danger'];
}

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">Grades Management</h2>
    <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#gradeModal">
        + Add New Grade
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
        <h5 class="mb-0">Filter Grades</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c) { ?>
                        <option value="<?php echo $c['class_id']; ?>"
                            <?php if ($filter_class == $c['class_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s) { ?>
                        <option value="<?php echo $s['subject_id']; ?>"
                            <?php if ($filter_subject == $s['subject_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($s['subject_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Assessment</label>
                <select name="assessment_type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['Test', 'Quiz', 'Assignment', 'Midterm', 'Final'] as $t) { ?>
                        <option value="<?php echo $t; ?>"
                            <?php if ($filter_type === $t) echo 'selected'; ?>>
                            <?php echo $t; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Academic Year</label>
                <input type="text" name="academic_year" class="form-control"
                       placeholder="e.g. 2025"
                       value="<?php echo htmlspecialchars($filter_year); ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="grades.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- SUMMARY -->
<?php if ($summary['count'] > 0) { ?>
<div class="mb-3">
    <span class="badge bg-primary">Records: <?php echo $summary['count']; ?></span>
    <span class="badge bg-info text-dark">Average: <?php echo $summary['avg']; ?></span>
    <span class="badge bg-success">Highest: <?php echo $summary['highest']; ?></span>
    <span class="badge bg-danger">Lowest: <?php echo $summary['lowest']; ?></span>
</div>
<?php } ?>

<!-- GRADE MODAL -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <?php echo $edit_grade ? "Update Grade" : "Record New Grade"; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST">

                    <?php if ($edit_grade) { ?>
                        <input type="hidden" name="grade_id"
                               value="<?php echo $edit_grade['grade_id']; ?>">
                    <?php } ?>

                    <div class="row">

                        <!-- Student -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $s) {
                                    $selected = ($edit_grade && $edit_grade['student_id'] == $s['student_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $s['student_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($s['admission_number'] . ' - ' . $s['names']); ?>
                                        <?php if (!empty($s['class_name'])) echo ' (' . htmlspecialchars($s['class_name']) . ')'; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s) {
                                    $selected = ($edit_grade && $edit_grade['subject_id'] == $s['subject_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $s['subject_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($s['subject_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Assessment Type -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assessment Type</label>
                            <select name="assessment_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach (['Test', 'Quiz', 'Assignment', 'Midterm', 'Final'] as $t) {
                                    $selected = ($edit_grade && $edit_grade['assessment_type'] === $t) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $t; ?>" <?php echo $selected; ?>>
                                        <?php echo $t; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Marks -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marks (0 - 100)</label>
                            <input type="number" name="marks" class="form-control"
                                   min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars($edit_grade['marks'] ?? ''); ?>"
                                   required>
                        </div>

                        <!-- Academic Year -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" class="form-control"
                                   placeholder="e.g. 2025"
                                   value="<?php echo htmlspecialchars($edit_grade['academic_year'] ?? date('Y')); ?>"
                                   required>
                        </div>

                    </div>

                    <?php if ($edit_grade) { ?>
                        <button type="submit" name="update_grade" class="btn btn-success">
                            Update Grade
                        </button>
                        <a href="grades.php" class="btn btn-secondary">Cancel</a>
                    <?php } else { ?>
                        <button type="submit" name="add_grade" class="btn btn-primary">
                            Save Grade
                        </button>
                    <?php } ?>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- GRADES TABLE -->
<div class="card shadow border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Grade Records</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Assessment</th>
                        <th>Marks</th>
                        <th>Grade</th>
                        <th>Academic Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_grades) > 0) { ?>
                        <?php foreach ($all_grades as $g) {
                            list($letter, $badge) = letterGrade($g['marks']);
                        ?>
                        <tr>
                            <td><?php echo $g['grade_id']; ?></td>
                            <td><?php echo htmlspecialchars($g['admission_number']); ?></td>
                            <td><?php echo htmlspecialchars($g['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($g['class_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($g['subject_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($g['assessment_type']); ?></td>
                            <td><strong><?php echo htmlspecialchars($g['marks']); ?></strong></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $letter; ?></span></td>
                            <td><?php echo htmlspecialchars($g['academic_year']); ?></td>
                            <td>
                                <a href="grades.php?edit=<?php echo $g['grade_id']; ?>"
                                   class="btn btn-warning btn-sm">Edit</a>
                                <a href="grades.php?delete=<?php echo $g['grade_id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this grade record?');">Delete</a>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="10" class="text-center">No grade records found.</td>
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
<?php if ($edit_grade) { ?>
<script>
    var gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));
    gradeModal.show();
</script>
<?php } ?>