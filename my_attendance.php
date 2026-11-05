<?php
include "config/db.php";
include "includes/auth.php";
require_role(['student']);

$user_id = (int) $_SESSION['user_id'];

// Find the student record for this user
$stmt = mysqli_prepare($conn, "SELECT student_id, admission_number, names
                               FROM students WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Optional date range filter
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

include "includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">My Attendance</h2>
</div>

<?php if (!$student) { ?>
    <div class="alert alert-warning">
        Your account is not linked to a student record yet. Please contact the administrator.
    </div>
<?php } else {

    // Build query
    $sql = "SELECT attendance_date, attendance_status
            FROM attendance
            WHERE student_id = ?";
    $params = [$student['student_id']];
    $types  = "i";

    if ($from !== '') {
        $sql .= " AND attendance_date >= ?";
        $params[] = $from;
        $types   .= "s";
    }
    if ($to !== '') {
        $sql .= " AND attendance_date <= ?";
        $params[] = $to;
        $types   .= "s";
    }
    $sql .= " ORDER BY attendance_date DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $records = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Summary
    $counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
    foreach ($records as $r) {
        if (isset($counts[$r['attendance_status']])) {
            $counts[$r['attendance_status']]++;
        }
    }
    $total = array_sum($counts);
    $attendance_pct = $total > 0 ? round(($counts['Present'] / $total) * 100, 1) : 0;

    // Status badge helper
    function statusBadge($status) {
        switch ($status) {
            case 'Present': return 'success';
            case 'Absent':  return 'danger';
            case 'Late':    return 'warning text-dark';
            case 'Excused': return 'info text-dark';
            default:        return 'secondary';
        }
    }
?>

    <!-- Student info -->
    <div class="card shadow border-0 mb-4">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <strong><?php echo htmlspecialchars($student['names']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($student['admission_number']); ?></small>
                </div>
                <div class="col-md-4">
                    <div class="fs-4 fw-bold text-primary"><?php echo $attendance_pct; ?>%</div>
                    <small class="text-muted">Attendance rate</small>
                </div>
                <div class="col-md-4">
                    <div class="fs-4 fw-bold"><?php echo $total; ?></div>
                    <small class="text-muted">Total days recorded</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary badges -->
    <div class="mb-3">
        <span class="badge bg-success">Present: <?php echo $counts['Present']; ?></span>
        <span class="badge bg-danger">Absent: <?php echo $counts['Absent']; ?></span>
        <span class="badge bg-warning text-dark">Late: <?php echo $counts['Late']; ?></span>
        <span class="badge bg-info text-dark">Excused: <?php echo $counts['Excused']; ?></span>
    </div>

    <!-- Date range filter -->
    <div class="card shadow border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from" class="form-control"
                           value="<?php echo htmlspecialchars($from); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to" class="form-control"
                           value="<?php echo htmlspecialchars($to); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary">Filter</button>
                    <a href="my_attendance.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Records table -->
    <div class="card shadow border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0">Attendance Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0) {
                            $i = 1;
                            foreach ($records as $r) {
                                $badge = statusBadge($r['attendance_status']);
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($r['attendance_date']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo htmlspecialchars($r['attendance_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php } } else { ?>
                            <tr><td colspan="3" class="text-center">No attendance records yet.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php } // end student check ?>

<?php include "includes/footer.php"; ?>