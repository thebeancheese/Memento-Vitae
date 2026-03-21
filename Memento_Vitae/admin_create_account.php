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

$message = "";
$message_type = "error";
$staff_role = ["role_id" => ROLE_BARANGAY_STAFF, "role_name" => "Barangay Staff"];
$form = [
    "fullname" => "",
    "email" => ""
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $role_id  = ROLE_BARANGAY_STAFF;
    $form["fullname"] = $fullname;
    $form["email"] = $email;

    if ($fullname == "" || $email == "" || $password == "") {
        $message = "Please fill out all fields.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id, role_id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);
        $existing_user = $check_res ? mysqli_fetch_assoc($check_res) : null;
        mysqli_stmt_close($check);

        if ($existing_user && isAdminRole((int)$existing_user["role_id"])) {
            $message = "That email is already assigned to an admin account.";
        } else if ($existing_user && isStaffRole((int)$existing_user["role_id"])) {
            $message = "That email is already a staff account.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            if ($existing_user && isUserRole((int)$existing_user["role_id"])) {
                $user_id = (int)$existing_user["user_id"];
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET full_name = ?, password = ?, role_id = ?, status = 'active', email_verified_at = NOW()
                     WHERE user_id = ?"
                );
                mysqli_stmt_bind_param($stmt, "ssii", $fullname, $hashed, $role_id, $user_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($ok) {
                    $message = "Existing user account promoted to staff successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error: " . mysqli_error($conn);
                }
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users (full_name, email, password, role_id, status, email_verified_at)
                     VALUES (?, ?, ?, ?, 'active', NOW())"
                );
                mysqli_stmt_bind_param($stmt, "sssi", $fullname, $email, $hashed, $role_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($ok) {
                    $message = "Staff account created successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Admin Create Staff</title>
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
    <div class="card dashboard-card add-record-card admin-staff-card">
      <img class="dashboard-logo" src="assets/logo.png" alt="Memento Vitae">
      <h2 class="dashboard-subtitle">Admin: Create Barangay Staff</h2>

      <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php } ?>

      <form method="POST" class="add-record-form">
        <input type="text" name="fullname" placeholder="Staff Full Name" value="<?php echo e($form["fullname"]); ?>" required>
        <input type="email" name="email" placeholder="Staff Email" value="<?php echo e($form["email"]); ?>" required>
        <input type="password" name="password" placeholder="Staff Password" required>
        <div class="note">New staff accounts are created as <b><?php echo e($staff_role["role_name"]); ?></b>.</div>
        <div class="note">If the email already belongs to a user account, it will be promoted to staff.</div>
        <button type="submit">Create Staff</button>
      </form>

      <div class="dashboard-logout">
        <a class="smallbtn" href="dashboard.php">Back to Dashboard</a>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
