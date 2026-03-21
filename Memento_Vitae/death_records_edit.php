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
$message = "";
$message_type = "error";
$duplicate_records = [];

// Load record
if (isAdminRole($role_id)) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM death_records WHERE record_id = ? AND deleted_at IS NULL LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM death_records WHERE record_id = ? AND created_by = ? AND deleted_at IS NULL LIMIT 1");
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

// Update record
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $deceased_name  = trim($_POST["deceased_name"]);
    $date_of_death  = $_POST["date_of_death"];
    $place_of_death = trim($_POST["place_of_death"]);
    $cause_of_death = trim($_POST["cause_of_death"]);
    $informant_name = trim($_POST["informant_name"]);
    $relationship   = trim($_POST["relationship"]);
    $change_remarks = trim($_POST["change_remarks"] ?? "");
    $confirm_duplicate = isset($_POST["confirm_duplicate"]);

    if ($deceased_name=="" || $date_of_death=="" || $place_of_death=="" || $cause_of_death=="" || $informant_name=="" || $relationship=="") {
        $message = "Please fill out all fields.";
    } else {
        $dup = mysqli_prepare(
            $conn,
            "SELECT record_id, deceased_name, date_of_death, status
             FROM death_records
             WHERE deceased_name = ? AND date_of_death = ? AND applicant_user_id = ? AND record_id <> ? AND deleted_at IS NULL
             ORDER BY date_submitted DESC"
        );
        mysqli_stmt_bind_param($dup, "ssii", $deceased_name, $date_of_death, $record["applicant_user_id"], $id);
        mysqli_stmt_execute($dup);
        $dup_res = mysqli_stmt_get_result($dup);
        while ($dup_row = mysqli_fetch_assoc($dup_res)) {
            $duplicate_records[] = $dup_row;
        }
        mysqli_stmt_close($dup);

        if (!empty($duplicate_records) && !$confirm_duplicate) {
            $message = "Possible duplicate found. Review the matching records below before saving.";
        } else if (isAdminRole($role_id)) {
            $upd = mysqli_prepare($conn,
                "UPDATE death_records
                 SET deceased_name=?, date_of_death=?, place_of_death=?, cause_of_death=?, informant_name=?, relationship=?
                 WHERE record_id=?"
            );
            mysqli_stmt_bind_param($upd, "ssssssi",
                $deceased_name, $date_of_death, $place_of_death, $cause_of_death, $informant_name, $relationship, $id
            );
        } else {
            $upd = mysqli_prepare($conn,
                "UPDATE death_records
                 SET deceased_name=?, date_of_death=?, place_of_death=?, cause_of_death=?, informant_name=?, relationship=?
                 WHERE record_id=? AND created_by=?"
            );
            mysqli_stmt_bind_param($upd, "ssssssii",
                $deceased_name, $date_of_death, $place_of_death, $cause_of_death, $informant_name, $relationship, $id, $user_id
            );
        }

        if (mysqli_stmt_execute($upd)) {
            $message = "Record updated successfully!";
            $message_type = "success";
            $details = "Record details were updated.";
            if ($change_remarks !== "") {
                $details .= " Note: " . $change_remarks;
            }
            logRecordActivity(
                $id,
                $user_id,
                "record_updated",
                $details,
                null,
                null,
                $change_remarks,
                (int)$record["applicant_user_id"]
            );
            if ((int)$record["applicant_user_id"] !== $user_id) {
                createNotification(
                    (int)$record["applicant_user_id"],
                    "Application details updated",
                    "Some details in the record for " . $deceased_name . " were updated.",
                    $id
                );
            }

            // refresh record values for display
            $record["deceased_name"] = $deceased_name;
            $record["date_of_death"] = $date_of_death;
            $record["place_of_death"] = $place_of_death;
            $record["cause_of_death"] = $cause_of_death;
            $record["informant_name"] = $informant_name;
            $record["relationship"] = $relationship;
        } else {
            $message = "Update failed: " . mysqli_error($conn);
        }
        mysqli_stmt_close($upd);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Edit Record</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">

<div class="card" style="max-width:760px; text-align:left;">
    <h1 style="text-align:center;">Memento Vitae</h1>
  <h2 style="text-align:center;">Edit Record</h2>

  <?php if ($message != "") { ?>
    <div class="alert alert-<?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php } ?>

  <form method="POST">
    <input type="text" name="deceased_name" required
      value="<?php echo htmlspecialchars($record['deceased_name']); ?>" placeholder="Deceased Name">

    <input type="date" name="date_of_death" required
      value="<?php echo htmlspecialchars($record['date_of_death']); ?>">

    <input type="text" name="place_of_death" required
      value="<?php echo htmlspecialchars($record['place_of_death']); ?>" placeholder="Place of Death">

    <input type="text" name="cause_of_death" required
      value="<?php echo htmlspecialchars($record['cause_of_death']); ?>" placeholder="Cause of Death">

    <input type="text" name="informant_name" required
      value="<?php echo htmlspecialchars($record['informant_name']); ?>" placeholder="Informant Name">

    <input type="text" name="relationship" required
      value="<?php echo htmlspecialchars($record['relationship']); ?>" placeholder="Relationship">

    <textarea name="change_remarks" rows="3" placeholder="Optional audit note about what changed"></textarea>

    <?php if (!empty($duplicate_records)) { ?>
      <label class="checkline">
        <input type="checkbox" name="confirm_duplicate" value="1">
        I reviewed the duplicate warning and still want to save these changes.
      </label>
    <?php } ?>

    <button type="submit">Save Changes</button>
  </form>

  <?php if (!empty($duplicate_records)) { ?>
    <div class="panel" style="margin-top:14px;">
      <h3>Possible Duplicate Matches</h3>
      <table class="data-table compact-table">
        <tr>
          <th>ID</th>
          <th>Deceased Name</th>
          <th>Date of Death</th>
          <th>Status</th>
        </tr>
        <?php foreach ($duplicate_records as $duplicate) { ?>
          <tr>
            <td><?php echo (int)$duplicate["record_id"]; ?></td>
            <td><?php echo e($duplicate["deceased_name"]); ?></td>
            <td><?php echo e($duplicate["date_of_death"]); ?></td>
            <td><?php echo e($duplicate["status"]); ?></td>
          </tr>
        <?php } ?>
      </table>
    </div>
  <?php } ?>

  <div style="text-align:center; margin-top:14px;">
    <a class="smallbtn" href="death_records_list.php">Back to List</a>
    <a class="smallbtn" href="record_view.php?id=<?php echo $record['record_id']; ?>" style="margin-left:8px;">View</a>
  </div>
</div>

</body>
</html>
