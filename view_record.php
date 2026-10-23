<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$isAdmin = strcasecmp($_SESSION['role'] ?? 'User', 'Admin') === 0;
$studentId = filter_var($_SESSION['student_id'] ?? null, FILTER_VALIDATE_INT);
if (!$isAdmin && (!$studentId || $studentId < 1)) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$student = null;
$enrollments = [];
$error = '';
$search_id = $isAdmin
    ? trim($_POST['search_id'] ?? $_GET['id'] ?? '')
    : (string) $studentId;

if (!$isAdmin || $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    if ($search_id === '' || !ctype_digit($search_id) || (int) $search_id < 1) {
        $error = 'Please enter a valid numeric student ID.';
    } else {
        $studentStmt = $pdo->prepare("
            SELECT student_id, first_name, last_name, cumulative_gpa, account_status
            FROM track_students
            WHERE student_id = ?
        ");
        $studentStmt->execute([$search_id]);
        $student = $studentStmt->fetch();

        if ($student) {
            $enrollmentStmt = $pdo->prepare("
                SELECT e.enrollment_id, c.course_name, e.enrollment_date,
                       e.enrollment_status, e.midterm_grade, e.final_grade,
                       e.attendance_rate
                FROM track_enrollments e
                JOIN track_courses c ON c.course_id = e.course_id
                WHERE e.student_id = ?
                ORDER BY e.enrollment_id
            ");
            $enrollmentStmt->execute([$search_id]);
            $enrollments = $enrollmentStmt->fetchAll();
        } else {
            $error = 'No student record found with ID: ' . $search_id;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isAdmin ? 'Student Record Lookup' : 'My Student Record' ?> - Academic Tracking System</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 16px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }
        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 32px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        h1, h2, h3 { margin-top: 0; }
        h1 { color: #1e3a8a; font-size: 26px; }
        .subtitle { margin-bottom: 24px; color: #64748b; }
        .search-form { display: flex; gap: 10px; margin-bottom: 24px; }
        .search-form input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
        }
        .search-form button, .back-link {
            display: inline-block;
            padding: 12px 18px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .alert-error {
            margin-bottom: 20px;
            padding: 12px 16px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }
        .card {
            margin-top: 20px;
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .profile-field span {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .profile-field strong { font-size: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            white-space: nowrap;
        }
        th { color: #475569; font-size: 12px; text-transform: uppercase; }
        .empty-state { color: #64748b; }
        .back-link, .logout-link {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 8px;
            background: #475569;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }
        .logout-link { background: #dc2626; }
        @media (max-width: 600px) {
            .container { padding: 22px 16px; }
            .search-form { flex-direction: column; }
            .page-header { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1><?= $isAdmin ? 'Student Record Lookup' : 'My Student Record' ?></h1>
            <?php if (!$isAdmin): ?>
                <a href="logout.php" class="logout-link">Logout</a>
            <?php endif; ?>
        </header>
        <p class="subtitle"><?= $isAdmin ? 'Enter a student ID to view that student profile and academic records.' : 'Your profile and academic records are shown below.' ?></p>

        <?php if ($isAdmin): ?>
        <form class="search-form" action="view_record.php" method="POST">
            <input type="number" name="search_id" min="1" step="1" value="<?= htmlspecialchars($search_id) ?>" placeholder="Enter student ID" required>
            <button type="submit">Search ID</button>
        </form>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($student): ?>
            <section class="card" aria-labelledby="student-details-heading">
                <h2 id="student-details-heading">Student Details</h2>
                <div class="profile-grid">
                    <div class="profile-field">
                        <span>Student ID</span>
                        <strong><?= htmlspecialchars((string) $student['student_id']) ?></strong>
                    </div>
                    <div class="profile-field">
                        <span>Name</span>
                        <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                    </div>
                    <div class="profile-field">
                        <span>Cumulative GPA</span>
                        <strong><?= $student['cumulative_gpa'] !== null ? htmlspecialchars((string) $student['cumulative_gpa']) : 'Not available' ?></strong>
                    </div>
                    <div class="profile-field">
                        <span>Account Status</span>
                        <strong><?= htmlspecialchars($student['account_status']) ?></strong>
                    </div>
                </div>
            </section>

            <section class="card" aria-labelledby="academic-records-heading">
                <h2 id="academic-records-heading">Academic Records</h2>
                <?php if ($enrollments): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Enrollment ID</th>
                                    <th>Course</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Midterm</th>
                                    <th>Final</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $enrollment['enrollment_id']) ?></td>
                                        <td><?= htmlspecialchars($enrollment['course_name']) ?></td>
                                        <td><?= htmlspecialchars($enrollment['enrollment_date']) ?></td>
                                        <td><?= htmlspecialchars($enrollment['enrollment_status']) ?></td>
                                        <td><?= $enrollment['midterm_grade'] !== null ? htmlspecialchars((string) $enrollment['midterm_grade']) : 'N/A' ?></td>
                                        <td><?= $enrollment['final_grade'] !== null ? htmlspecialchars((string) $enrollment['final_grade']) : 'N/A' ?></td>
                                        <td><?= $enrollment['attendance_rate'] !== null ? htmlspecialchars((string) $enrollment['attendance_rate']) . '%' : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty-state">There are no course enrollment records for this student.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <a href="index.php" class="back-link">Back to Dashboard</a>
        <?php endif; ?>
    </main>
</body>
</html>
