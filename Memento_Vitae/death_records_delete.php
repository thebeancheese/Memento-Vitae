<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$role_id = (int)$_SESSION["role_id"];
$user_id = (int)$_SESSION["user_id"];

if (isUserRole($role_id)) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: death_records_list.php");
    exit();
}

$id = (int)$_GET["id"];

// Load record (and check permission for staff)
if (isAdminRole($role_id)) {
    $sql = "SELECT dr.record_id, dr.deceased_name, dr.created_by, dr.applicant_user_id, u.full_name AS creator_name
            FROM death_records dr
            JOIN users u ON dr.created_by = u.user_id
            WHERE dr.record_id = ? AND dr.deleted_at IS NULL
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
} else {
    $sql = "SELECT dr.record_id, dr.deceased_name, dr.created_by, dr.applicant_user_id, u.full_name AS creator_name
            FROM death_records dr
            JOIN users u ON dr.created_by = u.user_id
            WHERE dr.record_id = ? AND dr.created_by = ? AND dr.deleted_at IS NULL
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$record = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$record) {
    header("Location: death_records_list.php");
    exit();
}

// If confirmed archive
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isAdminRole($role_id)) {
        $del = mysqli_prepare($conn, "UPDATE death_records SET deleted_at = NOW(), deleted_by = ? WHERE record_id = ?");
        mysqli_stmt_bind_param($del, "ii", $user_id, $id);
    } else {
        $del = mysqli_prepare($conn, "UPDATE death_records SET deleted_at = NOW(), deleted_by = ? WHERE record_id = ? AND created_by = ?");
        mysqli_stmt_bind_param($del, "iii", $user_id, $id, $user_id);
    }

    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    logRecordActivity(
        $id,
        $user_id,
        "record_archived",
        "Record for " . $record["deceased_name"] . " was archived.",
        null,
        null,
        "",
        (int)$record["applicant_user_id"]
    );
    createNotification(
        (int)$record["applicant_user_id"],
        "Application archived",
        "The record for " . $record["deceased_name"] . " has been archived from the active workflow.",
        $id
    );

    header("Location: death_records_list.php?deleted=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Archive Record</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">

<div class="card" style="max-width:720px; text-align:left;">
    <h1 style="text-align:center;">Memento Vitae</h1>
  <h2 style="text-align:center;">Archive Record</h2>

  <p class="note" style="text-align:center;">
    You are about to archive:
  </p>

  <div style="margin-top:12px;">
    <b>ID:</b> <?php echo $record["record_id"]; ?><br>
    <b>Deceased Name:</b> <?php echo htmlspecialchars($record["deceased_name"]); ?><br>
    <b>Created By:</b> <?php echo htmlspecialchars($record["creator_name"]); ?><br>
  </div>

  <p class="warning-copy">
    This will remove the record from the active workflow, but an admin can restore it later.
  </p>

  <form method="POST">
    <button type="submit">Yes, Archive</button>
  </form>

  <div style="text-align:center; margin-top:12px;">
    <a class="smallbtn" href="death_records_list.php">Cancel</a>
  </div>
</div>

</body>
</html>
