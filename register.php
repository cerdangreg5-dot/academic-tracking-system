<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentIdInput = $_POST['student_id'] ?? '';
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $student_id = is_string($studentIdInput) ? trim($studentIdInput) : '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($student_id === '' || !ctype_digit($student_id) || $username === '' || $password === '') {
        $error = 'Please enter your student ID, username, and password.';
    } elseif (strlen($username) > 50 || ctype_digit($username) || strcasecmp($username, 'admin') === 0) {
        $error = 'Choose a username of up to 50 characters that is not numeric or reserved.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters long.';
    } else {
        $studentStmt = $pdo->prepare("SELECT student_id FROM track_students WHERE student_id = ?");
        $studentStmt->execute([(int) $student_id]);
        $student = $studentStmt->fetch();

        if (!$student) {
            $error = 'No student record was found for that ID.';
        } else {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM system_users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = 'Username is already taken. Please choose another.';
            } else {
                $checkStudentStmt = $pdo->prepare("SELECT COUNT(*) FROM system_users WHERE student_id = ?");
                $checkStudentStmt->execute([(int) $student_id]);
                if ($checkStudentStmt->fetchColumn() > 0) {
                    $error = 'This student already has an account. Please contact the administrator.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO system_users (student_id, username, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([(int) $student_id, $username, $password_hash]);
                    header('Location: login.php?registered=success');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Academic Tracking System</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            display: flex; justify-content: center; align-items: center;
            height: 100vh; overflow: hidden;
        }
        .login-wrapper {
            display: flex; width: 900px; max-width: 95%; height: 560px;
            background: #ffffff; border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); overflow: hidden;
        }
        .login-brand {
            flex: 1; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white; padding: 50px; display: flex; flex-direction: column; justify-content: space-between;
        }
        .brand-logo h1 { margin: 0 0 10px 0; font-size: 28px; font-weight: 700; }
        .brand-logo p { margin: 0; color: #93c5fd; font-size: 14px; line-height: 1.5; }
        .brand-footer { font-size: 12px; color: #bfdbfe; }
        .login-form-container {
            flex: 1; padding: 40px; display: flex; flex-direction: column; justify-content: center; background: #ffffff;
        }
        .form-header h2 { margin: 0 0 8px 0; color: #1e293b; font-size: 24px; font-weight: 600; }
        .form-header p { margin: 0 0 20px 0; color: #64748b; font-size: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; color: #475569; font-weight: 500; font-size: 13px; }
        .form-group input {
            width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 14px; outline: none; background: #f8fafc; color: #1e293b;
        }
        .form-group input:focus { border-color: #2563eb; background: #ffffff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .btn-submit {
            width: 100%; padding: 12px; background-color: #10b981; color: white; border: none;
            border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 6px;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.25);
        }
        .btn-submit:hover { background-color: #059669; }
        .alert-error {
            background-color: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px;
            font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca; text-align: center;
        }
        .register-link { text-align: center; margin-top: 16px; font-size: 13px; color: #64748b; }
        .register-link a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
        @media (max-width: 768px) { .login-brand { display: none; } .login-wrapper { max-width: 400px; height: auto; } }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-brand">
            <div class="brand-logo">
                <h1>Create Account</h1>
                <p>Create your student account using the ID number assigned to your student record.</p>
            </div>
            <div class="brand-footer">&copy; 2026 Academic Tracking System</div>
        </div>

        <div class="login-form-container">
            <div class="form-header">
                <h2>Get Started</h2>
                <p>Your student ID and password will both work when you sign in.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" inputmode="numeric" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required placeholder="For example: 0001">
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username" placeholder="Choose a username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                </div>
                <button type="submit" class="btn-submit">Register Account</button>
            </form>

            <div class="register-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
</body>
</html>
