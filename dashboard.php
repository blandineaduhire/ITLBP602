<?php
include "config/db.php";

// =====================================
// LOGOUT
// =====================================

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// =====================================
// LOGIN CHECK
// =====================================

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// =====================================
// ROLE + FLASH MESSAGE
// =====================================

$role   = $_SESSION['role'] ?? 'guest';
$flash  = "";
$flash_type = "info";

if (isset($_GET['msg']) && $_GET['msg'] === 'forbidden') {
    $flash = "You do not have permission to access that page.";
    $flash_type = "warning";
}

// =====================================
// HELPER: safe scalar query
// =====================================

function scalar($conn, $sql, $types = "", $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_row($res);
    return $row ? $row[0] : 0;
}

// =====================================
// HELPER: safe row query
// =====================================

function fetch_all($conn, $sql, $types = "", $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
    return $out;
}

// =====================================
// STUDENT RECORD LINKED TO LOGGED-IN USER (if role=student)
// =====================================

$student = null;
if ($role === 'student') {
    $rows = fetch_all($conn,
        "SELECT * FROM students WHERE user_id = ? LIMIT 1",
        "i", [(int) $_SESSION['user_id']]);
    $student = $rows[0] ?? null;
}

// =====================================
// ROLE-SPECIFIC DATA
// =====================================

$today = date('Y-m-d');

