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
// GET STUDENT FOR EDITING (MUST RUN FIRST)
// =====================================

$edit_student = null;

if (isset($_GET['edit'])) {

    $student_id = (int) $_GET['edit'];

    $sql = "SELECT * FROM students WHERE student_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "i", $student_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $edit_student = mysqli_fetch_assoc($result);
}

// =====================================
// ADD NEW STUDENT
// =====================================

if (isset($_POST['add_student'])) {

    $admission_number = trim($_POST['admission_number']);
    $names            = trim($_POST['names']);
    $gender           = $_POST['gender'];
    $date_of_birth    = $_POST['date_of_birth'];
    $class_id         = $_POST['class_id'];
    $phone_number     = trim($_POST['phone_number']);
    $address          = trim($_POST['address']);

    // Linked user account (optional)
    $user_id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null;

    // Safety: don't allow a user to be linked to two students
    if ($user_id !== null) {
        $chk = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM students WHERE user_id = ?");
        mysqli_stmt_bind_param($chk, "i", $user_id);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));

        if ($chk_res['total'] > 0) {
            $message = "That user account is already linked to another student.";
            $message_type = "danger";
            $user_id = null;   // prevent insert with bad data
        }
    }

    if ($message === "") {
        $sql = "INSERT INTO students
                (admission_number, names, gender, date_of_birth, class_id, phone_number, address, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        // Types: s s s s i s s i  (user_id may be NULL — bind "i" accepts null in mysqli 8+)
        mysqli_stmt_bind_param(
            $stmt,
            "ssssissi",
            $admission_number,
            $names,
            $gender,
            $date_of_birth,
            $class_id,
            $phone_number,
            $address,
            $user_id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: students.php?msg=added");
            exit();
        } else {
            $message = "Error! Admission number may already exist.";
            $message_type = "danger";
        }
    }
}

// =====================================
// UPDATE STUDENT
// =====================================

if (isset($_POST['update_student'])) {

    $student_id       = (int) $_POST['student_id'];
    $admission_number = trim($_POST['admission_number']);
    $names            = trim($_POST['names']);
    $gender           = $_POST['gender'];
    $date_of_birth    = $_POST['date_of_birth'];
    $class_id         = $_POST['class_id'];
    $phone_number     = trim($_POST['phone_number']);
    $address          = trim($_POST['address']);

    // Linked user account (optional) — empty string means "no link"
    $user_id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null;

    // Safety: don't allow a user to be linked to two students
    if ($user_id !== null) {
        $chk = mysqli_prepare($conn,
            "SELECT COUNT(*) AS total FROM students WHERE user_id = ? AND student_id <> ?");
        mysqli_stmt_bind_param($chk, "ii", $user_id, $student_id);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));

        if ($chk_res['total'] > 0) {
            $message = "That user account is already linked to another student.";
            $message_type = "danger";
            $user_id = null;
        }
    }

    if ($message === "") {
        $sql = "UPDATE students SET
                admission_number = ?,
                names = ?,
                gender = ?,
                date_of_birth = ?,
                class_id = ?,
                phone_number = ?,
                address = ?,
                user_id = ?
                WHERE student_id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        // Types: s s s s i s s i i
        mysqli_stmt_bind_param(
            $stmt,
            "ssssissii",
            $admission_number,
            $names,
            $gender,
            $date_of_birth,
            $class_id,
            $phone_number,
            $address,
            $user_id,
            $student_id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: students.php?msg=updated");
            exit();
        } else {
            $message = "Error updating student!";
            $message_type = "danger";
        }
    }
}

// =====================================
// DELETE STUDENT
// =====================================

if (isset($_GET['delete'])) {

    $student_id = (int) $_GET['delete'];

    $sql = "DELETE FROM students WHERE student_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {

        header("Location: students.php?msg=deleted");
        exit();

    } else {

        $message = "Unable to delete student!";
        $message_type = "danger";

    }
}

// =====================================
// HANDLE FLASH MESSAGES FROM REDIRECTS
// =====================================

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = "Student registered successfully!";
            $message_type = "success";
            break;
        case 'updated':
            $message = "Student information updated successfully!";
            $message_type = "success";
            break;
        case 'deleted':
            $message = "Student deleted successfully!";
            $message_type = "success";
            break;
    }
}

