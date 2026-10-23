<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
require_once __DIR__ . '/db.php';

// Safe role checking na may fallback sa 'User'
$user_role = isset($_SESSION['role']) ? trim($_SESSION['role']) : 'User';
$isAdmin = strcasecmp($user_role, 'Admin') === 0;
$studentId = filter_var($_SESSION['student_id'] ?? null, FILTER_VALIDATE_INT);
if (!$isAdmin) {
    if (!$studentId || $studentId < 1) {
        header('Location: login.php');
        exit;
    }
    header('Location: view_record.php');
    exit;
}

// Search and Filter variables
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Build safe query conditions
$whereClauses = $isAdmin
    ? ["1=1"]
    : ["ID IN (SELECT enrollment_id FROM track_enrollments WHERE student_id = ?)"];
$params = [];
if (!$isAdmin) {
    $params[] = $studentId;
}

if ($search !== '') {
    $whereClauses[] = "(student_name LIKE ? OR course LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $whereClauses[] = "status = ?";
    $params[] = $status_filter;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

try {
    // 1. Get total records count matching the filter/search
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM academic_tracking $whereSql");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    // 2. Fetch overall KPI statistics for dashboard overview
    $statsSql = "SELECT COUNT(*) as total, AVG(midterm) as avg_midterm, AVG(final) as avg_final, AVG(attendance) as avg_attendance FROM academic_tracking";
    if ($isAdmin) {
        $stats = $pdo->query($statsSql)->fetch();
    } else {
        $statsStmt = $pdo->prepare($statsSql . " WHERE ID IN (SELECT enrollment_id FROM track_enrollments WHERE student_id = ?)");
        $statsStmt->execute([$studentId]);
        $stats = $statsStmt->fetch();
    }

    // 3. Exactly 10 slides (pages) total configuration
    $totalPages = 10;
    
    // Calculate limit per page so total records fit into exactly 10 pages maximum
    $limit = ($totalRecords > 0) ? ceil($totalRecords / $totalPages) : 1;
    if ($limit < 1) $limit = 1;

    if ($totalRecords < 10 && $totalRecords > 0) {
        $totalPages = $totalRecords;
    } elseif ($totalRecords == 0) {
        $totalPages = 1;
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if ($page > $totalPages) $page = $totalPages;
    
    $offset = ($page - 1) * $limit;

    // 4. Fetch records securely
    $sql = "SELECT * FROM academic_tracking $whereSql ORDER BY ID ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    $records = [];
    $totalPages = 1;
    $totalRecords = 0;
    $stats = ['total' => 0, 'avg_midterm' => 0, 'avg_final' => 0, 'avg_attendance' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Tracking System - Enterprise Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-main: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            color: white;
            padding: 18px 0;
            box-shadow: 0 10px 25px -5px rgba(49, 46, 129, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar h1::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 24px;
            background: #818cf8;
            border-radius: 4px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            gap: 6px;
        }
        .btn-danger { background-color: rgba(239, 68, 68, 0.15); color: #fca5a5; }
        .btn-danger:hover { background-color: #ef4444; color: white; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .btn-warning { background-color: #f59e0b; color: white; }
        .btn-warning:hover { background-color: #d97706; }
        .btn-secondary { background-color: #64748b; color: white; }
        .btn-secondary:hover { background-color: #475569; }

        .main-content { padding: 40px 24px; }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .dashboard-header h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .header-actions form { margin: 0; }
        .flash-message {
            margin-bottom: 20px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        .flash-success { background: #d1fae5; color: #065f46; }
        .flash-error { background: #fee2e2; color: #991b1b; }

        /* KPI Summary Cards Style */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface);
            padding: 22px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat-card .label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
        }

        .filter-section {
            background: var(--surface);
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border-color);
        }
        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            flex: 1;
        }
        .filter-form input, .filter-form select {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            background: #f8fafc;
        }
        .btn-filter {
            background-color: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-filter:hover { background-color: var(--primary-hover); }
        .btn-reset {
            background-color: #f1f5f9;
            color: var(--text-muted);
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .table-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            font-size: 11px;
        }
        td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background-color: #f8fafc; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-enrolled { background-color: #e0e7ff; color: #3730a3; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-withdrawn { background-color: #fef3c7; color: #92400e; }
        .status-dropped { background-color: #fee2e2; color: #991b1b; }

        .actions { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; font-weight: 600; }
        .text-center { text-align: center; padding: 60px; color: var(--text-muted); }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 36px;
        }
        .pagination a {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-main);
            background: white;
        }
        .pagination a:hover { background: #f1f5f9; }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }

        @media print {
            .navbar, .filter-section, .header-actions, .actions, .pagination { display: none !important; }
            body { background: white; }
            .table-card { border: none; }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1>Academic Tracking System</h1>
            <div class="user-info">
                <span>Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong> (<em><?= htmlspecialchars($user_role) ?></em>)</span>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <main class="container main-content">
        <div class="dashboard-header">
            <h2>Student Academic Records</h2>
            <div class="header-actions">
                <a href="export_csv.php" class="btn btn-secondary btn-sm">📥 Export CSV</a>
                <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Print / PDF</button>
                
                <!-- Lilitaw lang kapag ADMIN -->
                <?php if (!$isAdmin): ?>
                    <a href="view_record.php" class="btn btn-secondary btn-sm">My Student Record</a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <a href="student_accounts.php" class="btn btn-secondary btn-sm">Student Accounts</a>
                    <?php if (!empty($_SESSION['undo_deletions'])): ?>
                        <?php $undoRecord = end($_SESSION['undo_deletions']); ?>
                        <form action="undo.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="btn btn-warning btn-sm">Undo Delete (ID: <?= htmlspecialchars(str_pad((string) (int) $undoRecord['enrollment_id'], 4, '0', STR_PAD_LEFT)) ?>)</button>
                        </form>
                    <?php endif; ?>
                    <a href="add.php" class="btn btn-success btn-sm">+ Add New Record</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message <?= $flash['type'] === 'error' ? 'flash-error' : 'flash-success' ?>" role="status">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="label"><?= $isAdmin ? 'Total System Records' : 'My Academic Records' ?></span>
                <span class="value" style="color: var(--primary);"><?= number_format($stats['total']) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Average Midterm Grade</span>
                <span class="value" style="color: #10b981;"><?= number_format($stats['avg_midterm'], 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Average Final Grade</span>
                <span class="value" style="color: #3b82f6;"><?= number_format($stats['avg_final'], 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="label">Average Attendance</span>
                <span class="value" style="color: #f59e0b;"><?= number_format($stats['avg_attendance'], 1) ?>%</span>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET" action="index.php" class="filter-form">
                <input type="text" name="search" placeholder="🔍 Search student name or course..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 260px;">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Enrolled" <?= $status_filter === 'Enrolled' ? 'selected' : '' ?>>Enrolled</option>
                    <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Withdrawn" <?= $status_filter === 'Withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                    <option value="Dropped" <?= $status_filter === 'Dropped' ? 'selected' : '' ?>>Dropped</option>
                </select>
                <button type="submit" class="btn btn-filter">Filter</button>
                <?php if ($search !== '' || $status_filter !== ''): ?>
                    <a href="index.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>
            <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
                Showing <strong><?= count($records) ?></strong> of <strong><?= $totalRecords ?></strong> records (Slide <?= $page ?> of <?= $totalPages ?>)
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th>No.</th>
                            <?php endif; ?>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Midterm</th>
                            <th>Final</th>
                            <th>Attendance</th>
                            <!-- Lilitaw lang ang Actions Header kapag ADMIN -->
                            <?php if ($isAdmin): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $recordIndex => $row): ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                        <td><?= $offset + $recordIndex + 1 ?></td>
                                    <?php endif; ?>
                                    <td><strong style="color: var(--primary);">#<?= htmlspecialchars(str_pad((string) (int) $row['ID'], 4, '0', STR_PAD_LEFT)) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['course']) ?></td>
                                    <td>
                                        <span class="badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= $row['midterm'] < 75 ? '#dc2626' : '#059669' ?>; font-weight: 600;">
                                            <?= number_format($row['midterm'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= $row['final'] < 75 ? '#dc2626' : '#059669' ?>; font-weight: 600;">
                                            <?= number_format($row['final'], 2) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= number_format($row['attendance'], 1) ?>%</strong></td>
                                    
                                    <!-- Lilitaw lang ang Edit/Delete Buttons kapag ADMIN -->
                                    <?php if ($isAdmin): ?>
                                        <td class="actions">
                                            <a href="edit.php?id=<?= $row['ID'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                            <a href="delete.php?id=<?= $row['ID'] ?>" class="btn btn-danger btn-sm">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $isAdmin ? 9 : 7 ?>" class="text-center">No academic records found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="index.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="index.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>