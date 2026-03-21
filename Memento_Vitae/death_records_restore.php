<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isAdminRole((int)$_SESSION["role_id"])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: death_records_list.php?view=archived");
    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        dr.record_id,
        dr.deceased_name,
        dr.applicant_user_id,
        dr.deleted_at,
        creator.full_name AS creator_name,
        deleter.full_name AS archived_by_name
     FROM death_records dr
     JOIN users creator ON dr.created_by = creator.user_id
     LEFT JOIN users deleter ON dr.deleted_by = deleter.user_id
     WHERE dr.record_id = ? AND dr.deleted_at IS NOT NULL
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$record = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$record) {
    header("Location: death_records_list.php?view=archived");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $restore = mysqli_prepare($conn, "UPDATE death_records SET deleted_at = NULL, deleted_by = NULL WHERE record_id = ?");
    mysqli_stmt_bind_param($restore, "i", $id);
    mysqli_stmt_execute($restore);
    mysqli_stmt_close($restore);

    logRecordActivity(
        $id,
        $user_id,
        "record_restored",
        "Record for " . $record["deceased_name"] . " was restored to the active workflow.",
        null,
        null,
        "",
        (int)$record["applicant_user_id"]
    );

    createNotification(
        (int)$record["applicant_user_id"],
        "Application restored",
        "The record for " . $record["deceased_name"] . " has been restored to the active workflow.",
        $id
    );

    header("Location: death_records_list.php?view=archived&deleted=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Restore Record</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">

<div class="card" style="max-width:720px; text-align:left;">
  <h1 style="text-align:center;">Memento Vitae</h1>
  <h2 style="text-align:center;">Restore Archived Record</h2>

  <p class="note" style="text-align:center;">
    You are about to restore:
  </p>

  <div style="margin-top:12px;">
    <b>ID:</b> <?php echo (int)$record["record_id"]; ?><br>
    <b>Deceased Name:</b> <?php echo e($record["deceased_name"]); ?><br>
    <b>Created By:</b> <?php echo e($record["creator_name"]); ?><br>
    <b>Archived At:</b> <?php echo e($record["deleted_at"]); ?><br>
    <b>Archived By:</b> <?php echo e($record["archived_by_name"] ?: "Unknown"); ?><br>
  </div>

  <p class="success-copy">
    Restoring this record will return it to the active workflow board and make it visible again to the applicant.
  </p>

  <form method="POST">
    <button type="submit">Yes, Restore</button>
  </form>

  <div style="text-align:center; margin-top:12px;">
    <a class="smallbtn" href="death_records_list.php?view=archived">Cancel</a>
  </div>
</div>

</body>
</html>
