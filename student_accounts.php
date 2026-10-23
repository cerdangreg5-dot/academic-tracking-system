<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (strcasecmp($_SESSION['role'] ?? 'User', 'Admin') !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Your session token is invalid. Please reload the page and try again.';
        $messageType = 'error';
    } elseif (!$userId || !is_string($action)) {
        $message = 'The selected student account is invalid.';
        $messageType = 'error';
    } elseif ($action === 'delete_account') {
        $deleteStmt = $pdo->prepare(
            "DELETE FROM system_users WHERE user_id = ? AND student_id IS NOT NULL"
        );
        $deleteStmt->execute([$userId]);

        if ($deleteStmt->rowCount() === 1) {
            $message = 'Student account deleted. The student record and academic data were kept.';
        } else {
            $message = 'The selected student account was not found.';
            $messageType = 'error';
        }
    } elseif ($action !== 'set_password') {
        $message = 'The requested account action is invalid.';
        $messageType = 'error';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        if (!is_string($newPassword) || strlen($newPassword) < 8) {
            $message = 'Select a student account and enter a password of at least 8 characters.';
            $messageType = 'error';
        } else {
            $updateStmt = $pdo->prepare(
                "UPDATE system_users SET password_hash = ? WHERE user_id = ? AND student_id IS NOT NULL"
            );
            $updateStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

            if ($updateStmt->rowCount() === 1) {
                $message = 'Password updated. Give the new password to the student securely.';
            } else {
                $message = 'The selected student account was not found.';
                $messageType = 'error';
            }
        }
    }
}

$studentsStmt = $pdo->query("
    SELECT u.user_id, u.username, s.student_id, s.first_name, s.last_name
    FROM system_users u
    JOIN track_students s ON s.student_id = u.student_id
    ORDER BY s.student_id
");
$studentAccounts = $studentsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Accounts - Academic Tracking System</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: #f8fafc; color: #0f172a; font: 14px 'Segoe UI', Tahoma, sans-serif; }
        main { max-width: 1100px; margin: 0 auto; }
        header, .panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        h1 { margin: 0; font-size: 22px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        p { color: #475569; line-height: 1.5; }
        a, button { color: white; background: #4f46e5; border: 0; border-radius: 6px; padding: 9px 12px; text-decoration: none; font-weight: 600; cursor: pointer; }
        .notice { padding: 12px; border-radius: 8px; background: #d1fae5; color: #065f46; margin-bottom: 16px; }
        .notice.error { background: #fee2e2; color: #991b1b; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { color: #475569; font-size: 12px; text-transform: uppercase; }
        .password-form { display: flex; gap: 8px; }
        .password-form input { min-width: 180px; flex: 1; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .password-note { font-size: 13px; color: #64748b; }
        .delete-button { background: #dc2626; }
        .delete-button:hover { background: #b91c1c; }
        @media (max-width: 600px) { header { align-items: flex-start; flex-direction: column; } .password-form { flex-direction: column; } }
    </style>
</head>
<body>
<main>
    <header>
        <h1>Student Accounts</h1>
        <a href="index.php">Back to dashboard</a>
    </header>

    <section class="panel">
        <h2>Account credentials</h2>
        <p class="password-note">Passwords are stored as secure hashes and cannot be viewed. To give a student a new password, set one below and share it with them securely. Deleting an account removes only login access; the student record and academic data remain.</p>

        <?php if ($message !== ''): ?>
            <div class="notice <?= $messageType === 'error' ? 'error' : '' ?>" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student name</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Account actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($studentAccounts): ?>
                        <?php foreach ($studentAccounts as $account): ?>
                            <tr>
                                <td><?= htmlspecialchars(str_pad((string) $account['student_id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></td>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td>
                                    <form class="password-form" method="POST" action="student_accounts.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                                        <input type="hidden" name="action" value="set_password">
                                        <input type="password" name="new_password" minlength="8" required autocomplete="new-password" placeholder="Set new password">
                                        <button type="submit">Set password</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="student_accounts.php" onsubmit="return confirm('Delete this student login account? Their student record and academic data will remain.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                                        <input type="hidden" name="action" value="delete_account">
                                        <button class="delete-button" type="submit">Delete account</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No student accounts have been created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
