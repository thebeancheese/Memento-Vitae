<?php
require_once 'includes/db.php';

$message = "";
$message_type = "error";
$form = [
    "fullname" => "",
    "email" => ""
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"] ?? "";
    $form["fullname"] = $fullname;
    $form["email"] = $email;

    // Public registration always USER
    $role_id = ROLE_USER;

    if ($fullname == "" || $email == "" || $password == "" || $confirm_password == "") {
        $message = "Please fill out all fields.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        $check_res = mysqli_stmt_get_result($check);
        $exists = $check_res && mysqli_num_rows($check_res) > 0;
        mysqli_stmt_close($check);

        if ($exists) {
            $message = "Email already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (full_name, email, password, role_id, status, email_verified_at)
                 VALUES (?, ?, ?, ?, 'inactive', NULL)"
            );
            mysqli_stmt_bind_param($stmt, "sssi", $fullname, $email, $hashed, $role_id);

            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                $verification_link = createEmailVerificationLink($user_id);
                [$sent, $mail_error] = deliverActionLink(
                    $email,
                    $fullname,
                    "Verify your Memento Vitae account",
                    "Please verify your new account before logging in.",
                    $verification_link
                );

                if ($sent) {
                    header("Location: login.php?registered=1");
                    exit();
                }

                $message = "Account created, but the verification email could not be sent. Configure SMTP first.";
                $message_type = "error";
            } else {
                $message = "Error: " . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Register</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="public-site auth-page">
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

    <a class="public-login active" href="login.php">Login</a>
  </header>

  <main class="auth-main">
    <div class="card auth-card">
      <img class="auth-logo" src="assets/logo.png" alt="Memento Vitae">
      <h2 class="auth-subtitle">Create Account (User)</h2>

      <div class="note">This form creates a <b>User</b> account only.</div>

      <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php } ?>

      <form method="POST" class="auth-form">
        <input type="text" name="fullname" placeholder="Full Name" value="<?php echo e($form["fullname"]); ?>" required>
        <input type="email" name="email" placeholder="Email" value="<?php echo e($form["email"]); ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Re-type Password" required>
        <button type="submit">Register</button>
      </form>

      <div class="auth-links">
        <a class="link primary-auth-link" href="login.php">Already have an account? Login</a>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
