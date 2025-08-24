<?php
require_once 'config/db.php';

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT user_id, username, password_hash, role FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $user_id, $username, $hashed_password, $role);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Agri-Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 50%, #81C784 100%);
            font-family: 'Segoe UI', sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Floating icons */
        .login-wrapper::before {
            content: '🌾';
            position: absolute;
            top: 20%;
            left: 10%;
            font-size: 3rem;
            animation: floatIcon 6s ease-in-out infinite;
            opacity: 0.3;
        }

        .login-wrapper::after {
            content: '🚛';
            position: absolute;
            bottom: 20%;
            right: 10%;
            font-size: 2.5rem;
            animation: floatIcon 8s ease-in-out infinite reverse;
            opacity: 0.3;
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            animation: fadeInUp 0.8s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        .login-header img {
            width: 80px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            animation: pulse 2s ease-in-out infinite;
        }
        .login-header h3 {
            font-weight: bold;
            color: #2d6a4f;
            font-size: 2.2rem;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .login-header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .form-control {
            padding-left: 45px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.9);
        }
        .form-control:focus {
            border-color: #2E7D32;
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
            background: rgba(255,255,255,1);
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2E7D32;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus + .input-icon {
            color: #1B5E20;
            transform: translateY(-50%) scale(1.1);
        }
        .btn-success {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 12px 24px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
        }
        .btn-success:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(46, 125, 50, 0.3);
        }
        .alert {
            font-size: 0.9rem;
        }
        a {
            text-decoration: none;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="images/logo.png" alt="Logo">
            <h3>FarmFlo</h3>
            <p class="text-muted">Sign in to manage your logistics</p>
        </div>

        <?php if (!empty($login_err)) : ?>
            <div class="alert alert-danger"><?php echo $login_err; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-user"></i></span>
                <input type="text" name="username" placeholder="Username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <div class="invalid-feedback"><?php echo $username_err; ?></div>
            </div>
            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" placeholder="Password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <div class="invalid-feedback"><?php echo $password_err; ?></div>
            </div>
            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-success btn-lg">Login</button>
            </div>
            <div class="text-center">
                <a href="forgot_password.php" class="text-muted me-3">Forgot Password?</a>
                <a href="register.php" class="text-success fw-bold">Join as Customer</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
