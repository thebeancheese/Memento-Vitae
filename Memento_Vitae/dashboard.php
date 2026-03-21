<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$role_id = (int)$_SESSION["role_id"];
$role_name = roleName((int)$role_id);
$notification_count = isUserRole($role_id) ? unreadNotificationCount($user_id) : 0;

if (isUserRole($role_id)) {
    $stats_stmt = mysqli_prepare(
        $conn,
        "SELECT
            COUNT(*) AS total_records,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_total,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_total,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_total
         FROM death_records
         WHERE applicant_user_id = ? AND deleted_at IS NULL"
    );
    mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
} else {
    $stats_stmt = mysqli_prepare(
        $conn,
        "SELECT
            COUNT(*) AS total_records,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_total,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_total,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_total
         FROM death_records
         WHERE deleted_at IS NULL"
    );
}

mysqli_stmt_execute($stats_stmt);
$stats_res = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_res) ?: [
    "total_records" => 0,
    "pending_total" => 0,
    "approved_total" => 0,
    "rejected_total" => 0
];
mysqli_stmt_close($stats_stmt);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-dashboard-page">
  <header class="public-header">
    <a class="public-brand" href="index.php" aria-label="Memento Vitae home">
      <img src="assets/logo.png" alt="Memento Vitae">
    </a>

    <nav class="public-nav" aria-label="Primary">
      <a href="index.php">Home</a>
      <a href="articles.php">Articles</a>
      <a href="requirements.php">Requirements</a>
      <a href="contact.php">Contact Us</a>
    </nav>

    <a class="public-login" href="logout.php">Logout</a>
  </header>

  <main class="dashboard-main">
    <div class="card dashboard-card">
      <img class="dashboard-logo" src="assets/logo.png" alt="Memento Vitae">
      <h2 class="dashboard-subtitle">Dashboard</h2>

      <p class="note dashboard-note">
        Welcome, <b><?php echo e($_SESSION["full_name"]); ?></b> |
        Role: <b><?php echo e($role_name); ?></b><?php if (isUserRole($role_id)) { ?> |
        Unread Notifications: <b><?php echo $notification_count; ?></b><?php } ?>
      </p>

      <div class="stat-grid dashboard-stat-grid">
        <div class="stat-card">
          <div class="label">Total Records</div>
          <div class="value"><?php echo (int)$stats["total_records"]; ?></div>
        </div>
        <div class="stat-card">
          <div class="label">Pending</div>
          <div class="value status-pending"><?php echo (int)$stats["pending_total"]; ?></div>
        </div>
        <div class="stat-card">
          <div class="label">Approved</div>
          <div class="value status-approved"><?php echo (int)$stats["approved_total"]; ?></div>
        </div>
        <div class="stat-card">
          <div class="label">Rejected</div>
          <div class="value status-rejected"><?php echo (int)$stats["rejected_total"]; ?></div>
        </div>
      </div>

      <div class="grid dashboard-grid">
        <?php if (isAdminRole($role_id)) { ?>
          <div class="tile">
            <h3>Death Records</h3>
            <p>View all records, apply filters, export reports, and manage submissions.</p>
            <a class="smallbtn" href="death_records_list.php">Open Records List</a>
            <a class="smallbtn" href="death_records_add.php" style="margin-left:8px;">Add Record</a>
          </div>

          <div class="tile">
            <h3>Admin Tools</h3>
            <p>Create Barangay staff accounts securely.</p>
            <a class="smallbtn" href="admin_create_account.php">Create Staff Account</a>
          </div>

        <?php } elseif (isStaffRole($role_id)) { ?>
          <div class="tile">
            <h3>Workflow Board</h3>
            <p>Create records, review all records, update statuses, and check audit history in one place.</p>
            <a class="smallbtn" href="death_records_add.php">Add Record</a>
            <a class="smallbtn" href="death_records_list.php" style="margin-left:8px;">Open Workflow Board</a>
          </div>

        <?php } else { ?>
          <div class="tile">
            <h3>My Application Status</h3>
            <p>View your submitted records, status timeline, and request a death certificate once a record is approved.</p>
            <a class="smallbtn" href="user_status.php">View My Status</a>
          </div>

          <div class="tile">
            <h3>Notifications</h3>
            <p>See approval updates, review notes, and recent record activity.</p>
            <a class="smallbtn" href="notifications.php">Open Notifications</a>
          </div>
        <?php } ?>
      </div>

      <div class="dashboard-logout">
        <a class="smallbtn" href="logout.php">Logout</a>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
