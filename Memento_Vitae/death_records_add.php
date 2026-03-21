<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// User (role 3) cannot add
if (isUserRole((int)$_SESSION["role_id"])) {
    header("Location: dashboard.php");
    exit();
}

$message = "";
$message_type = "error";
$duplicate_records = [];
$form = [
    "applicant_user_id" => "",
    "deceased_name" => "",
    "date_of_death" => "",
    "place_of_death" => "",
    "cause_of_death" => "",
    "informant_name" => "",
    "relationship" => ""
];

// Load all USERS for applicant dropdown
$user_list = [];
$u_res = mysqli_query($conn, "SELECT user_id, full_name, email FROM users WHERE role_id = " . ROLE_USER . " ORDER BY full_name ASC");
if ($u_res) {
    while ($u = mysqli_fetch_assoc($u_res)) {
        $user_list[] = $u;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $deceased_name  = trim($_POST["deceased_name"]);
    $date_of_death  = $_POST["date_of_death"];
    $place_of_death = trim($_POST["place_of_death"]);
    $cause_of_death = trim($_POST["cause_of_death"]);
    $informant_name = trim($_POST["informant_name"]);
    $relationship   = trim($_POST["relationship"]);
    $confirm_duplicate = isset($_POST["confirm_duplicate"]);

    $applicant_user_id = (int)$_POST["applicant_user_id"]; // the user who will track status
    $created_by        = (int)$_SESSION["user_id"];         // staff/admin who encoded it

    $form = [
        "applicant_user_id" => $applicant_user_id,
        "deceased_name" => $deceased_name,
        "date_of_death" => $date_of_death,
        "place_of_death" => $place_of_death,
        "cause_of_death" => $cause_of_death,
        "informant_name" => $informant_name,
        "relationship" => $relationship
    ];

    // Validate applicant is a real USER role
    $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role_id = ?");
    $user_role_id = ROLE_USER;
    mysqli_stmt_bind_param($check, "ii", $applicant_user_id, $user_role_id);
    mysqli_stmt_execute($check);
    $check_res = mysqli_stmt_get_result($check);
    $valid_user = mysqli_fetch_assoc($check_res);
    mysqli_stmt_close($check);

    if (!$valid_user) {
        $message = "Please select a valid User applicant.";
    } else if ($deceased_name=="" || $date_of_death=="" || $place_of_death=="" || $cause_of_death=="" || $informant_name=="" || $relationship=="") {
        $message = "Please fill out all fields.";
    } else {
        $dup = mysqli_prepare(
            $conn,
            "SELECT record_id, deceased_name, date_of_death, status
             FROM death_records
             WHERE deceased_name = ? AND date_of_death = ? AND applicant_user_id = ? AND deleted_at IS NULL
             ORDER BY date_submitted DESC"
        );
        mysqli_stmt_bind_param($dup, "ssi", $deceased_name, $date_of_death, $applicant_user_id);
        mysqli_stmt_execute($dup);
        $dup_res = mysqli_stmt_get_result($dup);
        while ($dup_row = mysqli_fetch_assoc($dup_res)) {
            $duplicate_records[] = $dup_row;
        }
        mysqli_stmt_close($dup);

        if (!empty($duplicate_records) && !$confirm_duplicate) {
            $message = "Possible duplicate found. Review the matching records below before saving.";
        } else {
            $tracking_code = generateTrackingCode();
            $sql = "INSERT INTO death_records
                    (tracking_code, deceased_name, date_of_death, place_of_death, cause_of_death, informant_name, relationship,
                     applicant_user_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssii",
                $tracking_code, $deceased_name, $date_of_death, $place_of_death, $cause_of_death,
                $informant_name, $relationship, $applicant_user_id, $created_by
            );

            if (mysqli_stmt_execute($stmt)) {
                $new_record_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                logRecordActivity(
                    $new_record_id,
                    $created_by,
                    "record_created",
                    "A new death record was created and assigned for tracking. Reference: " . $tracking_code . ".",
                    null,
                    "Pending",
                    "",
                    $applicant_user_id
                );
                createNotification(
                    $applicant_user_id,
                    "New application submitted",
                    "A death record for " . $deceased_name . " has been submitted and is now Pending. Reference: " . $tracking_code . ".",
                    $new_record_id
                );
                header("Location: death_records_list.php?added=1");
                exit();
            } else {
                $message = "Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Add Record</title>
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
    <div class="card dashboard-card add-record-card">
      <img class="dashboard-logo" src="assets/logo.png" alt="Memento Vitae">
      <h2 class="dashboard-subtitle">Add Death Record</h2>

      <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div>
      <?php } ?>

      <form method="POST" class="add-record-form" onsubmit="return confirm('Are you sure you want to save this record?');">

        <label class="note add-record-label">Applicant (User)</label>
        <select name="applicant_user_id" required>
          <option value="">Select User...</option>
          <?php foreach ($user_list as $u) { ?>
            <option value="<?php echo $u['user_id']; ?>" <?php echo (int)$form["applicant_user_id"] === (int)$u["user_id"] ? "selected" : ""; ?>>
              <?php echo e($u['full_name'] . " (" . $u['email'] . ")"); ?>
            </option>
          <?php } ?>
        </select>

        <input type="text" name="deceased_name" placeholder="Deceased Name" value="<?php echo e($form["deceased_name"]); ?>" required>
        <input type="date" name="date_of_death" value="<?php echo e($form["date_of_death"]); ?>" required>
        <input type="text" name="place_of_death" placeholder="Place of Death" value="<?php echo e($form["place_of_death"]); ?>" required>
        <input type="text" name="cause_of_death" placeholder="Cause of Death" value="<?php echo e($form["cause_of_death"]); ?>" required>
        <input type="text" name="informant_name" placeholder="Informant Name" value="<?php echo e($form["informant_name"]); ?>" required>
        <input type="text" name="relationship" placeholder="Relationship" value="<?php echo e($form["relationship"]); ?>" required>

        <?php if (!empty($duplicate_records)) { ?>
          <label class="checkline">
            <input type="checkbox" name="confirm_duplicate" value="1">
            I reviewed the duplicate warning and still want to save this record.
          </label>
        <?php } ?>

        <button type="submit">Save Record</button>
      </form>

      <?php if (!empty($duplicate_records)) { ?>
        <div class="panel add-record-panel">
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

      <div class="dashboard-logout">
        <a class="smallbtn" href="dashboard.php">Back</a>
        <a class="smallbtn" href="death_records_list.php" style="margin-left:8px;">View Records</a>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
