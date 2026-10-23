<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (strcasecmp($_SESSION['role'] ?? 'User', 'Admin') !== 0) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/db.php';

$id = trim($_GET['id'] ?? '');
if ($id === '' || !ctype_digit($id) || (int) $id < 1) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid record ID.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'The request could not be verified. Please try again.'];
        header('Location: index.php');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $recordStmt = $pdo->prepare('SELECT * FROM track_enrollments WHERE enrollment_id = ? FOR UPDATE');
        $recordStmt->execute([$id]);
        $record = $recordStmt->fetch();

        if (!$record) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'This academic record no longer exists.'];
            header('Location: index.php');
            exit;
        }

        $assessmentStmt = $pdo->prepare('SELECT * FROM track_assessments WHERE enrollment_id = ? FOR UPDATE');
        $assessmentStmt->execute([$id]);
        $assessments = $assessmentStmt->fetchAll();

        $deleteStmt = $pdo->prepare('DELETE FROM track_enrollments WHERE enrollment_id = ?');
        $deleteStmt->execute([$id]);
        $pdo->commit();

        $_SESSION['undo_deletions'] ??= [];
        $_SESSION['undo_deletions'][] = [
            'enrollment' => $record,
            'assessments' => $assessments
        ];
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Record deleted. You can undo this action from the dashboard.'
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Academic record deletion failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'The record could not be deleted. Please try again.'];
    }

    header('Location: index.php');
    exit;
}

$recordStmt = $pdo->prepare('SELECT enrollment_id FROM track_enrollments WHERE enrollment_id = ?');
$recordStmt->execute([$id]);
if (!$recordStmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'This academic record no longer exists.'];
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Deletion - Academic Tracking System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1>Academic Tracking System</h1>
            <a href="index.php" class="btn btn-secondary btn-sm">Cancel</a>
        </div>
    </header>

    <main class="container main-content">
        <div class="form-card text-center" style="max-width: 450px; margin: 40px auto; text-align: center;">
            <h2 style="color: var(--danger-color, #dc3545); margin-bottom: 1rem;">⚠️ Warning: Delete Record</h2>
            <p style="margin-bottom: 1.5rem; color: #4b5563;">
                Are you sure you want to delete this record (ID: <strong><?= htmlspecialchars(str_pad((string) (int) $id, 4, '0', STR_PAD_LEFT)) ?></strong>)? You can undo this action from the dashboard.
            </p>

            <form action="delete.php?id=<?= rawurlencode($id) ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="submit" name="confirm_delete" class="btn btn-danger">Yes, Delete</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
