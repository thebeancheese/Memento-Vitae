<?php
require_once 'includes/db.php';

$message = "";
$message_type = "success";
$google_enabled = googleAuthConfigured();
$show_resend_link = false;

if (isset($_GET["registered"])) {
    $message = "Registration successful. Verify the email first, then log in.";
    $message_type = "success";
}

if (isset($_GET["verified"])) {
    $message = "Email verified successfully. You can now log in.";
    $message_type = "success";
}

if (isset($_GET["reset"])) {
    $message = "Password updated successfully. Please log in with the new password.";
    $message_type = "success";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["google_credential"])) {
        [$ok, $error_message, $tokeninfo] = verifyGoogleCredential($_POST["google_credential"]);

        if (!$ok) {
            $message = $error_message;
            $message_type = "error";
        } else {
            $google_sub = (string)($tokeninfo["sub"] ?? "");
            $google_email = trim((string)($tokeninfo["email"] ?? ""));
            $google_name = trim((string)($tokeninfo["name"] ?? ""));

            $u = findUserBySocialAccount("google", $google_sub);
            if (!$u && $google_email !== "") {
                $u = findUserByEmail($google_email);
            }

            if (!$u) {
                $user_id = createSocialUser($google_name !== "" ? $google_name : $google_email, $google_email, ROLE_USER);
                $u = findUserByEmail($google_email);
                logRecordActivity(null, $user_id, "account_created_google", "A new user account was created using Google sign-in.");
            }

            if (!$u) {
                $message = "Unable to create or find the Google account.";
                $message_type = "error";
            } else if ($u["status"] !== "active") {
                $message = !empty($u["email_verified_at"])
                    ? "Account is not active."
                    : "Please verify your email address before logging in.";
                $message_type = "error";
                $show_resend_link = empty($u["email_verified_at"]);
            } else {
                upsertSocialAccount((int)$u["user_id"], "google", $google_sub, $google_email);
                loginUserSession($u);
                header("Location: dashboard.php");
                exit();
            }
        }
    } else {
        $email = trim($_POST["email"]);
        $pass  = $_POST["password"];
        $u = findUserByEmail($email);

        if ($u) {
            if ($u["status"] !== "active") {
                $message = !empty($u["email_verified_at"])
                    ? "Account is not active."
                    : "Please verify your email address before logging in.";
                $message_type = "error";
                $show_resend_link = empty($u["email_verified_at"]);
            } else if (password_verify($pass, $u["password"])) {
                loginUserSession($u);
                header("Location: dashboard.php");
                exit();
            } else {
                $message = "Invalid email or password.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid email or password.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Login</title>
  <link rel="stylesheet" href="css/style.css">
  <?php if ($google_enabled) { ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php } ?>
</head>
<body class="public-site login-page">
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

  <main class="login-main">
    <div class="card login-card">
      <img class="login-logo" src="assets/logo.png" alt="Memento Vitae">
      <h2 class="login-subtitle">Login</h2>

      <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php } ?>

      <?php if ($show_resend_link) { ?>
        <div class="context-action">
          <a class="link" href="resend_verification.php">Resend Verification Email</a>
        </div>
      <?php } ?>

      <form method="POST" class="login-form">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
      </form>

      <div class="oauth-divider"><span>or</span></div>

      <?php if ($google_enabled) { ?>
        <div id="g_id_onload"
             data-client_id="<?php echo e(GOOGLE_CLIENT_ID); ?>"
             data-callback="handleGoogleCredential"
             data-auto_prompt="false">
        </div>
        <div class="google-wrap">
          <div class="g_id_signin"
               data-type="standard"
               data-shape="pill"
               data-theme="outline"
               data-text="signin_with"
               data-size="large"
               data-logo_alignment="left"
               data-width="100%">
          </div>
        </div>

        <form method="POST" id="google-login-form" style="display:none;">
          <input type="hidden" name="google_credential" id="google_credential">
        </form>

        <script>
          function handleGoogleCredential(response) {
            if (!response || !response.credential) {
              return;
            }
            document.getElementById('google_credential').value = response.credential;
            document.getElementById('google-login-form').submit();
          }
        </script>
      <?php } else { ?>
        <div class="alert alert-error" style="margin-top:16px;">
          Google login is ready in code, but you still need to set your Client ID in
          <code>includes/oauth_config.php</code>.
        </div>
      <?php } ?>

      <div class="auth-links">
        <a class="link primary-auth-link" href="register.php">Create a User Account</a>
        <div class="auth-links-secondary">
          <a class="link" href="forgot_password.php">Forgot Password?</a>
        </div>
      </div>
    </div>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
