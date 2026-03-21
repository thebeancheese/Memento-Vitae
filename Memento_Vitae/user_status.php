<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isUserRole((int)$_SESSION["role_id"])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$unread_count = unreadNotificationCount($user_id);

$sql = "SELECT
            record_id,
            tracking_code,
            deceased_name,
            status,
            date_submitted,
            (
                SELECT dcr.status
                FROM death_certificate_requests dcr
                WHERE dcr.record_id = death_records.record_id
                  AND dcr.requester_user_id = ?
                ORDER BY dcr.submitted_at DESC, dcr.request_id DESC
                LIMIT 1
            ) AS latest_request_status,
            (
                SELECT dcr.email_status
                FROM death_certificate_requests dcr
                WHERE dcr.record_id = death_records.record_id
                  AND dcr.requester_user_id = ?
                ORDER BY dcr.submitted_at DESC, dcr.request_id DESC
                LIMIT 1
            ) AS latest_email_status
        FROM death_records
        WHERE applicant_user_id = ? AND deleted_at IS NULL
        ORDER BY date_submitted DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - My Status</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .wrap { width: 92%; max-width: 980px; margin: 20px auto; }
    table { width:100%; border-collapse:collapse; background:#1a1a1a; border:1px solid #2a2a2a; }
    th, td { padding:12px; border-bottom:1px solid #2a2a2a; text-align:left; }
    th { color:#fff; }
  </style>
</head>
<body class="app-content-page">

<div class="wrap">
  <div class="card" style="max-width:none; text-align:left;">
    <h1 style="text-align:center;">Memento Vitae</h1>
    <h2 style="text-align:center;">My Application Status</h2>

    <div style="text-align:center; margin-bottom:12px;" class="inline-actions">
      <a class="smallbtn" href="dashboard.php">Back</a>
      <a class="smallbtn" href="notifications.php">Notifications (<?php echo $unread_count; ?>)</a>
    </div>

    <table>
      <tr>
        <th>ID</th>
        <th>Reference</th>
        <th>Deceased Name</th>
        <th>Status</th>
        <th>Certificate Request</th>
        <th>Date Submitted</th>
        <th>Action</th>
      </tr>

      <?php if (mysqli_num_rows($result) > 0) { ?>
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
          <tr>
            <td><?php echo $row["record_id"]; ?></td>
            <td><?php echo e($row["tracking_code"]); ?></td>
            <td><?php echo htmlspecialchars($row["deceased_name"]); ?></td>
            <td><span class="badge status-<?php echo strtolower(e($row["status"])); ?>"><?php echo e($row["status"]); ?></span></td>
            <td>
              <?php if (!empty($row["latest_request_status"])) { ?>
                <div class="badge"><?php echo e($row["latest_request_status"]); ?></div>
                <div class="note" style="margin-top:6px;">Email: <?php echo e($row["latest_email_status"] ?: "Pending"); ?></div>
              <?php } else { ?>
                <span class="muted">Not requested</span>
              <?php } ?>
            </td>
            <td><?php echo $row["date_submitted"]; ?></td>
            <td>
              <div class="inline-actions">
                <a class="smallbtn" href="user_record_view.php?id=<?php echo $row["record_id"]; ?>">View</a>
                <?php if ($row["status"] === "Approved") { ?>
                  <a class="smallbtn" href="request_certificate.php?id=<?php echo $row["record_id"]; ?>">Request Certificate</a>
                <?php } ?>
              </div>
            </td>
          </tr>
        <?php } ?>
      <?php } else { ?>
        <tr>
          <td colspan="7" class="table-empty">No submitted records yet.</td>
        </tr>
      <?php } ?>
    </table>

  </div>
</div>

</body>
</html>

<?php mysqli_stmt_close($stmt); ?>
