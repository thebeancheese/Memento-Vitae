<?php
require_once 'includes/db.php';

$contact_success = "";
$contact_error = "";
$contact_name = "";
$contact_email = "";
$contact_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $contact_name = trim((string)($_POST["full_name"] ?? ""));
    $contact_email = trim((string)($_POST["email"] ?? ""));
    $contact_message = trim((string)($_POST["message"] ?? ""));

    if ($contact_name === "" || $contact_email === "" || $contact_message === "") {
        $contact_error = "Please complete your name, email, and message.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = "Please enter a valid email address.";
    } elseif (!defined("CONTACT_FORM_RECIPIENT_EMAIL") || trim((string)CONTACT_FORM_RECIPIENT_EMAIL) === "") {
        $contact_error = "The contact email recipient is not configured.";
    } else {
        $safe_name = htmlspecialchars($contact_name, ENT_QUOTES, 'UTF-8');
        $safe_email = htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8');
        $safe_message = nl2br(htmlspecialchars($contact_message, ENT_QUOTES, 'UTF-8'));

        $subject = "New contact form message from " . $contact_name;
        $html = "
            <h2>New Contact Form Message</h2>
            <p><strong>Full Name:</strong> {$safe_name}</p>
            <p><strong>Email:</strong> {$safe_email}</p>
            <p><strong>Message:</strong><br>{$safe_message}</p>
        ";
        $plain = "New Contact Form Message\n"
            . "Full Name: {$contact_name}\n"
            . "Email: {$contact_email}\n\n"
            . "Message:\n{$contact_message}";

        [$sent, $send_error] = sendMailMessage(
            CONTACT_FORM_RECIPIENT_EMAIL,
            defined("CONTACT_FORM_RECIPIENT_NAME") ? CONTACT_FORM_RECIPIENT_NAME : "Memento Vitae",
            $subject,
            $html,
            $plain
        );

        if ($sent) {
            $contact_success = "Your message has been sent successfully.";
            $contact_name = "";
            $contact_email = "";
            $contact_message = "";
        } else {
            $contact_error = "We couldn't send your message right now. " . $send_error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Memento Vitae | Contact Us</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="public-site contact-page">
  <header class="public-header">
    <a class="public-brand" href="index.php" aria-label="Memento Vitae home">
      <img src="assets/logo.png" alt="Memento Vitae">
    </a>

    <nav class="public-nav" aria-label="Primary">
      <a href="index.php">Home</a>
      <a href="articles.php">Articles</a>
      <a href="requirements.php">Requirements</a>
      <a class="active" href="contact.php">Contact Us</a>
    </nav>

    <a class="public-login" href="login.php">Login</a>
  </header>

  <main class="contact-main">
    <section class="contact-shell">
      <div class="contact-intro">
        <p class="contact-eyebrow">Contact</p>
        <h1>CONTACT US</h1>
        <p>
          Lorem ipsum Anthony Fernan Cruz Dela. Lorem ipsum Cruzada Vincent Raiezen.
          Lorem ipsum Ivan Batumbacal. The cow jump over the lazy foxes. Lorem ipsum
          Anthony Fernan Cruz Dela. Lorem ipsum Cruzada Vincent Raiezen. Lorem ipsum
          Ivan Batumbacal. The cow jump over the lazy foxes.
        </p>
      </div>

      <div class="contact-layout">
        <section class="contact-info-list">
          <article class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">&#8962;</div>
            <div class="contact-info-copy">
              <h2>Address</h2>
              <p>
                Manila Street Block 57 Lot 28<br>
                Metro Clark Subdivision,<br>
                Barangay Sapang Malino<br>
                Mabalacat City
              </p>
            </div>
          </article>

          <article class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">&#9990;</div>
            <div class="contact-info-copy">
              <h2>Phone</h2>
              <p>0908-233-5550</p>
            </div>
          </article>

          <article class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">&#9993;</div>
            <div class="contact-info-copy">
              <h2>Email</h2>
              <p>mementovitae@gmail.com</p>
            </div>
          </article>
        </section>

        <section class="contact-form-card">
          <p class="contact-form-eyebrow">Message</p>
          <h2>Send a Message</h2>
          <p class="contact-form-intro">
            Tell us your concern, request, or follow-up and we will send it directly
            to the official inbox.
          </p>
          <form class="contact-form" action="#" method="post">
            <?php if ($contact_success !== "") { ?>
              <div class="contact-feedback contact-feedback-success"><?php echo e($contact_success); ?></div>
            <?php } ?>
            <?php if ($contact_error !== "") { ?>
              <div class="contact-feedback contact-feedback-error"><?php echo e($contact_error); ?></div>
            <?php } ?>
            <input type="text" name="full_name" placeholder="Full Name" value="<?php echo e($contact_name); ?>">
            <input type="email" name="email" placeholder="Email" value="<?php echo e($contact_email); ?>">
            <textarea name="message" placeholder="Type your message..."><?php echo e($contact_message); ?></textarea>
            <button type="submit" class="contact-submit">Send</button>
          </form>
        </section>
      </div>
    </section>
  </main>

  <footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>
</body>
</html>
