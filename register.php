
<?php
include "config/db.php";

$message = "";
$message_type = "";

if (isset($_POST['register'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password != $confirm_password) {

        $message = "Passwords do not match!";
        $message_type = "danger";

    } else {

        // Check if username already exists
        $check = "SELECT user_id FROM users WHERE username = ?";

        $stmt = mysqli_prepare($conn, $check);

        mysqli_stmt_bind_param($stmt, "s", $username);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {

            $message = "Username already exists!";
            $message_type = "danger";

        } else {

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Default role
            $role = "student";

            // Insert user
            $sql = "INSERT INTO users (username, password, role)
                    VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $username,
                $hashed_password,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {

                $message = "Account created successfully! You can now login.";
                $message_type = "success";

            } else {

                $message = "Registration failed!";
                $message_type = "danger";

            }
        }
    }
}

include "includes/header.php";
?>

<div class="row justify-content-center">

    <div class="col-md-6 col-lg-5">

        <div class="card shadow border-0">

            <div class="card-header bg-success text-white text-center p-3">

                <h3>Create Account</h3>

                <p class="mb-0">
                    LOL School Student Management System
                </p>

            </div>

            <div class="card-body p-4">

                <?php if ($message != "") { ?>

                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>

                <?php } ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">Username</label>

                        <input type="text"
                               name="username"
                               class="form-control"
                               placeholder="Enter username"
                               required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Password</label>

                        <input type="password"
                               name="password"
                               class="form-control"
                               minlength="6"
                               required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Confirm Password</label>

                        <input type="password"
                               name="confirm_password"
                               class="form-control"
                               minlength="6"
                               required>

                    </div>

                    <button type="submit"
                            name="register"
                            class="btn btn-success w-100">

                        Register

                    </button>

                </form>

                <hr>

                <p class="text-center mb-0">

                    Already have an account?

                    <a href="index.php">Login here</a>

                </p>

            </div>

        </div>

    </div>

</div>

<?php include "includes/footer.php"; ?>
