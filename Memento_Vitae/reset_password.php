<?php
require_once 'includes/db.php';

$token = trim($_GET["token"] ?? ($_POST["token"] ?? ""));
$message = "";
$message_type = "error";
$token_row = $token !== "" ? findValidTokenRecord("password_reset_tokens", $token) : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (!$token_row) {
        $message = "This reset link is invalid or expired.";
    } else if ($password === "" || $confirm_password === "") {
        $message = "Please fill out both password fields.";
    } else if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } else if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $user_id = (int)$token_row["user_id"];

        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        markTokenUsed("password_reset_tokens", "reset_id", (int)$token_row["reset_id"]);
        header("Location: login.php?reset=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Reset Password</title>
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
      <h2 class="auth-subtitle">Reset Password</h2>

      <?php if ($message !== "") { ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
      <?php } ?>

      <?php if ($token_row) { ?>
        <form method="POST" class="auth-form">
          <input type="hidden" name="token" value="<?php echo e($token); ?>">
          <input type="password" name="password" placeholder="New Password" required>
          <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
          <button type="submit">Update Password</button>
        </form>
      <?php } else { ?>
        <div class="alert alert-error">This reset link is invalid or expired.</div>
      <?php } ?>

      <div class="auth-links">
        <a class="link primary-auth-link" href="login.php">Back to Login</a>
        <div class="auth-links-secondary">
          <a class="link" href="forgot_password.php">Request Another Reset Link</a>
        </div>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
