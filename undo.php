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
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')
) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'The request could not be verified. Please try again.'];
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$undoRecords = $_SESSION['undo_deletions'] ?? [];
if (!$undoRecords) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'There is no recently deleted record to restore.'];
    header('Location: index.php');
    exit;
}

$recordIndex = array_key_last($undoRecords);
$snapshot = $undoRecords[$recordIndex];
$hasAssessmentSnapshot = array_key_exists('assessments', $snapshot);
$record = $snapshot['enrollment'] ?? $snapshot;
$assessments = $snapshot['assessments'] ?? [];
require_once __DIR__ . '/db.php';

try {
    $pdo->beginTransaction();

    $restoreStmt = $pdo->prepare("
        INSERT INTO track_enrollments (
            enrollment_id, student_id, course_id, enrollment_date,
            enrollment_status, midterm_grade, final_grade, attendance_rate,
            financial_clearance, administrative_remarks, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $restoreStmt->execute([
        $record['enrollment_id'],
        $record['student_id'],
        $record['course_id'],
        $record['enrollment_date'],
        $record['enrollment_status'],
        $record['midterm_grade'],
        $record['final_grade'],
        $record['attendance_rate'],
        $record['financial_clearance'],
        $record['administrative_remarks'],
        $record['created_at']
    ]);

    if ($assessments) {
        $assessmentStmt = $pdo->prepare("
            INSERT INTO track_assessments (
                assessment_id, enrollment_id, student_id, course_id,
                assessment_type, assessment_date, achieved_score,
                maximum_score, instructor_feedback, graded_by, is_makeup_exam
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($assessments as $assessment) {
            $assessmentStmt->execute([
                $assessment['assessment_id'],
                $assessment['enrollment_id'],
                $assessment['student_id'],
                $assessment['course_id'],
                $assessment['assessment_type'],
                $assessment['assessment_date'],
                $assessment['achieved_score'],
                $assessment['maximum_score'],
                $assessment['instructor_feedback'],
                $assessment['graded_by'],
                $assessment['is_makeup_exam']
            ]);
        }
    }

    $pdo->commit();
    unset($undoRecords[$recordIndex]);
    $_SESSION['undo_deletions'] = array_values($undoRecords);
    if (!$_SESSION['undo_deletions']) {
        unset($_SESSION['undo_deletions']);
    }
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => $hasAssessmentSnapshot
            ? 'Record ' . str_pad((string) (int) $record['enrollment_id'], 4, '0', STR_PAD_LEFT)
                . ' and ' . count($assessments) . ' assessment(s) have been restored.'
            : 'Record ' . str_pad((string) (int) $record['enrollment_id'], 4, '0', STR_PAD_LEFT)
                . ' has been restored, but its assessments were not saved before deletion and could not be restored.'
    ];
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Academic record restoration failed: ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'The record could not be restored. Its student and course must still exist, and the ID must be available.'
    ];
}

header('Location: index.php');
exit;
