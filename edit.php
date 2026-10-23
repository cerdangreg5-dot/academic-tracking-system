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

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$error = '';

// Fetch the enrollment record along with student and course details using your actual column names
$stmt = $pdo->prepare("
    SELECT e.enrollment_id, e.student_id, e.course_id, e.enrollment_status, 
           e.attendance_rate, e.midterm_grade, e.final_grade,
           CONCAT(s.first_name, ' ', s.last_name) AS student_name,
           c.course_name
    FROM track_enrollments e
    JOIN track_students s ON e.student_id = s.student_id
    JOIN track_courses c ON e.course_id = c.course_id
    WHERE e.enrollment_id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'Enrolled';
    $attendance = $_POST['attendance'] ?? 100.00;
    $midterm = $_POST['midterm'] ?? 0.00;
    $final = $_POST['final'] ?? 0.00;

    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE track_enrollments 
            SET enrollment_status = ?, attendance_rate = ?, midterm_grade = ?, final_grade = ? 
            WHERE enrollment_id = ?
        ");
        $stmtUpdate->execute([$status, $attendance, $midterm, $final, $id]);

        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = 'Error updating record: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Academic Record</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1>Academic Tracking System</h1>
            <a href="index.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>
    </header>

    <main class="container main-content">
        <div class="form-card">
            <h2>Edit Academic Record (ID: <?= htmlspecialchars($record['enrollment_id']) ?>)</h2>
            <?php if (!empty($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="edit.php?id=<?= $record['enrollment_id'] ?>" method="POST">
                <div class="form-group">
                    <label>Student</label>
                    <input type="text" value="<?= htmlspecialchars($record['student_name']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Course</label>
                    <input type="text" value="<?= htmlspecialchars($record['course_name']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Enrolled" <?= $record['enrollment_status'] === 'Enrolled' ? 'selected' : '' ?>>Enrolled</option>
                        <option value="Completed" <?= $record['enrollment_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Dropped" <?= $record['enrollment_status'] === 'Dropped' ? 'selected' : '' ?>>Dropped</option>
                        <option value="Withdrawn" <?= $record['enrollment_status'] === 'Withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="midterm">Midterm Score</label>
                    <input type="number" step="0.01" id="midterm" name="midterm" value="<?= htmlspecialchars($record['midterm_grade'] ?? 0) ?>" min="0" max="100">
                </div>

                <div class="form-group">
                    <label for="final">Final Score</label>
                    <input type="number" step="0.01" id="final" name="final" value="<?= htmlspecialchars($record['final_grade'] ?? 0) ?>" min="0" max="100">
                </div>

                <div class="form-group">
                    <label for="attendance">Attendance Rate</label>
                    <input type="number" step="0.1" id="attendance" name="attendance" value="<?= htmlspecialchars($record['attendance_rate'] ?? 100) ?>" min="0" max="100">
                </div>

                <button type="submit" class="btn btn-primary">Update Record</button>
            </form>
        </div>
    </main>
</body>
</html>