<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$input_value = '';

// Check if there is a success message from registration
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = 'Account successfully created! Please sign in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputInput = $_POST['username_or_id'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $input_value = is_string($inputInput) ? trim($inputInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($input_value !== '' && $password !== '') {
        if (ctype_digit($input_value)) {
            $stmt = $pdo->prepare("SELECT user_id, student_id, username, password_hash FROM system_users WHERE username = ?");
            $stmt->execute([$input_value]);
            $user = $stmt->fetch();

            if (!$user && (int) $input_value > 0) {
                $stmt = $pdo->prepare("SELECT user_id, student_id, username, password_hash FROM system_users WHERE student_id = ?");
                $stmt->execute([(int) $input_value]);
                $user = $stmt->fetch();
            }
        } else {
            $stmt = $pdo->prepare("SELECT user_id, student_id, username, password_hash FROM system_users WHERE username = ?");
            $stmt->execute([$input_value]);
            $user = $stmt->fetch();
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['student_id'] = $user['student_id'] !== null ? (int) $user['student_id'] : null;
            $_SESSION['role'] = $user['student_id'] === null ? 'Admin' : 'User';

            header('Location: ' . ($user['student_id'] === null ? 'index.php' : 'view_record.php'));
            exit;
        }
        $error = 'Invalid username or student ID, or password.';
    } elseif ($input_value !== '') {
        $error = 'Please enter your password.';
    } else {
        $error = 'Please enter your username or student ID.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Portal - Sign In / Lookup</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-wrapper {
            display: flex;
            width: 900px;
            max-width: 95%;
            min-height: 560px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        /* Left Brand Panel */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .login-brand::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.1), transparent 60%);
            pointer-events: none;
        }
        .brand-logo h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .brand-logo p {
            margin: 0;
            color: #93c5fd;
            font-size: 14px;
            line-height: 1.5;
        }
        .brand-footer {
            font-size: 12px;
            color: #bfdbfe;
        }
        /* Right Form Panel */
        .login-form-container {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            overflow-y: auto;
            max-height: 560px;
        }
        .form-header h2 {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 24px;
            font-weight: 600;
        }
        .form-header p {
            margin: 0 0 20px 0;
            color: #64748b;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-weight: 500;
            font-size: 13px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
            background: #f8fafc;
            color: #1e293b;
        }
        .form-group input:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .password-wrapper {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            display: flex;
            align-items: center;
        }
        .toggle-password:hover {
            color: #2563eb;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            margin-top: 6px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }
        .btn-submit:hover {
            background-color: #1d4ed8;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            border: 1px solid #fecaca;
            text-align: center;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            border: 1px solid #a7f3d0;
            text-align: center;
        }
        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #64748b;
        }
        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .login-brand {
                display: none;
            }
            .login-wrapper {
                max-width: 400px;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-brand">
            <div class="brand-logo">
                <h1>Academic Portal</h1>
                <p>Advanced student records, performance tracking, and grade management system.</p>
            </div>
            <div class="brand-footer">
                &copy; 2026 Academic Tracking System
            </div>
        </div>

        <div class="login-form-container">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in with your username or student ID and the password you created.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username_or_id">Username or Student ID</label>
                    <input type="text" id="username_or_id" name="username_or_id" value="<?= htmlspecialchars($input_value) ?>" required autocomplete="username" placeholder="Enter username or student ID (e.g. 0001)">
                </div>
                <div class="form-group" id="passwordGroup">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle-password" id="togglePasswordBtn" title="Show/Hide Password">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="register.php">Create an Account</a>
            </div>
        </div>
    </div>

    <script>
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                if (type === 'text') {
                    eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            });
        }
    </script>
</body>
</html>