<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$role_id = (int)$_SESSION["role_id"];
if (!isUserRole($role_id)) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$message = "";
$show = ($_GET["show"] ?? "all") === "unread" ? "unread" : "all";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["mark_all_read"])) {
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "All notifications marked as read.";
    } else if (isset($_POST["notification_id"])) {
        $notification_id = (int)$_POST["notification_id"];
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "Notification marked as read.";
    }
}

$query = "SELECT notification_id, related_record_id, title, message, is_read, created_at
          FROM notifications
          WHERE user_id = ?";
$types = "i";
$params = [$user_id];

if ($show === "unread") {
    $query .= " AND is_read = 0";
}

$query .= " ORDER BY created_at DESC, notification_id DESC";

$stmt = mysqli_prepare($conn, $query);
stmtBindParams($stmt, $types, $params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($row = mysqli_fetch_assoc($res)) {
    $notifications[] = $row;
}
mysqli_stmt_close($stmt);

$unread_count = unreadNotificationCount($user_id);
$record_page = "user_record_view.php";
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Notifications</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">

<div class="page-shell">
  <div class="card" style="max-width:none; text-align:left;">
    <h1 style="text-align:center;">Memento Vitae</h1>
    <h2 style="text-align:center;">Notifications Center</h2>

    <?php if ($message !== "") { ?>
      <div class="alert alert-success"><?php echo e($message); ?></div>
    <?php } ?>

    <div class="inline-actions" style="justify-content:space-between; margin-top:16px;">
      <div class="inline-actions">
        <a class="smallbtn" href="dashboard.php">Back</a>
        <a class="smallbtn" href="notifications.php">Show All</a>
        <a class="smallbtn" href="notifications.php?show=unread">Unread Only</a>
      </div>

      <form method="POST" style="margin:0;">
        <input type="hidden" name="mark_all_read" value="1">
        <button type="submit" style="width:auto;">Mark All Read</button>
      </form>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="label">Unread Alerts</div>
        <div class="value"><?php echo $unread_count; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Visible Items</div>
        <div class="value"><?php echo count($notifications); ?></div>
      </div>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Latest Alerts</h3>

      <?php if (!empty($notifications)) { ?>
        <div class="notification-list">
          <?php foreach ($notifications as $notification) { ?>
            <div class="notification-item <?php echo (int)$notification["is_read"] === 0 ? "unread" : ""; ?>">
              <h3><?php echo e($notification["title"]); ?></h3>
              <div class="timeline-meta"><?php echo e($notification["created_at"]); ?></div>
                <div><?php echo e($notification["message"]); ?></div>

              <div class="inline-actions" style="margin-top:12px;">
                <?php if (!empty($notification["related_record_id"])) { ?>
                  <a class="smallbtn" href="<?php echo e($record_page); ?>?id=<?php echo (int)$notification["related_record_id"]; ?>">Open Record</a>
                <?php } ?>
                <?php if ((int)$notification["is_read"] === 0) { ?>
                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="notification_id" value="<?php echo (int)$notification["notification_id"]; ?>">
                    <button type="submit" style="width:auto;">Mark Read</button>
                  </form>
                <?php } else { ?>
                  <span class="badge">Read</span>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="table-empty">No notifications to show.</div>
      <?php } ?>
    </div>
  </div>
</div>

</body>
</html>
