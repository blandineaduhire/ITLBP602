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
// HANDLE FLASH MESSAGES
// =====================================

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'saved':
            $message = "Attendance saved successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "Attendance updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "Attendance record deleted!";
            $message_type = "success";
            break;
        case 'error':
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
            break;
    }
}

// =====================================
// DELETE ATTENDANCE RECORD
// =====================================

if (isset($_GET['delete'])) {

    $attendance_id = (int) $_GET['delete'];

    $sql = "DELETE FROM attendance WHERE attendance_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $attendance_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: attendance.php?msg=deleted");
        exit();
    } else {
        header("Location: attendance.php?msg=error");
        exit();
    }
}

// =====================================
// SAVE / UPDATE ATTENDANCE (BULK)
// =====================================

if (isset($_POST['save_attendance'])) {

    $attendance_date = $_POST['attendance_date'];
    $class_id        = (int) $_POST['class_id'];
    $statuses        = $_POST['status'] ?? [];   // [student_id => status]

    if (empty($statuses)) {
        header("Location: attendance.php?msg=error");
        exit();
    }

    // Upsert each student's attendance
    // Requires UNIQUE KEY (student_id, attendance_date)
    $sql = "INSERT INTO attendance (student_id, attendance_date, attendance_status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE attendance_status = VALUES(attendance_status)";

    $stmt = mysqli_prepare($conn, $sql);

    $all_ok = true;

    foreach ($statuses as $student_id => $status) {

        $student_id = (int) $student_id;
        $status     = trim($status);

        // Only accept known statuses
        if (!in_array($status, ['Present', 'Absent', 'Late', 'Excused'], true)) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, "iss", $student_id, $attendance_date, $status);

        if (!mysqli_stmt_execute($stmt)) {
            $all_ok = false;
        }
    }

    if ($all_ok) {
        // Keep the same class/date on the page after save
        header("Location: attendance.php?class_id={$class_id}&date={$attendance_date}&msg=saved");
        exit();
    } else {
        header("Location: attendance.php?msg=error");
        exit();
    }
}

// =====================================
// FILTERS (for viewing + marking)
// =====================================

$filter_class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$filter_date     = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = date('Y-m-d');
}

// =====================================
// GET ALL CLASSES
// =====================================

$class_sql    = "SELECT * FROM classes ORDER BY class_name ASC";
$class_result = mysqli_query($conn, $class_sql);

$classes = [];
while ($c = mysqli_fetch_assoc($class_result)) {
    $classes[] = $c;
}

// =====================================
// GET STUDENTS OF SELECTED CLASS + THEIR ATTENDANCE FOR THE DATE
// =====================================

$students = [];

if ($filter_class_id > 0) {

    $sql = "SELECT s.student_id,
                   s.admission_number,
                   s.names,
                   c.class_name,
                   a.attendance_id,
                   a.attendance_status
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.class_id
            LEFT JOIN attendance a
                   ON a.student_id = s.student_id
                  AND a.attendance_date = ?
            WHERE s.class_id = ?
            ORDER BY s.names ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $filter_date, $filter_class_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}

// =====================================
// SUMMARY COUNTS FOR CURRENT VIEW
// =====================================

$summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Unmarked' => 0];

foreach ($students as $s) {
    if (empty($s['attendance_status'])) {
        $summary['Unmarked']++;
    } else {
        $summary[$s['attendance_status']] = ($summary[$s['attendance_status']] ?? 0) + 1;
    }
}

// =====================================
// RECENT ATTENDANCE LOG (optional, last 50)
// =====================================

$recent_sql = "SELECT a.attendance_id,
                      a.attendance_date,
                      a.attendance_status,
                      s.admission_number,
                      s.names,
                      c.class_name
               FROM attendance a
               INNER JOIN students s ON a.student_id = s.student_id
               LEFT JOIN classes c ON s.class_id = c.class_id
               ORDER BY a.attendance_id DESC
               LIMIT 50";