// =====================================
// GET ALL CLASSES
// =====================================

$class_sql = "SELECT * FROM classes ORDER BY class_name ASC";
$class_result = mysqli_query($conn, $class_sql);

$classes = [];
while ($c = mysqli_fetch_assoc($class_result)) {
    $classes[] = $c;
}

// =====================================
// GET AVAILABLE STUDENT USER ACCOUNTS
// (users with role='student', not yet linked to another student)
// =====================================

$student_users = [];
$user_sql = "SELECT u.user_id, u.username
             FROM users u
             LEFT JOIN students s ON s.user_id = u.user_id
             WHERE u.role = 'student'
             ORDER BY u.username ASC";
$user_res = mysqli_query($conn, $user_sql);
while ($u = mysqli_fetch_assoc($user_res)) {
    $student_users[] = $u;
}

// =====================================
// GET ALL STUDENTS (with linked username)
// =====================================

$student_sql = "SELECT students.*,
                       classes.class_name,
                       u.username AS linked_username
                FROM students
                LEFT JOIN classes c ON students.class_id = c.class_id
                LEFT JOIN users u ON students.user_id = u.user_id
                ORDER BY students.student_id DESC";

// Fix: original query used `classes.class_name` but joined without alias.
// Rewritten below with proper alias `c`.
$student_sql = "SELECT students.*,
                       c.class_name,
                       u.username AS linked_username
                FROM students
                LEFT JOIN classes c ON students.class_id = c.class_id
                LEFT JOIN users u ON students.user_id = u.user_id
                ORDER BY students.student_id DESC";

$student_result = mysqli_query($conn, $student_sql);

// =====================================
// INCLUDE HEADER
// =====================================

include "includes/header.php";
?>

<!-- PAGE TITLE -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="text-primary">Students Management</h2>

    <button class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#studentModal">

        + Add New Student

    </button>

</div>

<!-- SUCCESS OR ERROR MESSAGE -->

<?php if ($message != "") { ?>

    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">

        <?php echo htmlspecialchars($message); ?>

        <button type="button" class="btn-close"
                data-bs-dismiss="alert"></button>

    </div>

<?php } ?>

<!-- STUDENT REGISTRATION MODAL -->

