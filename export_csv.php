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

// I-set ang header para ma-download bilang CSV file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=academic_tracking_report.csv');

$output = fopen('php://output', 'w');

// Ilagay ang column headers
fputcsv($output, array('ID', 'Student Name', 'Course', 'Status', 'Midterm', 'Final', 'Attendance'));

try {
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT ID, student_name, course, status, midterm, final, attendance FROM academic_tracking");
    } else {
        $stmt = $pdo->prepare("
            SELECT ID, student_name, course, status, midterm, final, attendance
            FROM academic_tracking
            WHERE ID IN (SELECT enrollment_id FROM track_enrollments WHERE student_id = ?)
        ");
        $stmt->execute([$studentId]);
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (Exception $e) {
    error_log('Academic records CSV export failed: ' . $e->getMessage());
    fclose($output);
    http_response_code(500);
    exit('Unable to export academic records.');
}

fclose($output);
exit();
?>