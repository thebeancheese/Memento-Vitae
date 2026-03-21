<?php
require_once 'includes/db.php';

$message = "";
$message_type = "success";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $message = "Please enter your email address.";
        $message_type = "error";
    } else {
        $user = findUserByEmail($email);
        if ($user && !empty($user["email_verified_at"])) {
            $reset_link = createPasswordResetLink((int)$user["user_id"]);
            [$sent, $mail_error] = deliverActionLink(
                $user["email"],
                $user["full_name"],
                    "Reset your Memento Vitae password",
                "Use the secure link below to set a new password.",
                $reset_link
            );
            if (!$sent && mailConfigured()) {
                $message = "We found the account, but the reset email could not be sent right now.";
                $message_type = "error";
            } else if (!$sent) {
                $message = "SMTP is not configured yet, so reset email sending is unavailable.";
                $message_type = "error";
            }
        }

        if ($message === "") {
            $message = "If the email exists and is verified, a reset link has been sent.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Forgot Password</title>
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
      <h2 class="auth-subtitle">Forgot Password</h2>

      <?php if ($message !== "") { ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
      <?php } ?>

      <form method="POST" class="auth-form">
        <input type="email" name="email" placeholder="Registered Email" value="<?php echo e($email); ?>" required>
        <button type="submit">Send Reset Link</button>
      </form>

      <div class="auth-links">
        <a class="link primary-auth-link" href="login.php">Back to Login</a>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