$recent_result = mysqli_query($conn, $recent_sql);

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">Attendance Management</h2>
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
        <h5 class="mb-0">Select Class & Date</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-5">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $class) { ?>
                        <option value="<?php echo $class['class_id']; ?>"
                            <?php if ($filter_class_id == $class['class_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control"
                       value="<?php echo htmlspecialchars($filter_date); ?>" required>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    Load Students
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($filter_class_id > 0) { ?>

    <!-- SUMMARY BADGES -->
    <div class="mb-3">
        <span class="badge bg-success">Present: <?php echo $summary['Present']; ?></span>
        <span class="badge bg-danger">Absent: <?php echo $summary['Absent']; ?></span>
        <span class="badge bg-warning text-dark">Late: <?php echo $summary['Late']; ?></span>
        <span class="badge bg-info text-dark">Excused: <?php echo $summary['Excused']; ?></span>
        <span class="badge bg-secondary">Unmarked: <?php echo $summary['Unmarked']; ?></span>
    </div>

    <!-- ATTENDANCE FORM -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                Mark Attendance —
                <?php
                    $class_name = '';
                    foreach ($classes as $c) {
                        if ($c['class_id'] == $filter_class_id) { $class_name = $c['class_name']; break; }
                    }
                    echo htmlspecialchars($class_name);
                ?>
                (<?php echo htmlspecialchars($filter_date); ?>)
            </h5>

            <div>
                <button type="button" class="btn btn-sm btn-outline-success"
                        onclick="markAll('Present')">All Present</button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="markAll('Absent')">All Absent</button>
            </div>
        </div>

        <div class="card-body">
            <?php if (count($students) > 0) { ?>

            <form method="POST">

                <input type="hidden" name="class_id" value="<?php echo $filter_class_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($filter_date); ?>">

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>Admission No.</th>
                                <th>Student Names</th>
                                <th style="min-width: 380px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($students as $s) {
                                $current = $s['attendance_status'] ?? '';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($s['admission_number']); ?></td>
                                <td><?php echo htmlspecialchars($s['names']); ?></td>
                                <td>
                                    <?php
                                    $options = ['Present', 'Absent', 'Late', 'Excused'];
                                    foreach ($options as $opt) {
                                        $checked = ($current === $opt) ? 'checked' : '';
                                        $id = 'st_' . $s['student_id'] . '_' . $opt;
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input status-radio"
                                               type="radio"
                                               name="status[<?php echo $s['student_id']; ?>]"
                                               id="<?php echo $id; ?>"
                                               value="<?php echo $opt; ?>"
                                               <?php echo $checked; ?>>
                                        <label class="form-check-label" for="<?php echo $id; ?>">
                                            <?php echo $opt; ?>
                                        </label>
                                    </div>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" name="save_attendance" class="btn btn-success">
                    Save Attendance
                </button>
            </form>

            <?php } else { ?>
                <div class="alert alert-info mb-0">
                    No students found in this class. Please add students first.
                </div>
            <?php } ?>
        </div>
    </div>

<?php } else { ?>
    <div class="alert alert-info">
        Please select a class and date to mark attendance.
    </div>
<?php } ?>

<!-- RECENT ATTENDANCE LOG -->
<div class="card shadow border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Recent Attendance Records</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Admission No.</th>
                        <th>Student Names</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recent_result) > 0) { ?>
                        <?php while ($r = mysqli_fetch_assoc($recent_result)) {
                            $badge = 'secondary';
                            if ($r['attendance_status'] === 'Present') $badge = 'success';
                            elseif ($r['attendance_status'] === 'Absent') $badge = 'danger';
                            elseif ($r['attendance_status'] === 'Late') $badge = 'warning text-dark';
                            elseif ($r['attendance_status'] === 'Excused') $badge = 'info text-dark';
                        ?>
                        <tr>
                            <td><?php echo $r['attendance_id']; ?></td>
                            <td><?php echo htmlspecialchars($r['attendance_date']); ?></td>
                            <td><?php echo htmlspecialchars($r['admission_number']); ?></td>
                            <td><?php echo htmlspecialchars($r['names']); ?></td>
                            <td><?php echo htmlspecialchars($r['class_name'] ?? '-'); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($r['attendance_status']); ?></span></td>
                            <td>
                                <a href="attendance.php?delete=<?php echo $r['attendance_id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this attendance record?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7" class="text-center">No attendance records yet.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function markAll(status) {
    document.querySelectorAll('.status-radio').forEach(function (radio) {
        if (radio.value === status) {
            radio.checked = true;
        }
    });
}
</script>

<?php
include "includes/footer.php";
?>