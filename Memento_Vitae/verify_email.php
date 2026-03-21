<?php
require_once 'includes/db.php';

$token = trim($_GET["token"] ?? "");
$message = "";
$message_type = "error";

if ($token === "") {
    $message = "Verification token is missing.";
} else {
    $token_row = findValidTokenRecord("email_verification_tokens", $token);

    if (!$token_row) {
        $message = "This verification link is invalid or expired.";
    } else {
        $user_id = (int)$token_row["user_id"];
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET status = 'active', email_verified_at = NOW()
             WHERE user_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        markTokenUsed("email_verification_tokens", "verification_id", (int)$token_row["verification_id"]);
        header("Location: login.php?verified=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Verify Email</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">
  <div class="card">
    <h1>Memento Vitae</h1>
    <h2>Email Verification</h2>

    <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>

    <a class="link" href="login.php">Back to Login</a>
    <a class="link" href="resend_verification.php">Request New Verification Link</a>
  </div>
</body>
</html>
