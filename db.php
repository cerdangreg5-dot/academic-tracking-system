<?php
$host = 'localhost';
$db = 'academic_tracking';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $studentIdColumn = $pdo->query("SHOW COLUMNS FROM system_users LIKE 'student_id'");
    if (!$studentIdColumn->fetch()) {
        $pdo->exec("ALTER TABLE system_users ADD COLUMN student_id INT NULL AFTER user_id");
    }

    $studentIdIndex = $pdo->query("SHOW INDEX FROM system_users WHERE Key_name = 'uq_system_users_student_id'");
    if (!$studentIdIndex->fetch()) {
        $pdo->exec("ALTER TABLE system_users ADD UNIQUE KEY uq_system_users_student_id (student_id)");
    }

    // Create the default administrator only when it does not already exist.
    $stmt = $pdo->prepare("SELECT user_id FROM system_users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();

    if (!$admin) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("INSERT INTO system_users (username, password_hash, full_name) VALUES (?, ?, ?)");
        $insertStmt->execute(['admin', $passwordHash, 'System Administrator']);
    }

    // 2. Automatically create or replace the academic_tracking view using your actual table columns
    $pdo->exec("
        CREATE OR REPLACE VIEW academic_tracking AS
        SELECT 
            e.enrollment_id AS ID,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            c.course_name AS course,
            e.enrollment_status AS status,
            COALESCE(e.midterm_grade, 0.00) AS midterm,
            COALESCE(e.final_grade, 0.00) AS final,
            COALESCE(e.attendance_rate, 100.00) AS attendance
        FROM track_enrollments e
        JOIN track_students s ON e.student_id = s.student_id
        JOIN track_courses c ON e.course_id = c.course_id
    ");

} catch (PDOException $e) {
    throw $e;
}
?> 