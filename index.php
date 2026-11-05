
<?php
include "config/db.php";

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

// Login process
if (isset($_POST['login'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "s", $username);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboard.php");
            exit();

        } else {

            $error = "Incorrect password!";

        }

    } else {

        $error = "Username does not exist!";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LOL School - Login</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- CSS in the same page -->
    <style>

        body {
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background-color: #0d6efd;
        }

        .login-container {
            max-width: 430px;
            margin: 70px auto;
        }

        .login-card {
            border: none;
            border-radius: 12px;
        }

        .login-title {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }

        .btn-login {
            width: 100%;
            padding: 10px;
        }

        footer {
            background-color: #212529;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 80px;
        }

    </style>

</head>

<body>

<!-- Navigation bar -->
<nav class="navbar navbar-dark">

    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            LOL SCHOOL SMS
        </a>

        <a href="register.php" class="btn btn-light">
            Register
        </a>

    </div>

</nav>


<!-- Login form -->
<div class="container">

    <div class="login-container">

        <div class="card shadow login-card">

            <div class="login-title">

                <h3>LOL School Login</h3>

                <p class="mb-0">
                    Student Management System
                </p>

            </div>

            <div class="card-body p-4">

                <?php if ($error != "") { ?>

                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php } ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Username
                        </label>

                        <input type="text"
                               name="username"
                               class="form-control"
                               placeholder="Enter username"
                               required>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Password
                        </label>

                        <input type="password"
                               name="password"
                               class="form-control"
                               placeholder="Enter password"
                               required>

                    </div>


                    <button type="submit"
                            name="login"
                            class="btn btn-primary btn-login">

                        Login

                    </button>

                </form>

                <hr>

                <p class="text-center mb-0">

                    Don't have an account?

                    <a href="register.php">
                        Register here
                    </a>

                </p>

            </div>

        </div>

    </div>

</div>


<!-- Independent footer -->
<footer>

    <p class="mb-0">
        &copy; 2026 LOL School Student Management System.
    </p>

    <small>Nyamagabe District, Rwanda</small>

</footer>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
