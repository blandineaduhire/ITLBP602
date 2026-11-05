<?php
include "config/db.php";
include "includes/auth.php";
require_role(['student']);

$user_id = (int) $_SESSION['user_id'];

// Look up the student record linked to this user
$stmt = mysqli_prepare($conn, "SELECT student_id, admission_number, names, class_id
                               FROM students WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Optional filter: academic year
$filter_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';

include "includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">My Grades</h2>
</div>

<?php if (!$student) { ?>
    <div class="alert alert-warning">
        Your account is not linked to a student record yet. Please contact the administrator.
    </div>
<?php } else {

    // Fetch distinct years for this student
    $years = [];
    $stmt = mysqli_prepare($conn,
        "SELECT DISTINCT academic_year FROM grades WHERE student_id = ? ORDER BY academic_year DESC");
    mysqli_stmt_bind_param($stmt, "i", $student['student_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($y = mysqli_fetch_assoc($res)) $years[] = $y['academic_year'];

    // Fetch grades (with optional year filter)
    $sql = "SELECT g.*, sub.subject_name, sub.subject_code
            FROM grades g
            LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
            WHERE g.student_id = ?";
    $params = [$student['student_id']];
    $types  = "i";
    if ($filter_year !== '') {
        $sql .= " AND g.academic_year = ?";
        $params[] = $filter_year;
        $types   .= "s";
    }
    $sql .= " ORDER BY g.academic_year DESC, sub.subject_name ASC, g.assessment_type ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $grades = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Grade letter helper
    function letterGrade($marks) {
        if ($marks >= 80) return ['A', 'success'];
        if ($marks >= 70) return ['B', 'primary'];
        if ($marks >= 60) return ['C', 'info text-dark'];
        if ($marks >= 50) return ['D', 'warning text-dark'];
        return ['F', 'danger'];
    }
?>

    <!-- Student info -->
    <div class="card shadow border-0 mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Name:</strong> <?php echo htmlspecialchars($student['names']); ?>
                </div>
                <div class="col-md-4">
                    <strong>Admission No:</strong>
                    <?php echo htmlspecialchars($student['admission_number']); ?>
                </div>
                <div class="col-md-4">
                    <strong>Grades on file:</strong>
                    <?php echo count($grades); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Year filter -->
    <?php if (!empty($years)) { ?>
    <div class="card shadow border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
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
                <div class="col-md-6 d-flex gap-2">
                    <button class="btn btn-primary">Filter</button>
                    <a href="my_grades.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>

    <!-- Grades table -->
    <div class="card shadow border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0">Grade Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Assessment</th>
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Academic Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($grades) > 0) { ?>
                            <?php foreach ($grades as $g) {
                                list($letter, $badge) = letterGrade($g['marks']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['subject_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($g['subject_code'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($g['assessment_type']); ?></td>
                                <td><strong><?php echo htmlspecialchars($g['marks']); ?></strong></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $letter; ?></span></td>
                                <td><?php echo htmlspecialchars($g['academic_year']); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="6" class="text-center">No grades recorded yet.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php } // end student check ?>

<?php include "includes/footer.php"; ?>