<div class="modal fade" id="studentModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">

                    <?php echo $edit_student ? "Update Student" : "Register New Student"; ?>

                </h5>

                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <form method="POST">

                    <?php if ($edit_student) { ?>

                        <input type="hidden" name="student_id"
                               value="<?php echo $edit_student['student_id']; ?>">

                    <?php } ?>

                    <div class="row">

                        <!-- Admission Number -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Admission Number</label>

                            <input type="text"
                                   name="admission_number"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($edit_student['admission_number'] ?? ''); ?>"
                                   required>

                        </div>


                        <!-- Student Names -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Student Names</label>

                            <input type="text"
                                   name="names"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($edit_student['names'] ?? ''); ?>"
                                   required>

                        </div>


                        <!-- Gender -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Gender</label>

                            <select name="gender" class="form-select" required>

                                <option value="">Select Gender</option>

                                <option value="Male"
                                <?php if (($edit_student['gender'] ?? '') == 'Male') echo 'selected'; ?>>
                                    Male
                                </option>

                                <option value="Female"
                                <?php if (($edit_student['gender'] ?? '') == 'Female') echo 'selected'; ?>>
                                    Female
                                </option>

                            </select>

                        </div>


                        <!-- Date of Birth -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Date of Birth</label>

                            <input type="date"
                                   name="date_of_birth"
                                   class="form-control"
                                   value="<?php echo $edit_student['date_of_birth'] ?? ''; ?>"
                                   required>

                        </div>


                        <!-- Class -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Class</label>

                            <select name="class_id" class="form-select" required>

                                <option value="">Select Class</option>

                                <?php foreach ($classes as $class) { ?>

                                    <option value="<?php echo $class['class_id']; ?>"
                                    <?php if (($edit_student['class_id'] ?? '') == $class['class_id']) echo 'selected'; ?>>

                                        <?php echo htmlspecialchars($class['class_name']); ?>

                                    </option>

                                <?php } ?>

                            </select>

                        </div>


                        <!-- Phone Number -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">Phone Number</label>

                            <input type="text"
                                   name="phone_number"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($edit_student['phone_number'] ?? ''); ?>">

                        </div>


                        <!-- Linked User Account (NEW) -->

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Linked User Account
                                <small class="text-muted">(for student login)</small>
                            </label>

                            <?php if (count($student_users) > 0) { ?>

                                <select name="user_id" class="form-select">

                                    <option value="">— Not linked —</option>

                                    <?php foreach ($student_users as $u) {
                                        $selected = (($edit_student['user_id'] ?? '') == $u['user_id']) ? 'selected' : '';
                                    ?>

                                        <option value="<?php echo $u['user_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($u['username']); ?>
                                        </option>

                                    <?php } ?>

                                </select>

                                <small class="text-muted">
                                    Only accounts with role <strong>student</strong> are listed.
                                </small>

                            <?php } else { ?>

                                <div class="alert alert-warning mb-0 py-2 px-3 small">
                                    No <strong>student</strong> user accounts exist yet.
                                    <a href="register.php" class="alert-link">Create one</a>
                                    (make sure its role is set to <code>student</code>).
                                </div>

                            <?php } ?>

                        </div>


                        <!-- Address -->

                        <div class="col-md-12 mb-3">

                            <label class="form-label">Address</label>

                            <textarea name="address"
                                      class="form-control"
                                      rows="2"><?php echo htmlspecialchars($edit_student['address'] ?? ''); ?></textarea>

                        </div>

                    </div>

                    <?php if ($edit_student) { ?>

                        <button type="submit" name="update_student"
                                class="btn btn-success">

                            Update Student

                        </button>

                        <a href="students.php" class="btn btn-secondary">
                            Cancel
                        </a>

                    <?php } else { ?>

                        <button type="submit" name="add_student"
                                class="btn btn-primary">

                            Save Student

                        </button>

                    <?php } ?>

                </form>

            </div>

        </div>

    </div>

</div>


<!-- STUDENTS TABLE -->

<div class="card shadow border-0">

    <div class="card-header bg-white">

        <h5 class="mb-0">Registered Students</h5>

    </div>

    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-primary">

                    <tr>

                        <th>ID</th>
                        <th>Admission No.</th>
                        <th>Student Names</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Class</th>
                        <th>Phone</th>
                        <th>Linked Account</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($student_result) > 0) { ?>

                        <?php while ($student = mysqli_fetch_assoc($student_result)) { ?>

                            <tr>

                                <td><?php echo $student['student_id']; ?></td>

                                <td><?php echo htmlspecialchars($student['admission_number']); ?></td>

                                <td><?php echo htmlspecialchars($student['names']); ?></td>

                                <td><?php echo htmlspecialchars($student['gender']); ?></td>

                                <td><?php echo htmlspecialchars($student['date_of_birth']); ?></td>

                                <td><?php echo htmlspecialchars($student['class_name'] ?? 'Not assigned'); ?></td>

                                <td><?php echo htmlspecialchars($student['phone_number'] ?? ''); ?></td>

                                <td>
                                    <?php if (!empty($student['linked_username'])) { ?>
                                        <span class="badge bg-success">
                                            <?php echo htmlspecialchars($student['linked_username']); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary">Not linked</span>
                                    <?php } ?>
                                </td>

                                <td>

                                    <a href="students.php?edit=<?php echo $student['student_id']; ?>"
                                       class="btn btn-warning btn-sm">

                                        Edit

                                    </a>

                                    <a href="students.php?delete=<?php echo $student['student_id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this student?');">

                                        Delete

                                    </a>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="9" class="text-center">

                                No students registered yet.

                            </td>

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

<!-- Automatically open modal when editing -->

<?php if ($edit_student) { ?>

<script>
    var studentModal = new bootstrap.Modal(
        document.getElementById('studentModal')
    );

    studentModal.show();
</script>

<?php } ?>