// ---------- ADMIN ----------
$admin_stats = [];
if ($role === 'admin') {
    $admin_stats = [
        'students'      => scalar($conn, "SELECT COUNT(*) FROM students"),
        'classes'       => scalar($conn, "SELECT COUNT(*) FROM classes"),
        'subjects'      => scalar($conn, "SELECT COUNT(*) FROM subjects"),
        'teachers'      => scalar($conn, "SELECT COUNT(*) FROM users WHERE role='teacher'"),
        'student_users' => scalar($conn, "SELECT COUNT(*) FROM users WHERE role='student'"),
        'announcements' => scalar($conn, "SELECT COUNT(*) FROM announcements"),
        'grades'        => scalar($conn, "SELECT COUNT(*) FROM grades"),
        'attendance_today' => scalar($conn,
            "SELECT COUNT(*) FROM attendance WHERE attendance_date = ?",
            "s", [$today]),
    ];

    // Recent announcements
    $admin_recent_ann = fetch_all($conn,
        "SELECT a.title, a.publication_date, u.username
         FROM announcements a
         LEFT JOIN users u ON a.posted_by = u.user_id
         ORDER BY a.publication_date DESC, a.announcement_id DESC
         LIMIT 5");

    // Attendance breakdown today
    $attendance_today_rows = fetch_all($conn,
        "SELECT attendance_status, COUNT(*) AS total
         FROM attendance
         WHERE attendance_date = ?
         GROUP BY attendance_status",
        "s", [$today]);

    $attendance_today = ['Present'=>0,'Absent'=>0,'Late'=>0,'Excused'=>0];
    foreach ($attendance_today_rows as $r) {
        if (isset($attendance_today[$r['attendance_status']])) {
            $attendance_today[$r['attendance_status']] = (int) $r['total'];
        }
    }

    // Recent students
    $recent_students = fetch_all($conn,
        "SELECT s.student_id, s.admission_number, s.names, c.class_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.class_id
         ORDER BY s.student_id DESC
         LIMIT 5");
}

// ---------- TEACHER ----------
$teacher_stats = [];
if ($role === 'teacher') {
    $teacher_stats = [
        'students'      => scalar($conn, "SELECT COUNT(*) FROM students"),
        'grades'        => scalar($conn, "SELECT COUNT(*) FROM grades"),
        'attendance_today' => scalar($conn,
            "SELECT COUNT(*) FROM attendance WHERE attendance_date = ?",
            "s", [$today]),
        'classes'       => scalar($conn, "SELECT COUNT(*) FROM classes"),
    ];

    // Grade distribution across all students (useful overview)
    $grade_dist_rows = fetch_all($conn,
        "SELECT
            SUM(CASE WHEN marks >= 80 THEN 1 ELSE 0 END) AS a_count,
            SUM(CASE WHEN marks >= 70 AND marks < 80 THEN 1 ELSE 0 END) AS b_count,
            SUM(CASE WHEN marks >= 60 AND marks < 70 THEN 1 ELSE 0 END) AS c_count,
            SUM(CASE WHEN marks >= 50 AND marks < 60 THEN 1 ELSE 0 END) AS d_count,
            SUM(CASE WHEN marks < 50 THEN 1 ELSE 0 END) AS f_count
         FROM grades");
    $grade_dist = $grade_dist_rows[0] ?? ['a_count'=>0,'b_count'=>0,'c_count'=>0,'d_count'=>0,'f_count'=>0];

    // Recent grades entered
    $recent_grades = fetch_all($conn,
        "SELECT g.grade_id, g.marks, g.assessment_type, g.academic_year,
                s.names AS student_name, s.admission_number,
                sub.subject_name
         FROM grades g
         INNER JOIN students s ON g.student_id = s.student_id
         LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
         ORDER BY g.grade_id DESC
         LIMIT 5");

    // Recent announcements
    $teacher_recent_ann = fetch_all($conn,
        "SELECT a.title, a.publication_date, u.username
         FROM announcements a
         LEFT JOIN users u ON a.posted_by = u.user_id
         ORDER BY a.publication_date DESC, a.announcement_id DESC
         LIMIT 5");
}

// ---------- STUDENT ----------
$student_stats = [];
if ($role === 'student' && $student) {

    $sid = (int) $student['student_id'];

    // Grade summary
    $student_stats['grades_count'] = scalar($conn,
        "SELECT COUNT(*) FROM grades WHERE student_id = ?", "i", [$sid]);

    $student_stats['avg_mark'] = scalar($conn,
        "SELECT ROUND(AVG(marks), 1) FROM grades WHERE student_id = ?", "i", [$sid]);

    $student_stats['best_subject'] = scalar($conn,
        "SELECT sub.subject_name
         FROM grades g
         LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
         WHERE g.student_id = ?
         GROUP BY g.subject_id, sub.subject_name
         ORDER BY AVG(g.marks) DESC
         LIMIT 1",
        "i", [$sid]);

    // Attendance summary
    $student_attendance_rows = fetch_all($conn,
        "SELECT attendance_status, COUNT(*) AS total
         FROM attendance
         WHERE student_id = ?
         GROUP BY attendance_status",
        "i", [$sid]);

    $student_attendance = ['Present'=>0,'Absent'=>0,'Late'=>0,'Excused'=>0];
    foreach ($student_attendance_rows as $r) {
        if (isset($student_attendance[$r['attendance_status']])) {
            $student_attendance[$r['attendance_status']] = (int) $r['total'];
        }
    }
    $total_att = array_sum($student_attendance);
    $att_pct = $total_att > 0
        ? round(($student_attendance['Present'] / $total_att) * 100, 1)
        : 0;

    $student_stats['attendance_pct'] = $att_pct;
    $student_stats['attendance_total'] = $total_att;

    // Recent grades
    $student_recent_grades = fetch_all($conn,
        "SELECT g.marks, g.assessment_type, g.academic_year,
                sub.subject_name, sub.subject_code
         FROM grades g
         LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
         WHERE g.student_id = ?
         ORDER BY g.grade_id DESC
         LIMIT 5",
        "i", [$sid]);

    // Published announcements
    $student_announcements = fetch_all($conn,
        "SELECT a.title, a.publication_date, u.username
         FROM announcements a
         LEFT JOIN users u ON a.posted_by = u.user_id
         WHERE a.publication_date <= ?
         ORDER BY a.publication_date DESC
         LIMIT 5",
        "s", [$today]);
}

// =====================================
// GRADE LETTER HELPER
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

<div class="container">

    <!-- Flash -->
    <?php if ($flash != "") { ?>
        <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } ?>

    <!-- Page title -->
    <div class="mb-4">
        <h2 class="text-primary">Dashboard</h2>
        <p class="text-muted mb-0">
            Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            &mdash; <?php echo htmlspecialchars(ucfirst($role)); ?>
        </p>
    </div>

    <!-- ============================================= -->
    <!-- ADMIN VIEW                                    -->
    <!-- ============================================= -->
    <?php if ($role === 'admin') { ?>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Students</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $admin_stats['students']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Classes</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $admin_stats['classes']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Subjects</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $admin_stats['subjects']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Teachers</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $admin_stats['teachers']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Student Accounts</div>
                        <div class="fs-2 fw-bold text-info"><?php echo $admin_stats['student_users']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Announcements</div>
                        <div class="fs-2 fw-bold text-info"><?php echo $admin_stats['announcements']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Grade Records</div>
                        <div class="fs-2 fw-bold text-info"><?php echo $admin_stats['grades']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Attendance Today</div>
                        <div class="fs-2 fw-bold text-info"><?php echo $admin_stats['attendance_today']; ?></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4">

            <!-- Attendance today -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Attendance Today (<?php echo $today; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="badge bg-success">Present: <?php echo $attendance_today['Present']; ?></span>
                            <span class="badge bg-danger">Absent: <?php echo $attendance_today['Absent']; ?></span>
                            <span class="badge bg-warning text-dark">Late: <?php echo $attendance_today['Late']; ?></span>
                            <span class="badge bg-info text-dark">Excused: <?php echo $attendance_today['Excused']; ?></span>
                        </div>
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary mt-2">
                            Mark Attendance &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent announcements -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($admin_recent_ann) > 0) { ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($admin_recent_ann as $a) { ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($a['publication_date']); ?></small>
                                        </div>
                                        <small class="text-muted">by <?php echo htmlspecialchars($a['username'] ?? 'Unknown'); ?></small>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <p class="text-muted mb-0">No announcements yet.</p>
                        <?php } ?>
                        <a href="announcements.php" class="btn btn-sm btn-outline-primary mt-2">
                            Manage &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent students -->
            <div class="col-md-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recently Registered Students</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Admission No.</th>
                                        <th>Name</th>
                                        <th>Class</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_students) > 0) { ?>
                                        <?php foreach ($recent_students as $s) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($s['admission_number']); ?></td>
                                                <td><?php echo htmlspecialchars($s['names']); ?></td>
                                                <td><?php echo htmlspecialchars($s['class_name'] ?? '—'); ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No students yet.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    <!-- ============================================= -->
    <!-- TEACHER VIEW                                  -->
    <!-- ============================================= -->
    <?php } elseif ($role === 'teacher') { ?>

        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Students</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $teacher_stats['students']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Classes</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $teacher_stats['classes']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Grade Records</div>
                        <div class="fs-2 fw-bold text-primary"><?php echo $teacher_stats['grades']; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Marked Today</div>
                        <div class="fs-2 fw-bold text-info"><?php echo $teacher_stats['attendance_today']; ?></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4">

            <!-- Grade distribution -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Grade Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="badge bg-success">A: <?php echo (int) $grade_dist['a_count']; ?></span>
                            <span class="badge bg-primary">B: <?php echo (int) $grade_dist['b_count']; ?></span>
                            <span class="badge bg-info text-dark">C: <?php echo (int) $grade_dist['c_count']; ?></span>
                            <span class="badge bg-warning text-dark">D: <?php echo (int) $grade_dist['d_count']; ?></span>
                            <span class="badge bg-danger">F: <?php echo (int) $grade_dist['f_count']; ?></span>
                        </div>
                        <a href="grades.php" class="btn btn-sm btn-outline-primary mt-2">
                            Manage Grades &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent announcements -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($teacher_recent_ann) > 0) { ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($teacher_recent_ann as $a) { ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($a['publication_date']); ?></small>
                                        </div>
                                        <small class="text-muted">by <?php echo htmlspecialchars($a['username'] ?? 'Unknown'); ?></small>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <p class="text-muted mb-0">No announcements yet.</p>
                        <?php } ?>
                        <a href="announcements.php" class="btn btn-sm btn-outline-primary mt-2">
                            Manage &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent grades -->
            <div class="col-md-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recently Entered Grades</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Assessment</th>
                                        <th>Marks</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_grades) > 0) { ?>
                                        <?php foreach ($recent_grades as $g) {
                                            list($letter, $badge) = letterGrade($g['marks']);
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($g['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($g['subject_name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($g['assessment_type']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($g['marks']); ?></strong></td>
                                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $letter; ?></span></td>
                                            </tr>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No grades yet.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    <!-- ============================================= -->
    <!-- STUDENT VIEW                                  -->
    <!-- ============================================= -->
    <?php } elseif ($role === 'student') { ?>

        <?php if (!$student) { ?>

            <div class="alert alert-warning">
                Your account is not linked to a student record yet.
                Please contact the administrator to link your account.
            </div>

        <?php } else { ?>

            <!-- Student info card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="text-muted small">Name</div>
                            <strong><?php echo htmlspecialchars($student['names']); ?></strong>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Admission No.</div>
                            <strong><?php echo htmlspecialchars($student['admission_number']); ?></strong>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Attendance Rate</div>
                            <strong class="text-primary"><?php echo $student_stats['attendance_pct']; ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="row g-3 mb-4">

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Grades on File</div>
                            <div class="fs-2 fw-bold text-primary"><?php echo $student_stats['grades_count']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Average Mark</div>
                            <div class="fs-2 fw-bold text-primary">
                                <?php echo $student_stats['avg_mark'] ?: '—'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Best Subject</div>
                            <div class="fs-5 fw-bold text-primary">
                                <?php echo htmlspecialchars($student_stats['best_subject'] ?? '—'); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="row g-4">

                <!-- Attendance breakdown -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">My Attendance</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">
                                Total days recorded: <strong><?php echo $student_stats['attendance_total']; ?></strong>
                            </p>
                            <div class="mb-2">
                                <span class="badge bg-success">Present: <?php echo $student_attendance['Present']; ?></span>
                                <span class="badge bg-danger">Absent: <?php echo $student_attendance['Absent']; ?></span>
                                <span class="badge bg-warning text-dark">Late: <?php echo $student_attendance['Late']; ?></span>
                                <span class="badge bg-info text-dark">Excused: <?php echo $student_attendance['Excused']; ?></span>
                            </div>
                            <a href="my_attendance.php" class="btn btn-sm btn-outline-primary mt-2">
                                View All &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Latest Announcements</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($student_announcements) > 0) { ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($student_announcements as $a) { ?>
                                        <li class="list-group-item px-0">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($a['publication_date']); ?></small>
                                            </div>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p class="text-muted mb-0">No announcements yet.</p>
                            <?php } ?>
                            <a href="announcements_view.php" class="btn btn-sm btn-outline-primary mt-2">
                                View All &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent grades -->
                <div class="col-md-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">My Recent Grades</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Subject</th>
                                            <th>Assessment</th>
                                            <th>Marks</th>
                                            <th>Grade</th>
                                            <th>Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($student_recent_grades) > 0) { ?>
                                            <?php foreach ($student_recent_grades as $g) {
                                                list($letter, $badge) = letterGrade($g['marks']);
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($g['subject_name'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($g['assessment_type']); ?></td>
                                                    <td><strong><?php echo htmlspecialchars($g['marks']); ?></strong></td>
                                                    <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $letter; ?></span></td>
                                                    <td><?php echo htmlspecialchars($g['academic_year']); ?></td>
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="5" class="text-center text-muted py-3">No grades yet.</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <a href="my_grades.php" class="btn btn-sm btn-outline-primary mt-2">
                        View All Grades &rarr;
                    </a>
                </div>

            </div>

        <?php } // end student linked check ?>

    <?php } // end role switch ?>

</div>

<?php include "includes/footer.php"; ?>