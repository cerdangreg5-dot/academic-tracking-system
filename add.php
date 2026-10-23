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

$error = '';
// Fetch unique students
$students = $pdo->query("SELECT student_id, CONCAT(first_name, ' ', last_name) AS name FROM track_students ORDER BY last_name")->fetchAll();

// Fetch unique courses using MIN(course_id) to satisfy strict SQL modes
$courses = $pdo->query("SELECT MIN(course_id) AS course_id, course_name FROM track_courses GROUP BY course_name ORDER BY course_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $status = $_POST['status'] ?? 'Enrolled';
    $attendance = $_POST['attendance'] ?? 100.00;
    $midterm = $_POST['midterm'] ?? 0.00;
    $final = $_POST['final'] ?? 0.00;

    if (!empty($student_id) && !empty($course_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO track_enrollments (student_id, course_id, enrollment_status, midterm_grade, final_grade, attendance_rate, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
            $stmt->execute([$student_id, $course_id, $status, $midterm, $final, $attendance]);

            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error saving record: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select both a student and a course.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Academic Record</title>
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
            <h2>Add New Academic Record</h2>
            <?php if (!empty($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="add.php" method="POST">
                <div class="form-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="course_id">Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Enrolled">Enrolled</option>
                        <option value="Completed">Completed</option>
                        <option value="Dropped">Dropped</option>
                        <option value="Withdrawn">Withdrawn</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="midterm">Midterm Score</label>
                    <input type="number" step="0.01" id="midterm" name="midterm" value="0.00" min="0" max="100">
                </div>

                <div class="form-group">
                    <label for="final">Final Score</label>
                    <input type="number" step="0.01" id="final" name="final" value="0.00" min="0" max="100">
                </div>

                <div class="form-group">
                    <label for="attendance">Attendance Rate</label>
                    <input type="number" step="0.1" id="attendance" name="attendance" value="100.0" min="0" max="100">
                </div>

                <button type="submit" class="btn btn-success">Save Record</button>
            </form>
        </div>
    </main>
</body>
</html>